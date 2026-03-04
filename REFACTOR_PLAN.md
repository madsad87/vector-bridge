# Vector Bridge MVDB Indexer — Strategic Refactoring Plan

---

## Project Goal

**Refactor Vector Bridge from a working-but-fragile monolith into a maintainable, single-responsibility architecture — without changing what the plugin does.** The current codebase has duplicate AJAX handlers that cause double execution, a 999-line god class mixing 7 concerns, and services instantiated redundantly up to 9 times per request. The refactored plugin will have one handler per endpoint, one class per concern, and shared service instances — making it safe to extend, debug, and eventually test.

**"Done" means:** Every AJAX endpoint, settings page, cron job, and content pipeline works identically to today, but the code behind it is structured so that a developer can find, understand, and modify any feature without risk of breaking unrelated functionality.

**Value delivered:**
- **Reliability** — Eliminates race conditions from duplicate handlers firing on every AJAX request
- **Maintainability** — Any feature change touches 1 file instead of 3-4
- **Extensibility** — Service interfaces and DI make it possible to swap implementations, add new content types, or write tests without WordPress bootstrapping

---

## Document Purpose

This plan defines **what** to fix, **why** it matters, and **in what order** — bridging the Researcher's findings (REFACTOR_ANALYSIS.md) and the Architect's technical blueprint (REFACTOR_ARCHITECTURE.md). It does not prescribe implementation details; the Architect owns the HOW.

---

## 1. Objectives (Ranked by Priority)

### P0 — Critical: Eliminate Duplicate AJAX Handlers

**What:** 5 AJAX hooks are registered by both Plugin.php and Settings.php with diverging implementations. WordPress fires both callbacks — the first executes wastefully and the second's response wins.

**Why this is #1:** This is the only issue that causes **incorrect runtime behavior today**. It wastes server resources (double execution), creates unpredictable outcomes (which handler's logic "wins" depends on registration order), and makes debugging nearly impossible since developers don't know which implementation is authoritative.

**Success Criteria:**
- Zero duplicate `add_action('wp_ajax_*')` registrations across the entire codebase
- Each AJAX endpoint has exactly one handler with one clear implementation
- Behavioral intent from both versions is reconciled (see Decision Points below)

---

### P1 — High: Decompose Plugin.php God Object

**What:** Plugin.php is 999 lines spanning 7 concerns (singleton factory, service container, admin controller, indexing AJAX router, content browser AJAX router, cron processor, job logger). It has 26 methods.

**Why:** A God Object this large is the root cause of most other issues. Developers can't find what they need, changes to one concern risk breaking others, and the file is untestable. This is the structural fix that makes everything else sustainable.

**Success Criteria:**
- Plugin.php under 80 lines, responsible only for bootstrap
- Each extracted concern lives in a single-purpose class
- No business logic or AJAX handling remains in Plugin.php

---

### P2 — High: Eliminate Service Re-instantiation

**What:** Settings.php creates 9+ fresh `MVDBService()` instances across its AJAX handlers. Each one spins up a new Guzzle HTTP client. No instance sharing or caching.

**Why:** Unnecessary resource waste on every AJAX request. When P0 consolidates handlers, this gets solved naturally — but only if the new architecture enforces shared service instances via a container.

**Success Criteria:**
- Each service (MVDBService, ChunkingService, ExtractionService) instantiated at most once per request
- Services accessed through a container/provider, never via `new` in handler code

---

### P3 — High: Standardize Security Patterns

**What:** Nonce verification uses two inconsistent approaches (manual `wp_verify_nonce` vs `check_ajax_referer`). Capability checks are copy-pasted 22 times with inconsistent i18n.

**Why:** Security inconsistency is a vulnerability vector. Manual nonce checking misses edge cases that `check_ajax_referer` handles. The duplication means a security fix must be applied in 22 places.

**Success Criteria:**
- All AJAX handlers use a single shared security verification method
- `check_ajax_referer` used everywhere (WordPress best practice)
- Capability check appears in exactly one place (base class or middleware)

---

### P4 — Medium: Remove Settings.php AJAX Handlers

**What:** Settings.php contains 10 AJAX handlers (5 duplicates of Plugin.php, 5 unique document-management handlers). These don't belong in a settings/configuration class.

**Why:** Separation of concerns. Settings.php should manage settings registration, rendering, and sanitization — not HTTP request handling. This is tightly coupled to P0 (the 5 duplicates) but the 5 unique handlers also need a new home.

