# Vector Bridge MVDB Indexer — Refactored Architecture

## Executive Summary

The current codebase has three core problems:
1. **Plugin.php is a 999-line god class** — mixes bootstrap, AJAX handlers, cron callbacks, asset enqueuing, and job logging
2. **Duplicated AJAX handlers** — `handleValidateConnection`, `handleDryRun`, `handleProcessUrl`, `handleUploadFile`, `handleGetJobs` all exist in BOTH Plugin.php and Settings.php with different implementations
3. **No service interfaces** — services are instantiated directly with `new`, making them untestable and tightly coupled

This architecture eliminates all three problems with minimal new abstractions.

---

## Target File Tree

```
src/
├── Core/
│   ├── Plugin.php                    # Bootstrap only (~60 lines)
│   ├── ServiceProvider.php           # DI container / service registration
│   └── HookManager.php              # All add_action/add_filter calls
│
├── Admin/
│   ├── AdminMenu.php                 # Menu registration + page routing (keep, slim down)
│   ├── Settings.php                  # Settings registration + rendering ONLY (no AJAX)
│   └── AssetManager.php             # enqueueAdminAssets() extracted from Plugin.php
│
├── Controllers/
│   ├── AbstractController.php        # Base: nonce check, capability check, JSON response helpers
│   ├── ConnectionController.php      # validate_connection
│   ├── IndexingController.php        # process_url, process_file, process_video, upload_file, dry_run
│   ├── CollectionController.php      # get_collections, get_collection_content, test_query, export, delete
│   └── JobController.php            # get_jobs
│
├── Services/
│   ├── Contracts/
│   │   ├── MVDBServiceInterface.php
│   │   ├── ChunkingServiceInterface.php
│   │   ├── ExtractionServiceInterface.php
│   │   └── ContentTypeBuilderInterface.php   # (already exists, move here)
│   │
│   ├── MVDBService.php               # implements MVDBServiceInterface
│   ├── ChunkingService.php           # implements ChunkingServiceInterface
│   ├── ExtractionService.php         # implements ExtractionServiceInterface
│   ├── VttExtractionService.php      # (keep as-is)
│   ├── ContentTypeFactory.php        # (keep, update imports)
│   └── ContentTypes/                 # (keep as-is)
│       ├── DefaultContentBuilder.php
│       ├── DocumentContentBuilder.php
│       ├── VideoContentBuilder.php
│       └── WebpageContentBuilder.php
│
├── Cron/
│   └── CronManager.php              # processContent() + indexChunks() + logJobStatus()
│
└── Support/
    └── Validation.php                # Shared sanitization/validation utilities
```

---

## Class Responsibilities

### Core Layer

#### `Plugin.php` (~60 lines)
**What it does now:** Everything (999 lines).
**What it does after:** Bootstrap only.

```php
class Plugin {
    private static ?Plugin $instance = null;
    private ServiceProvider $container;

    public static function getInstance(): Plugin { ... }

    private function init(): void {
        $this->container = new ServiceProvider();
        $this->container->register();

        (new HookManager($this->container))->register();

        if (is_admin() && current_user_can('manage_options')) {
            $this->container->get('admin_menu'); // triggers lazy init
        }
    }

    public function getContainer(): ServiceProvider {
        return $this->container;
    }
}
```

#### `ServiceProvider.php`
Simple associative container — no framework needed.

```php
class ServiceProvider {
    private array $services = [];
    private array $factories = [];

    public function register(): void {
        // Services (lazy — instantiated on first get())
        $this->factories['settings']   = fn() => new Settings();
        $this->factories['mvdb']       = fn() => new MVDBService();
        $this->factories['chunking']   = fn() => new ChunkingService();
        $this->factories['extraction'] = fn() => new ExtractionService();
        $this->factories['admin_menu'] = fn() => new AdminMenu();

        // Controllers (receive container for service access)
        $this->factories['controller.connection']  = fn() => new ConnectionController($this);
        $this->factories['controller.indexing']    = fn() => new IndexingController($this);
        $this->factories['controller.collection']  = fn() => new CollectionController($this);
        $this->factories['controller.job']         = fn() => new JobController($this);

        // Cron
        $this->factories['cron'] = fn() => new CronManager($this);
    }

    public function get(string $id): mixed {
        if (!isset($this->services[$id])) {
            if (!isset($this->factories[$id])) {
                throw new \Exception("Service '{$id}' not registered");
            }
            $this->services[$id] = ($this->factories[$id])();
        }
        return $this->services[$id];
    }
}
```

