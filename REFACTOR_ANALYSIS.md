# Vector Bridge MVDB Indexer - Refactor Analysis

## 1. Dependency Map (`new ClassName()` Instantiations)

### Plugin.php (src/Core/Plugin.php)

| Line | Instantiation | Context |
|------|--------------|---------|
| 54 | `new self()` | Singleton factory |
| 92 | `new MVDBService()` | `registerServices()` - hardcoded |
| 93 | `new ChunkingService()` | `registerServices()` - hardcoded |
| 94 | `new ExtractionService()` | `registerServices()` - hardcoded |
| 95 | `new Settings()` | `registerServices()` - hardcoded |
| 142 | `new AdminMenu()` | `initAdmin()` - hardcoded |
| 622 | `new ContentTypeFactory()` | `processContent()` - hardcoded in method body |

### Settings.php (src/Admin/Settings.php)

| Line | Instantiation | Context |
|------|--------------|---------|
| 466 | `new MVDBService()` | `handleValidateConnection()` |
| 507 | `new ChunkingService()` | `handleDryRun()` |
| 545 | `new MVDBService()` | `handleGetAllDocuments()` |
| 579 | `new MVDBService()` | `handleSearchDocuments()` |
| 613 | `new MVDBService()` | `handleGetDocumentDetails()` |
| 662 | `new MVDBService()` | `handleDeleteDocument()` |
| 693 | `new MVDBService()` | `handleClearAllDocuments()` |
| 739 | `new ExtractionService()` | `handleProcessUrl()` |
| 754 | `new ChunkingService()` | `handleProcessUrl()` |
| 764 | `new MVDBService()` | `handleProcessUrl()` |
| 822 | `new ExtractionService()` | `handleUploadFile()` |
| 836 | `new ChunkingService()` | `handleUploadFile()` |
| 846 | `new MVDBService()` | `handleUploadFile()` |

### MVDBService.php (src/Services/MVDBService.php)

| Line | Instantiation | Context |
|------|--------------|---------|
| 38 | `new Client([...])` | Constructor - Guzzle HTTP client |

### ExtractionService.php (src/Services/ExtractionService.php)

| Line | Instantiation | Context |
|------|--------------|---------|
| 55 | `new Client([...])` | Constructor - Guzzle HTTP client |
| 62 | `new HtmlConverter([...])` | Constructor |
| 68 | `new PdfParser()` | Constructor |
| 336 | `new VttExtractionService()` | `extractFromVtt()` - created on every call |

### ContentTypeFactory.php (src/Services/ContentTypeFactory.php)

| Line | Instantiation | Context |
|------|--------------|---------|
| 38 | `new VideoContentBuilder()` | `createDataBuilder()` static factory |
| 39 | `new DocumentContentBuilder()` | `createDataBuilder()` static factory |
| 40 | `new WebpageContentBuilder()` | `createDataBuilder()` static factory |
| 41 | `new DefaultContentBuilder()` | `createDataBuilder()` static factory |
| 56 | `new ExtractionService()` | `createExtractor()` - unused/future |
| 69 | `new ChunkingService()` | `createChunker()` - unused/future |

### DocumentContentBuilder.php

| Line | Instantiation | Context |
|------|--------------|---------|
| 307 | `new \Smalot\PdfParser\Parser()` | `extractPdfMetadata()` - duplicate of ExtractionService |

**Critical Finding:** Settings.php creates **9 separate `new MVDBService()` instances** across its AJAX handlers. None of these are shared or cached. Each creates a fresh Guzzle HTTP client.

---

## 2. Duplicate AJAX Handlers (Plugin.php vs Settings.php)

Both classes register handlers for the **same** WordPress AJAX hooks. WordPress `add_action` appends callbacks, so **both handlers fire** for each request - the last `wp_send_json_*` call wins, but the first handler's logic still executes wastefully.