**Success Criteria:**
- Settings.php contains zero AJAX handlers
- Settings.php reduced to ~350 lines (settings registration, rendering, sanitization only)
- The 5 unique document-management handlers live in an appropriate controller

---

### P5 — Medium: Consolidate Duplicate Utility Code

**What:** Multiple identical implementations scattered across files:
- `maskToken()` — 2 files (Settings.php, MVDBService.php)
- `formatTimestamp()` / `parseTimestamp()` — 2 files each (VttExtractionService, VideoContentBuilder)
- Content normalization — 3 files (ChunkingService, ExtractionService, DefaultContentBuilder)
- Title extraction — 5 classes with overlapping logic

**Why:** Duplicate code means bugs get fixed in one place but not others. The timestamp and normalization duplication are maintenance landmines.

**Success Criteria:**
- `maskToken`, `formatTimestamp`, `parseTimestamp`, `normalizeContent` each exist in exactly one location
- All call sites reference the shared implementation
- Title extraction: MVDBService's private `extractTitle()` removed (ContentTypeBuilders handle this)

---

### P6 — Medium: Fix Broken Interface References

**What:** ContentTypeFactory references `ExtractionServiceInterface` and `ChunkingServiceInterface` in return types, but these interfaces don't exist as files.

**Why:** This is dead code that will cause fatal errors if the `createExtractor()` or `createChunker()` methods are ever called. It also signals incomplete design intent that should be fulfilled.

**Success Criteria:**
- All referenced interfaces exist as files
- Services implement their respective interfaces
- No return type references to non-existent classes

---

### P7 — Medium: Eliminate Reflection Hack

**What:** Settings.php uses `ReflectionClass` to call `MVDBService::deleteDocument()` because it's `private`.

**Why:** Reflection bypasses visibility for a reason — if the method needs to be called externally, it should be public. This is fragile (breaks on rename) and signals an incomplete public API.

**Success Criteria:**
- `deleteDocument()` is part of the public service interface
- Zero uses of `ReflectionClass` in the codebase for accessing private methods

---

### P8 — Low: Standardize Error Responses

**What:** 20+ AJAX handlers return errors in inconsistent formats — some prefix error messages with context, some don't; some use i18n, some don't.

**Why:** Makes frontend error handling unreliable and creates inconsistent UX. Low priority because it's cosmetic — nothing breaks.

**Success Criteria:**
- All AJAX error responses use a consistent format
- Error messages use i18n consistently

---

### P9 — Low: Decouple Services from WordPress Globals

**What:** Services call `Settings::get()` (static), `get_site_url()`, `current_time()`, `__()`, and `error_log()` directly.

**Why:** Makes services impossible to unit test without WordPress loaded. Important for long-term maintainability but not causing issues today.

**Success Criteria (this round — partial):**
- Services receive configuration via constructor injection, not static calls
- `error_log()` calls routed through a simple logging wrapper (can be a static helper — no need for full abstraction)

**Deferred:** Full decoupling from `get_site_url()`, `current_time()`, `__()` is out of scope for this round.

---

## 2. Scope

### In Scope (This Round)

| Item | Objectives Served |
|------|-------------------|
| Consolidate duplicate AJAX handlers into single implementations | P0, P4 |
| Decompose Plugin.php into bootstrap + separate classes | P1 |
| Introduce ServiceProvider for shared service instances | P2 |
| Create AbstractController with shared security verification | P3 |
| Move all AJAX handlers out of Settings.php | P4 |
| Extract shared utilities (maskToken, timestamps, normalization) | P5 |
| Create service interfaces (MVDB, Chunking, Extraction) | P6, P7 |
| Make `deleteDocument()` public via interface | P7 |

### Out of Scope (Deferred)

| Item | Reason |
|------|--------|
| Full WordPress decoupling of services | Diminishing returns for a WP plugin; services will always run in WP context |
| PSR-11 / framework DI container | Over-engineering for ~10 services |
| Logging abstraction layer | Current `error_log()` usage is adequate; can be addressed later |
| Template extraction for admin HTML | WordPress-standard inline HTML; extracting adds complexity without value |
| Automated test suite creation | Valuable but separate initiative; this refactor makes testing *possible* |
| Error response standardization | Low impact; can be done as a follow-up pass |
| Frontend/JS refactoring | Not surfaced as problematic; out of scope |

---

## 3. Risks & Mitigation

### Risk 1: AJAX Behavioral Divergence (HIGH)