#### `HookManager.php`
Single place for ALL hook registration. No logic — just wiring.

```php
class HookManager {
    public function __construct(private ServiceProvider $container) {}

    public function register(): void {
        // Admin assets
        add_action('admin_enqueue_scripts', [$this->container->get('asset_manager'), 'enqueue']);

        // Settings
        add_action('admin_init', [$this->container->get('settings'), 'initSettings']);

        // AJAX — Connection
        add_action('wp_ajax_vector_bridge_validate_connection',
            [$this->container->get('controller.connection'), 'validateConnection']);

        // AJAX — Indexing
        add_action('wp_ajax_vector_bridge_dry_run',
            [$this->container->get('controller.indexing'), 'dryRun']);
        add_action('wp_ajax_vector_bridge_process_url',
            [$this->container->get('controller.indexing'), 'processUrl']);
        add_action('wp_ajax_vector_bridge_process_file',
            [$this->container->get('controller.indexing'), 'processFile']);
        add_action('wp_ajax_vector_bridge_process_video',
            [$this->container->get('controller.indexing'), 'processVideo']);
        add_action('wp_ajax_vector_bridge_upload_file',
            [$this->container->get('controller.indexing'), 'uploadFile']);

        // AJAX — Collections/Content Browser
        add_action('wp_ajax_vector_bridge_get_collections',
            [$this->container->get('controller.collection'), 'getCollections']);
        add_action('wp_ajax_vector_bridge_get_collection_content',
            [$this->container->get('controller.collection'), 'getCollectionContent']);
        add_action('wp_ajax_vector_bridge_test_query',
            [$this->container->get('controller.collection'), 'testQuery']);
        add_action('wp_ajax_vector_bridge_export_collection',
            [$this->container->get('controller.collection'), 'exportCollection']);
        add_action('wp_ajax_vector_bridge_delete_collection',
            [$this->container->get('controller.collection'), 'deleteCollection']);

        // AJAX — Content Browser (Settings.php duplicates — consolidated here)
        add_action('wp_ajax_vector_bridge_get_all_documents',
            [$this->container->get('controller.collection'), 'getAllDocuments']);
        add_action('wp_ajax_vector_bridge_search_documents',
            [$this->container->get('controller.collection'), 'searchDocuments']);
        add_action('wp_ajax_vector_bridge_get_document_details',
            [$this->container->get('controller.collection'), 'getDocumentDetails']);
        add_action('wp_ajax_vector_bridge_delete_document',
            [$this->container->get('controller.collection'), 'deleteDocument']);
        add_action('wp_ajax_vector_bridge_clear_all_documents',
            [$this->container->get('controller.collection'), 'clearAllDocuments']);

        // AJAX — Jobs
        add_action('wp_ajax_vector_bridge_get_jobs',
            [$this->container->get('controller.job'), 'getJobs']);

        // Cron callbacks
        add_action('vector_bridge_process_content',
            [$this->container->get('cron'), 'processContent'], 10, 4);
        add_action('vector_bridge_index_chunks',
            [$this->container->get('cron'), 'indexChunks'], 10, 2);

        // Plugin action links
        add_filter('plugin_action_links_' . VECTOR_BRIDGE_PLUGIN_BASENAME,
            [$this->container->get('settings'), 'addSettingsLink']);
    }
}
```

### Controllers

#### `AbstractController.php`
Eliminates the copy-pasted nonce/capability checks from every AJAX handler.