| AJAX Hook | Plugin.php Method (line) | Settings.php Method (line) |
|-----------|-------------------------|---------------------------|
| `vector_bridge_validate_connection` | `handleValidateConnection` (108, 192) | `handleValidateConnection` (24, 458) |
| `vector_bridge_dry_run` | `handleDryRun` (109, 223) | `handleDryRun` (25, 485) |
| `vector_bridge_process_url` | `handleProcessUrl` (110, 271) | `handleProcessUrl` (26, 722) |
| `vector_bridge_upload_file` | `handleUploadFile` (113, 458) | `handleUploadFile` (27, 794) |
| `vector_bridge_get_jobs` | `handleGetJobs` (114, 518) | `handleGetJobs` (28, 876) |

**5 duplicate AJAX hook registrations total.**

### Behavioral Differences in Duplicates

The duplicate handlers are **not identical** - they have different implementations:

1. **`handleValidateConnection`**:
   - Plugin.php: uses `$this->getService('mvdb')` (shared instance), manual `wp_verify_nonce`
   - Settings.php: creates `new MVDBService()` (fresh instance), uses `check_ajax_referer`

2. **`handleDryRun`**:
   - Plugin.php: uses `$this->getService('extraction')` + `$this->getService('chunking')`, calls `$this->loadSampleFixtures()`, passes content through extraction service first
   - Settings.php: creates `new ChunkingService()`, uses inline fixtures, passes raw content directly

3. **`handleProcessUrl`**:
   - Plugin.php: schedules via WP cron (`wp_schedule_single_event`), requires collection param
   - Settings.php: processes synchronously (extract + chunk + index in-request), auto-generates post_type from domain

4. **`handleUploadFile`**:
   - Plugin.php: schedules via WP cron, requires collection param
   - Settings.php: processes synchronously, has file type validation (`pdf, docx, txt, md`), auto-generates post_type from extension

5. **`handleGetJobs`**:
   - Plugin.php: queries `_get_cron_array()` + `vector_bridge_job_history` option, returns formatted job list
   - Settings.php: returns empty `{ jobs: [] }` (placeholder)

---

## 3. Duplicate Code Patterns

### 3a. Nonce Verification (repeated 12 times)

**Pattern A** (Plugin.php - used 10 times, lines 194, 225, 273, 321, 384, 460, 520, 782, 850, 935):
```php
if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vector_bridge_nonce')) {
    wp_die('Security check failed');
}
```

**Pattern B** (Settings.php - used 10 times, lines 459, 486, 538, 562, 598, 647, 686, 723, 795, 877):
```php
check_ajax_referer('vector_bridge_nonce', 'nonce');
```