**Risk:** The 5 duplicate AJAX handlers have different implementations. Consolidating to one means choosing which behavior to keep. The wrong choice could break existing workflows.

**Mitigation:**
- The decision matrix in Section 6 (Decision Points) captures each divergence
- User must approve behavioral choices before implementation begins
- Each consolidated handler should be tested against the frontend JS to verify expected behavior

### Risk 2: Hook Registration Order Changes (MEDIUM)

**Risk:** WordPress hook execution order depends on registration order and priority. Moving hooks from Plugin.php/Settings.php constructors into HookManager changes when callbacks are registered relative to other plugins/themes.

**Mitigation:**
- All hooks should be registered at the same WordPress lifecycle point (`plugins_loaded`) as they are today
- Explicitly set priority values where order matters
- Verify no third-party code depends on Vector Bridge's hook registration order (unlikely for AJAX hooks)

### Risk 3: Singleton Dependency (MEDIUM)

**Risk:** External code (other plugins, theme functions) might call `Plugin::getInstance()->getService()` or similar. Changing Plugin.php's public API could break integrations.

**Mitigation:**
- Maintain `Plugin::getInstance()` with a `getContainer()` method
- Keep `getService()` as a deprecated proxy if needed (check if anything external calls it)
- This is a private plugin, so external dependency risk is low

### Risk 4: Autoloader / Namespace Changes (LOW)

**Risk:** Moving files to new directories could break the PSR-4 autoloader if `composer.json` isn't updated.

**Mitigation:**
- Update `composer.json` autoload map as part of the refactoring
- Run `composer dump-autoload` after each phase
- Test plugin activation after every structural change

### Risk 5: Cron Job Continuity (LOW)

**Risk:** WordPress cron stores callback references (class + method). If the cron callback moves from `Plugin::processContent` to `CronManager::processContent`, scheduled jobs registered before the refactor will fail silently.

**Mitigation:**
- On plugin update, flush and re-register cron hooks
- Or: keep a thin proxy in Plugin.php that delegates to CronManager (temporary backwards compat)

---

## 4. Phasing Strategy

### Phase 1: Extract & Consolidate Controllers
**Objectives:** P0, P1, P2, P3, P4
**Risk:** Medium (behavioral choices needed)

This is the big phase. It tackles the most impactful structural changes:

1. Create `AbstractController` with `verifyRequest()` (P3)
2. Create all 4 controllers, moving AJAX logic out of Plugin.php and Settings.php (P0, P1, P4)
3. Create `ServiceProvider` for shared instances (P2)
4. Create `HookManager` for centralized hook registration (P0, P1)
5. Create `AssetManager` from Plugin.php's `enqueueAdminAssets()` (P1)
6. Create `CronManager` from Plugin.php's cron methods (P1)
7. Rewrite Plugin.php as bootstrap-only (P1)
8. Strip AJAX handlers from Settings.php (P4)

**Verification gate:** All 17 unique AJAX endpoints respond correctly. Settings page renders. Cron jobs fire. No PHP errors.

### Phase 2: Interfaces, Utilities & Cleanup
**Objectives:** P5, P6, P7
**Risk:** Low (no behavioral changes)

1. Create service interfaces in `Services/Contracts/` (P6)
2. Add `implements` to service classes (P6)
3. Make `deleteDocument()` public (P7)
4. Create `Support/Validation.php` with shared utilities (P5)
5. Replace duplicate `maskToken`, timestamp, normalization calls with shared versions (P5)
6. Remove MVDBService's private `extractTitle()` (P5)

**Verification gate:** All endpoints still work. No PHP errors. `grep -r "new MVDBService\|new ChunkingService\|new ExtractionService"` shows zero results outside ServiceProvider.

---

## 5. Architecture Assessment

The REFACTOR_ARCHITECTURE.md plan is **well-aligned** with these objectives. Specific assessment:

### Strengths

- **File tree is clean and logical.** The `Core/`, `Controllers/`, `Services/Contracts/`, `Cron/`, `Support/` structure matches the concerns identified by the Researcher.
- **AbstractController is the right pattern.** Eliminates 22+ duplicated security checks with zero abstraction overhead.
- **ServiceProvider is appropriately simple.** Closure-based lazy loading without a framework — right-sized for ~10 services.
- **HookManager centralizes registration.** Single source of truth for all hook wiring eliminates the duplicate registration problem entirely.
- **Key decisions are pragmatic.** No framework DI, no template extraction, keeping ContentTypes as-is — all correct calls that avoid over-engineering.