```php
abstract class AbstractController {
    protected ServiceProvider $container;

    public function __construct(ServiceProvider $container) {
        $this->container = $container;
    }

    protected function verifyRequest(): void {
        check_ajax_referer('vector_bridge_nonce', 'nonce');  // handles both GET and POST
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'vector-bridge-mvdb-indexer')], 403);
            wp_die();
        }
    }

    protected function success(array $data): void {
        wp_send_json_success($data);
    }

    protected function error(string $message, int $status = 400): void {
        wp_send_json_error(['message' => $message], $status);
    }

    protected function service(string $name): mixed {
        return $this->container->get($name);
    }
}
```

#### `ConnectionController.php`
```php
class ConnectionController extends AbstractController {
    public function validateConnection(): void {
        $this->verifyRequest();
        try {
            $result = $this->service('mvdb')->validateConnection();
            $this->success([
                'message' => __('Connection successful!', 'vector-bridge-mvdb-indexer'),
                'details' => $result
            ]);
        } catch (\Exception $e) {
            $this->error(__('Connection failed: ', 'vector-bridge-mvdb-indexer') . $e->getMessage());
        }
    }
}
```

#### `IndexingController.php`
Consolidates `handleDryRun`, `handleProcessUrl`, `handleProcessFile`, `handleProcessVideo`, `handleUploadFile` from Plugin.php. The Settings.php duplicates are eliminated entirely.

#### `CollectionController.php`
Consolidates all collection/document browsing AJAX from both Plugin.php and Settings.php:
- `getCollections`, `getCollectionContent`, `testQuery`, `exportCollection`, `deleteCollection` (from Plugin.php)
- `getAllDocuments`, `searchDocuments`, `getDocumentDetails`, `deleteDocument`, `clearAllDocuments` (from Settings.php)

#### `JobController.php`
Single `getJobs` method — replaces duplicate implementations in Plugin.php and Settings.php.

### Services Layer

#### `Contracts/MVDBServiceInterface.php`
```php
interface MVDBServiceInterface {
    public function validateConnection(): array;
    public function indexChunks(array $chunks, string $collection, string $source = ''): array;
    public function getCollections(): array;
    public function getAllDocuments(string $post_type = ''): array;
    public function searchDocuments(string $query_text, string $post_type = '', int $limit = 5): array;
    public function deleteDocument(string $document_id): array;  // was private — eliminates reflection hack
    public function deleteDocumentsByType(string $post_type): array;
}
```

#### `Contracts/ChunkingServiceInterface.php`
```php
interface ChunkingServiceInterface {
    public function chunkContent(string $content, string $source = ''): array;
    public function getChunkingStats(string $content): array;
    public function validateConfiguration(): array;
}
```

#### `Contracts/ExtractionServiceInterface.php`
```php
interface ExtractionServiceInterface {
    public function extractFromUrl(string $url): string;
    public function extractFromFile(string $file_path): string;
    public function extractFromHtml(string $html): string;
    public function extractFromText(string $text): string;
    public function getSupportedTypes(): array;
    public function isFileTypeSupported(string $file_path): bool;
}
```

### Cron Layer

#### `CronManager.php`
Extracts from Plugin.php: `processContent()`, `indexChunks()`, `logJobStatus()`, `loadSampleFixtures()`.

```php
class CronManager {
    public function __construct(private ServiceProvider $container) {}

    public function processContent(string $source, string $collection, string $type, array $metadata = []): void { ... }
    public function indexChunks(array $chunks, string $collection): void { ... }
    private function logJobStatus(string $job_id, string $hook, string $status, array $args = []): void { ... }
}
```

### Admin Layer

#### `Settings.php` (slimmed)
**Remove:** All AJAX handlers (moved to controllers).
**Keep:** `initSettings()`, `registerSetting()`, `renderField()`, `renderPage()`, sanitize callbacks, `get()`, `update()`.

#### `AssetManager.php`
Extracts `enqueueAdminAssets()` from Plugin.php.

```php
class AssetManager {
    public function enqueue(string $hook_suffix): void {
        if (strpos($hook_suffix, 'vector-bridge') === false) return;
        // wp_enqueue_style, wp_enqueue_script, wp_localize_script
    }
}
```