Two inconsistent approaches to the same security check. Pattern B is more correct (uses WordPress's built-in helper that handles both `$_POST` and `$_GET`).

### 3b. Capability Check (repeated 22 times)

Every AJAX handler has:
```php
if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions');
    // OR: wp_die(__('Insufficient permissions', 'vector-bridge-mvdb-indexer'));
}
```

Plugin.php uses unlocalized strings; Settings.php uses `__()` for i18n.

### 3c. Token Masking (duplicated across 2 files)

**Settings.php:353-359** (`maskToken`):
```php
private function maskToken(string $token): string {
    if (strlen($token) <= 6) {
        return str_repeat('*', strlen($token));
    }
    return substr($token, 0, 6) . str_repeat('*', strlen($token) - 6);
}
```

**MVDBService.php:702-708** (`maskToken`):
```php
private function maskToken(string $token): string {
    if (strlen($token) <= 6) {
        return str_repeat('*', strlen($token));
    }
    return substr($token, 0, 6) . str_repeat('*', strlen($token) - 6);
}
```

Identical implementations in two files with identical signatures.

### 3d. Timestamp Formatting (duplicated across 2 files)

**VttExtractionService.php:264-270** (`formatTimestamp`):
```php
private function formatTimestamp(float $seconds): string {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    return sprintf('%02d:%02d:%06.3f', $hours, $minutes, $secs);
}
```

**VideoContentBuilder.php:212-218** (`formatTimestamp`):
```php
private function formatTimestamp(float $seconds): string {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    return sprintf('%02d:%02d:%06.3f', $hours, $minutes, $secs);
}
```

Identical implementations.

### 3e. Timestamp Parsing (duplicated across 2 files)

**VttExtractionService.php:245-256** (`parseTimestamp`):
```php
private function parseTimestamp(string $timestamp): float { ... }
```

**VideoContentBuilder.php:238-249** (`parseTimestamp`):
```php
private function parseTimestamp(string $timestamp): float { ... }
```

Identical implementations.

### 3f. Title Extraction (duplicated across 4 files)

The logic for extracting a title from content (first line < 100 chars, fallback to truncation) appears in:

1. **MVDBService.php:729-742** - `extractTitle(array $chunk)`
2. **DefaultContentBuilder.php:117-140** - `extractTitle(array $chunk, array $metadata)`
3. **VideoContentBuilder.php:135-180** - `extractTitle(array $chunk, array $metadata)`
4. **DocumentContentBuilder.php:145-197** - `extractTitle(array $chunk, array $metadata)`
5. **WebpageContentBuilder.php:136-197** - `extractTitle(array $chunk, array $metadata)`

MVDBService has a private version that doesn't use the interface. The four ContentTypeBuilders all share a common "first line" extraction pattern but with type-specific additions.

### 3g. Error Response Formatting (repeated 20+ times)

```php
wp_send_json_error([
    'message' => __('...prefix: ', 'vector-bridge-mvdb-indexer') . $e->getMessage()
]);
```

vs

```php
wp_send_json_error([
    'message' => $e->getMessage()
]);
```

No consistent error response structure.

### 3h. PDF Parser Instantiation (duplicated across 2 files)

- **ExtractionService.php:68** - `new PdfParser()` in constructor
- **DocumentContentBuilder.php:307** - `new \Smalot\PdfParser\Parser()` in `extractPdfMetadata()`

### 3i. Content Normalization (duplicated across 3 files)

The pattern of normalizing line endings + removing excessive whitespace appears in:
- **ChunkingService.php:74-88** (`normalizeContent`)
- **ExtractionService.php:391-406** (`cleanText`)
- **DefaultContentBuilder.php:149-163** (`preprocessContent`)

---

## 4. Hook Registration Graph

### vector-bridge-mvdb-indexer.php (bootstrap)
```
plugins_loaded          -> vector_bridge_init_plugin()
register_activation     -> vector_bridge_activate_plugin()
register_deactivation   -> vector_bridge_deactivate_plugin()
admin_notices           -> vector_bridge_php_version_notice() (conditional)
admin_notices           -> vector_bridge_wp_version_notice() (conditional)
admin_notices           -> vector_bridge_composer_notice() (conditional)
admin_notices           -> vector_bridge_init_error_notice() (conditional)
```

### Plugin.php
```
admin_enqueue_scripts   -> Plugin::enqueueAdminAssets
wp_ajax_*               -> 12 AJAX handlers (see section 2 + content browser)
vector_bridge_process_content -> Plugin::processContent (cron)
vector_bridge_index_chunks    -> Plugin::indexChunks (cron)
plugin_action_links_*   -> Plugin::addSettingsLink
```

### Settings.php (via constructor)
```
admin_init              -> Settings::initSettings
wp_ajax_*               -> 10 AJAX handlers (5 overlapping with Plugin.php + 5 unique)
```

Settings.php unique AJAX hooks (not in Plugin.php):
- `vector_bridge_get_all_documents` -> `handleGetAllDocuments`
- `vector_bridge_search_documents` -> `handleSearchDocuments`
- `vector_bridge_get_document_details` -> `handleGetDocumentDetails`
- `vector_bridge_delete_document` -> `handleDeleteDocument`
- `vector_bridge_clear_all_documents` -> `handleClearAllDocuments`

### AdminMenu.php (via constructor)
```
admin_menu              -> AdminMenu::addMenuPages
```

### Total Hook Registrations

| Hook Type | Count |
|-----------|-------|
| `wp_ajax_*` (Plugin.php) | 12 |
| `wp_ajax_*` (Settings.php) | 10 |
| `wp_ajax_*` **DUPLICATED** | 5 |
| `wp_ajax_*` total unique | 17 |
| WP Cron hooks | 2 |
| Admin hooks | 3 (enqueue, init, menu) |
| Filter hooks | 1 (plugin_action_links) |

---

## 5. Tight Coupling Points

### 5a. Static `Settings::get()` calls (coupling to global state)

Every service reads configuration via `Settings::get()` directly:

- **MVDBService.php**: lines 54, 55, 103, 160, 573, 574, 750, 800 (8 calls)
- **ChunkingService.php**: lines 46, 47, 353, 354, 358, 370, 381, 382 (8 calls)
- **DefaultContentBuilder.php**: line 35
- **VideoContentBuilder.php**: line 31
- **DocumentContentBuilder.php**: line 31
- **WebpageContentBuilder.php**: line 31

Services cannot be instantiated with custom configuration. Testing requires a running WordPress environment with `get_option()` available.

### 5b. `get_site_url()` calls in service classes

Called directly in 4 content builders and MVDBService - tightly couples services to WordPress runtime.

### 5c. `current_time()` calls in service classes

Called in all content builders and ChunkingService - couples to WordPress timezone functions.

### 5d. WordPress i18n `__()` calls in service layer

`MVDBService.php`, `ChunkingService.php`, `ExtractionService.php`, `VttExtractionService.php` all use `__()` for error messages - couples services to WordPress i18n system.

### 5e. Singleton Pattern (Plugin.php)

`Plugin::getInstance()` makes the core class impossible to test in isolation. No way to inject mock services or override configuration.

### 5f. Direct `error_log()` calls

Used in MVDBService.php (lines 126, 251, 285, 328, 382, 434, 589, 607, 613, 616, 629, 640) and Plugin.php (lines 657, 697). No logging abstraction; cannot swap logger implementation.

### 5g. Settings.php uses Reflection to access private method

**Settings.php:664-668**:
```php
$reflection = new \ReflectionClass($mvdb_service);
$method = $reflection->getMethod('deleteDocument');
$method->setAccessible(true);
$result = $method->invoke($mvdb_service, $document_id);
```

Uses reflection to call `MVDBService::deleteDocument()` because it's `private`. This is a code smell indicating the API surface is insufficient.

### 5h. ContentTypeFactory uses `ExtractionServiceInterface` and `ChunkingServiceInterface`

These interfaces are referenced in `createExtractor()` and `createChunker()` (lines 53, 66) but **do not exist** as files in the codebase. The return types reference non-existent interfaces.

---

## 6. Plugin.php Method Categorization by Concern

### Routing / Hook Registration (should be in a HookManager or Router)
| Method | Line | Description |
|--------|------|-------------|
| `setupHooks()` | 103 | Registers all WP hooks and AJAX handlers |
| `addSettingsLink()` | 749 | Plugin action links filter |

### Service Container / DI (should be in a ServiceProvider)
| Method | Line | Description |
|--------|------|-------------|
| `registerServices()` | 90 | Creates and stores service instances |
| `getService()` | 767 | Service accessor (primitive DI container) |

### Admin / UI (should be in an AdminController)
| Method | Line | Description |
|--------|------|-------------|
| `initAdmin()` | 136 | Conditional admin initialization |
| `enqueueAdminAssets()` | 151 | CSS/JS enqueuing |

### AJAX Controllers - Indexing (should be in IndexController)
| Method | Line | Description |
|--------|------|-------------|
| `handleValidateConnection()` | 192 | Connection test AJAX |
| `handleDryRun()` | 223 | Dry run AJAX |
| `handleProcessUrl()` | 271 | URL processing AJAX |
| `handleProcessFile()` | 319 | File processing AJAX |
| `handleProcessVideo()` | 382 | Video processing AJAX |
| `handleUploadFile()` | 458 | File upload AJAX |
| `handleGetJobs()` | 518 | Job status AJAX |

### AJAX Controllers - Content Browser (should be in BrowserController)
| Method | Line | Description |
|--------|------|-------------|
| `handleGetCollections()` | 780 | Get collections AJAX |
| `handleGetCollectionContent()` | 810 | Get collection content AJAX |
| `handleTestQuery()` | 848 | Test query AJAX |
| `handleExportCollection()` | 888 | Export collection AJAX |
| `handleDeleteCollection()` | 934 | Delete collection AJAX |

### Service Logic / Business Logic (should NOT be in Plugin.php)
| Method | Line | Description |
|--------|------|-------------|
| `processContent()` | 584 | Content processing pipeline (cron callback) |
| `indexChunks()` | 669 | Chunk indexing pipeline (cron callback) |
| `logJobStatus()` | 711 | Job history persistence |
| `loadSampleFixtures()` | 972 | Test fixture data |

### Lifecycle (appropriate for Plugin.php)
| Method | Line | Description |
|--------|------|-------------|
| `getInstance()` | 52 | Singleton accessor |
| `init()` | 66 | Bootstrap orchestration |
| `__construct()` | 43 | Private constructor |
| `__clone()` | 990 | Clone prevention |
| `__wakeup()` | 995 | Deserialization prevention |

### Summary

Plugin.php has **26 methods** spanning **7 distinct concerns**. It acts as:
1. A singleton factory
2. A service container
3. An admin controller
4. An AJAX router for indexing
5. An AJAX router for content browsing
6. A cron job processor
7. A job status logger

---

## 7. Settings.php Method Categorization

### Settings Registration / Configuration (appropriate)
| Method | Line |
|--------|------|
| `initSettings()` | 43 |
| `registerSetting()` | 150 |
| `get()` | 368 |
| `update()` | 379 |

### Settings Rendering (appropriate)
| Method | Line |
|--------|------|
| `renderPage()` | 388 |
| `renderField()` | 207 |
| `renderTextField()` | 236 |
| `renderNumberField()` | 260 |
| `renderMvdbSectionDescription()` | 179 |
| `renderProcessingSectionDescription()` | 188 |
| `renderPerformanceSectionDescription()` | 197 |

### Sanitization (appropriate)
| Method | Line |
|--------|------|
| `sanitizeChunkSize()` | 290 |
| `sanitizeOverlapPercentage()` | 301 |
| `sanitizeBatchSize()` | 312 |
| `sanitizeQps()` | 323 |
| `sanitizeToken()` | 334 |

### Security Utility (should be shared)
| Method | Line |
|--------|------|
| `maskToken()` | 353 |

### AJAX Controllers (should be in dedicated controllers)
| Method | Line |
|--------|------|
| `handleValidateConnection()` | 458 |
| `handleDryRun()` | 485 |
| `handleProcessUrl()` | 722 |
| `handleUploadFile()` | 794 |
| `handleGetJobs()` | 876 |
| `handleGetAllDocuments()` | 537 |
| `handleSearchDocuments()` | 561 |
| `handleGetDocumentDetails()` | 597 |
| `handleDeleteDocument()` | 646 |
| `handleClearAllDocuments()` | 685 |

Settings.php has **27 methods** spanning **5 concerns**. 10 of those are AJAX handlers that don't belong.

---

## 8. Issue Summary & Severity

| # | Issue | Severity | Impact |
|---|-------|----------|--------|
| 1 | 5 duplicate AJAX registrations (both handlers fire) | **Critical** | Race conditions, double processing, unpredictable behavior |
| 2 | Settings.php creates 9+ fresh MVDBService instances per page load | **High** | Wasted resources, no connection pooling |
| 3 | Nonce verification inconsistency (manual vs `check_ajax_referer`) | **High** | Security inconsistency |
| 4 | Plugin.php is a 998-line God Object with 7 concerns | **High** | Untestable, hard to maintain |
| 5 | All services tightly coupled to `Settings::get()` static calls | **High** | Cannot test without WordPress, cannot configure per-instance |
| 6 | Token masking duplicated in 2 files | **Medium** | Maintenance burden |
| 7 | Timestamp formatting/parsing duplicated in 2 files | **Medium** | Maintenance burden |
| 8 | Title extraction logic duplicated in 5 classes | **Medium** | Divergent behavior risk |
| 9 | Content normalization duplicated in 3 classes | **Medium** | Inconsistent cleaning |
| 10 | Reflection hack to call private `deleteDocument()` | **Medium** | Fragile, breaks if method renamed |
| 11 | Non-existent interfaces referenced (`ExtractionServiceInterface`, `ChunkingServiceInterface`) | **Low** | Dead code / incomplete feature |
| 12 | No error response standardization | **Low** | Inconsistent API surface |
| 13 | `error_log()` calls with no logging abstraction | **Low** | Cannot swap logging strategy |