### Gaps / Concerns

1. **AJAX behavioral reconciliation is not addressed.** The architecture shows the target structure but doesn't specify which duplicate handler's behavior wins. The `handleDryRun` implementations are materially different (sample fixtures vs inline fixtures, extraction pipeline vs raw content). This needs explicit decision before implementation. See Decision Points below.

2. **HookManager eagerly resolves services.** In the architecture doc, `HookManager::register()` calls `$this->container->get('controller.connection')` etc. at registration time. This means ALL controllers and services are instantiated on every admin page load — not just when AJAX fires. The lazy factory pattern in ServiceProvider is defeated. **Recommendation:** Register hooks with closures that resolve from the container only when the hook fires: `add_action('wp_ajax_...', fn() => $this->container->get('controller.connection')->validateConnection())`.

3. **Controllers receive the full container.** The architecture passes `ServiceProvider` to controllers. This is a service locator pattern — it works, but it hides dependencies and makes it unclear what each controller actually needs. For this plugin's size, it's acceptable, but worth noting. No change recommended for this round.

4. **Missing: `addSettingsLink` ownership.** The architecture assigns `addSettingsLink` to Settings.php in HookManager, but this is a Plugin-level concern (it adds a "Settings" link to the plugins list page). Minor — either location works.

5. **Validation.php scope creep potential.** The architecture puts `maskToken`, `normalizeContent`, `formatTimestamp`, `parseTimestamp`, URL validation, file validation, and clamping all in one static class. This is fine now but should not grow into a junk drawer. If it exceeds ~15 methods, reconsider organization.

---

## 6. Decision Points (Requires User Input)

These behavioral divergences between duplicate handlers cannot be resolved by the Architect alone. The user (or product owner) must decide which behavior is correct.

### Decision 1: `handleProcessUrl` — Sync vs Cron

| | Plugin.php | Settings.php |
|---|---|---|
| **Processing** | Schedules via `wp_schedule_single_event` (async cron) | Processes synchronously in-request |
| **Collection** | Required parameter from user | Auto-generated from URL domain |

**Options:**
- **A) Keep cron-based (Plugin.php approach):** Better for large content; doesn't block the HTTP request. Requires the user to specify a collection name.
- **B) Keep synchronous (Settings.php approach):** Simpler, immediate feedback. Could timeout on large pages.
- **C) Hybrid:** Synchronous for small content, cron for large content. More complex.

**Recommendation:** Option A (cron-based) — it's more robust and already handles the job tracking UI.

### Decision 2: `handleUploadFile` — Sync vs Cron

Same pattern as Decision 1. Plugin.php uses cron; Settings.php processes synchronously.

**Recommendation:** Option A (cron-based), same reasoning.

### Decision 3: `handleDryRun` — Extraction Pipeline

| | Plugin.php | Settings.php |
|---|---|---|
| **Content handling** | Passes content through ExtractionService first, then chunks | Chunks raw content directly |
| **Fixtures** | `loadSampleFixtures()` method | Inline fixture array |

**Options:**
- **A) With extraction pipeline:** More realistic preview — shows what chunking looks like after content is cleaned/extracted.
- **B) Without extraction:** Faster, simpler, but less representative of actual processing.

**Recommendation:** Option A — dry run should mirror actual processing as closely as possible.

### Decision 4: `handleGetJobs` — Real vs Placeholder

Plugin.php queries `_get_cron_array()` + job history option. Settings.php returns empty `{ jobs: [] }`.

**Decision:** This is obvious — use Plugin.php's real implementation. Settings.php's version is a placeholder.

### Decision 5: `handleValidateConnection` — Behavior is functionally equivalent

Both test the MVDB connection. Plugin.php uses `getService('mvdb')`; Settings.php creates `new MVDBService()`. The consolidated version will use the container — no behavioral choice needed.

**Decision:** No user input needed. Use container-provided instance.

---

## 7. Summary

| Phase | Objectives | Risk | Estimated Scope |
|-------|-----------|------|-----------------|
| Phase 1 | P0, P1, P2, P3, P4 | Medium | 8-10 new/modified files |
| Phase 2 | P5, P6, P7 | Low | 5-7 new/modified files |

**Blocking dependency:** Decisions 1-3 in Section 6 must be resolved before Phase 1 implementation begins.

**Key principle:** This refactoring changes **structure**, not **behavior** (except where duplicate handlers are consolidated). After refactoring, the plugin should do exactly the same things it does today — just from a clean, maintainable architecture.