### Support Layer

#### `Validation.php`
Shared validation utilities — extracted from scattered inline validation.

```php
class Validation {
    public static function requireNonEmpty(string $value, string $field_name): string {
        $value = sanitize_text_field($value);
        if (empty($value)) {
            throw new \InvalidArgumentException(
                sprintf(__('%s is required.', 'vector-bridge-mvdb-indexer'), $field_name)
            );
        }
        return $value;
    }

    public static function requireUrl(string $url, string $field_name = 'URL'): string {
        $url = sanitize_url($url);
        if (empty($url)) {
            throw new \InvalidArgumentException(
                sprintf(__('%s is required.', 'vector-bridge-mvdb-indexer'), $field_name)
            );
        }
        return $url;
    }

    public static function requireFile(string $files_key): array {
        if (empty($_FILES[$files_key])) {
            throw new \InvalidArgumentException(__('No file uploaded.', 'vector-bridge-mvdb-indexer'));
        }
        return $_FILES[$files_key];
    }

    public static function clampInt(int $value, int $min, int $max): int {
        return max($min, min($max, $value));
    }

    public static function clampFloat(float $value, float $min, float $max): float {
        return max($min, min($max, $value));
    }

    public static function maskToken(string $token): string {
        if (strlen($token) <= 6) {
            return str_repeat('*', strlen($token));
        }
        return substr($token, 0, 6) . str_repeat('*', strlen($token) - 6);
    }

    public static function normalizeContent(string $content): string {
        $content = preg_replace('/\r\n|\r/', "\n", $content);
        $content = preg_replace('/[ \t]+/', ' ', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        return trim($content);
    }

    public static function formatTimestamp(float $seconds): string {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        return sprintf('%02d:%02d:%06.3f', $hours, $minutes, $secs);
    }

    public static function parseTimestamp(string $timestamp): float {
        $parts = explode(':', $timestamp);
        if (count($parts) === 3) {
            return (float)$parts[0] * 3600 + (float)$parts[1] * 60 + (float)$parts[2];
        }
        if (count($parts) === 2) {
            return (float)$parts[0] * 60 + (float)$parts[1];
        }
        return (float)$timestamp;
    }
}
```

---

## Migration Plan

### Phase 1: Extract Controllers (Task #3)
Target: Plugin.php goes from 999 → ~60 lines.

1. Create `src/Controllers/AbstractController.php`
2. Create `src/Controllers/ConnectionController.php` — move `handleValidateConnection()` from Plugin.php
3. Create `src/Controllers/IndexingController.php` — move `handleDryRun()`, `handleProcessUrl()`, `handleProcessFile()`, `handleProcessVideo()`, `handleUploadFile()` from Plugin.php
4. Create `src/Controllers/CollectionController.php` — move collection AJAX from Plugin.php + document AJAX from Settings.php
5. Create `src/Controllers/JobController.php` — move `handleGetJobs()` from Plugin.php
6. Create `src/Core/ServiceProvider.php`
7. Create `src/Core/HookManager.php`
8. Create `src/Admin/AssetManager.php` — move `enqueueAdminAssets()` from Plugin.php
9. Create `src/Cron/CronManager.php` — move `processContent()`, `indexChunks()`, `logJobStatus()`, `loadSampleFixtures()` from Plugin.php
10. Rewrite `Plugin.php` to bootstrap-only using ServiceProvider + HookManager

### Phase 2: Clean Settings.php + Add Interfaces (Task #4)
Target: Settings.php drops from 889 → ~350 lines. Services get interfaces.

1. Remove ALL AJAX handlers from Settings.php (now in controllers)
2. Create `src/Services/Contracts/MVDBServiceInterface.php`
3. Create `src/Services/Contracts/ChunkingServiceInterface.php`
4. Create `src/Services/Contracts/ExtractionServiceInterface.php`
5. Move existing `ContentTypeBuilderInterface.php` → `src/Services/Contracts/`
6. Add `implements` to MVDBService, ChunkingService, ExtractionService
7. Create `src/Support/Validation.php`
8. Update ServiceProvider type hints to use interfaces
9. Update `composer.json` autoload PSR-4 mapping if needed

### Verification Checklist
After each phase:
- [ ] All AJAX actions still fire (test each endpoint)
- [ ] Settings page renders and saves correctly
- [ ] No duplicate hook registrations
- [ ] No PHP fatal errors on activation
- [ ] Content processing pipeline works end-to-end

---

## Additional Fixes (from researcher's analysis)

These items were surfaced by the codebase research (REFACTOR_ANALYSIS.md) and are addressed in this architecture:

### Fix: Reflection hack for `deleteDocument()` (Issue #10)
Settings.php uses `ReflectionClass` to call `MVDBService::deleteDocument()` because it's private. **Solution:** Add `deleteDocument(string $id): array` to `MVDBServiceInterface` and make it public on `MVDBService`. The CollectionController calls it directly — no reflection needed.

### Fix: Duplicate `maskToken()` (Issue #6)
Identical implementations exist in Settings.php:353 and MVDBService.php:702. **Solution:** Move to `Support/Validation::maskToken(string $token): string`. Both classes call `Validation::maskToken()`.

### Fix: Duplicate timestamp formatting/parsing (Issue #7)
`formatTimestamp()` and `parseTimestamp()` are duplicated between VttExtractionService and VideoContentBuilder. **Solution:** Add `Validation::formatTimestamp(float $seconds): string` and `Validation::parseTimestamp(string $timestamp): float` to `Support/Validation.php`.

### Fix: Inconsistent nonce verification (Issue #3)
Plugin.php uses manual `wp_verify_nonce()`, Settings.php uses `check_ajax_referer()`. **Solution:** `AbstractController::verifyRequest()` standardizes on `check_ajax_referer('vector_bridge_nonce', 'nonce')` — the WordPress-recommended approach that handles both GET and POST.

### Fix: Non-existent interfaces (Issue #11)
ContentTypeFactory references `ExtractionServiceInterface` and `ChunkingServiceInterface` in return types for `createExtractor()` and `createChunker()` — but these files don't exist. **Solution:** Creating these interfaces in `Services/Contracts/` fixes the broken references.

### Fix: Duplicate content normalization (Issue #9)
Content normalization (line ending normalization + whitespace cleanup) appears in ChunkingService, ExtractionService, and DefaultContentBuilder. **Solution:** Add `Validation::normalizeContent(string $content): string` to consolidate. Services call the shared method.

### Fix: Duplicate title extraction (Issue #8)
MVDBService has its own `extractTitle()` separate from the ContentTypeBuilder interface. **Solution:** Remove `MVDBService::extractTitle()` — the ContentTypeFactory/builders already handle this. The CronManager should use the builder's `extractTitle()` instead.

---

## Key Decisions

1. **No framework DI container** — A simple closure-based ServiceProvider is sufficient. We have ~10 services. Adding a PSR-11 container or Illuminate Container would be over-engineering.

2. **Lazy instantiation** — Services are only created when first requested via `get()`. This avoids creating MVDBService (which initializes a Guzzle Client) on every page load.

3. **Controllers receive the container** — Not individual services. This keeps constructor signatures stable as services evolve. Each controller pulls what it needs via `$this->service('mvdb')`.

4. **Settings.php keeps sanitize callbacks** — These are WordPress settings API callbacks and belong with the settings registration code. They don't move to Validation.php.

5. **AbstractController handles security** — The `verifyRequest()` method uses `check_ajax_referer()` (WordPress best practice) and replaces ~50 duplicated lines of nonce/capability checking across all handlers.

6. **Existing ContentTypes/ directory stays** — The strategy pattern for content types is already well-designed. We just move the interface to `Contracts/`.

7. **AdminMenu.php keeps its render methods** — The inline HTML is WordPress-standard for admin pages. Extracting it to templates would be premature for this plugin's complexity level.

8. **`deleteDocument()` becomes public** — The current reflection hack in Settings.php is eliminated by adding the method to MVDBServiceInterface.
