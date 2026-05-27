# JCORE Update API Hooks — Implementation Plan

## 1) Project goal

Build a **Composer-installable PHP library** that lets WordPress plugins receive update information from the **JCORE Update API** instead of WordPress.org.

The library should be:

- **Self-contained** (easy to drop into any plugin)
- **Minimal integration effort** (plugin passes slug + version, and `license_key` only when plugin is paid)
- **WordPress-native** (uses core update hooks and transients)
- **Extensible** (supports both free and paid plugins)

Reference API project: https://github.com/JCO-Digital/update-api

---

## 2) Scope

### In scope

- Querying JCORE API for update metadata
- Injecting update info into WordPress update UI/flow
- Optional license-aware requests for paid plugins
- Public method to validate license keys via `POST /v1/licenses/validate`
- Plugin details modal support (`View details`)
- Cache/transient strategy to avoid excessive API calls
- Basic error handling and fail-safe behavior
- Test coverage for core update hook behavior

### Out of scope (for v1)

- Theme updates
- License settings / activation / validation UI (handled by host plugin)
- Telemetry/analytics dashboards
- Multi-API fallback system

---

## 3) Proposed package shape

### Suggested package metadata

- Composer name: `jcodigital/jcore-update`
- PHP target: `>=8.2`
- Type: `library`
- PSR-4 namespace: `Jcore\Update\`

### Directory structure

```text
src/
  Client/
    UpdateApiClient.php
  Config/
    UpdateConfig.php
  Hooks/
    PluginUpdateHooks.php
  Licensing/
    LicenseProviderInterface.php
    LicenseValidationResult.php
  Support/
    LoggerInterface.php (optional, or PSR-3)
  ValueObject/
    PluginIdentity.php
    UpdatePayload.php
    PluginInfoPayload.php
tests/
  Unit/
  Integration/
PLAN.md
README.md
composer.json
```

---

## 4) Public API design (consumer-facing)

### 4.1 Primary integration (hooks + validation)

```php
use Jcore\Update\Config\UpdateConfig;
use Jcore\Update\Hooks\PluginUpdateHooks;

$config = new UpdateConfig(
    pluginFile: __FILE__,
    slug: 'my-plugin-slug',
    version: '1.2.3',
    apiBaseUrl: 'https://api.example.com/v1',
    licenseKey: $storedLicenseKey // only required for paid plugins
);

$updater = new PluginUpdateHooks($config);
$updater->register();

// Host plugin UI flow (settings page, AJAX action, etc.)
$result = $updater->validateLicense($userInputLicense);
if ($result->isSuccess() && $result->valid) {
    // mark active in your own plugin UI/state
}
```

### 4.2 Exact method contract (v1)

`PluginUpdateHooks` will expose:

```php
public function register(): void;
public function unregister(): void;

public function validateLicense(string $licenseKey, bool $forceRefresh = false): LicenseValidationResult;
public function isLicenseValid(string $licenseKey, bool $forceRefresh = false): bool;
```

Return type:

```php
final class LicenseValidationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly bool $success,
        public readonly bool $fromCache,
        public readonly ?string $errorCode = null,
        public readonly ?string $message = null,
    ) {}

    public function isSuccess(): bool { return $this->success; }
}
```

`errorCode` values (initial set):

- `transport_error`
- `http_error`
- `invalid_json`
- `invalid_payload`
- `rate_limited`

### 4.3 Behavior rules for validation methods

- `validateLicense()` calls `POST /v1/licenses/validate` with `slug` and `license_key`
- `isLicenseValid()` is a convenience wrapper that returns only `$result->valid`
- If request succeeds and API returns `{ "valid": true|false }`, then:
  - `success = true`
  - `valid` reflects API value
- If request fails/parsing fails/rate limited:
  - `success = false`
  - `valid = false` (fail closed)
  - `errorCode` populated
- No UI side effects (no notices/forms/screens); host plugin owns UX completely

### 4.4 Configuration contract

Minimal required config:

- `pluginFile` (full plugin bootstrap path, used for plugin basename)
- `slug`
- `version`
- `apiBaseUrl` (expected base includes `/v1`)

Optional config:

- `licenseKey` (required by API only when plugin is `is_paid`)
- `requestTimeout`
- `updateCacheTtl`
- `licenseValidationCacheTtl`
- custom HTTP args/filter callbacks
- logger

---

## 5) WordPress hooks to implement

### 5.1 Update check injection

- Hook: `pre_set_site_transient_update_plugins`
- Behavior:
  - Read checked plugin versions from transient
  - Identify current plugin
  - Call JCORE update endpoint
  - If newer version exists, inject into `$transient->response[$pluginBasename]`
  - If no update, ensure plugin appears in `$transient->no_update[$pluginBasename]` when useful

### 5.2 Plugin details modal (`View details`)

- Hook: `plugins_api`
- Behavior:
  - Intercept action `plugin_information` for matching slug
  - Reuse `GET /v1/update-check` request shape (`slug`, `version`, optional `license_key`)
  - Return object compatible with WordPress plugin info expectations when API returns update data
  - If API returns no content, keep default WordPress behavior/fallback unchanged

### 5.3 Post-update housekeeping (optional in v1)

- Hook: `upgrader_process_complete`
- Behavior:
  - Clear relevant transients/cache
  - Optional action hook for host plugin follow-up

---

## 6) API contract mapping (from `update-api` docs)

### 6.1 Update check endpoint

- `GET /v1/update-check`
- Query params:
  - `slug` (required)
  - `version` (required)
  - `license_key` (required only for paid plugins)

Response behavior:

- `200 OK`: JSON object compatible with WordPress plugin update/info expectations when a newer version exists
- `204 No Content`: no update available (installed version is current or newer)

Known fields from WordPress integration example:

- `new_version`
- `tested`
- `package` (download URL)
- `url`
- `requires`
- `requires_php`
- `sections` (for plugin details modal)

### 6.2 License validation endpoint

- `POST /v1/licenses/validate`
- Body:
  - `slug`
  - `license_key`
- Response (`200 OK`):
  - `{ "valid": true|false }`
- Rate limit:
  - 6 requests per minute per IP (per API docs)

Design note:

- Keep strict adapter layers (`UpdateApiResponseMapper`, `LicenseValidationMapper`) so hook logic depends on internal DTOs, not raw HTTP payloads.
- Treat unknown/optional keys as additive and ignore safely unless mapped explicitly.

---

## 7) Caching and performance plan

- Cache update responses in site transients keyed by:
  - slug
  - installed version
  - license hash presence/state (not raw key)
- Default TTL for update checks: 6–12 hours (configurable)
- Optional short-lived cache for license validation results (e.g. 5–15 minutes) keyed by slug + license hash, to respect `/licenses/validate` rate limits
- Bypass cache when WordPress explicitly forces update checks
- Avoid remote calls in admin screens unrelated to updates where possible

---

## 8) Security and privacy

- Never log raw license keys
- `license_key` is sent as a query parameter for `GET /v1/update-check`; redact it in any logs/debug output
- Add timeout + graceful failure behavior for remote calls
- Sanitize and validate remote payload before injecting into WP objects
- Use HTTPS-only API base URL in production docs

---

## 9) Failure behavior

If API fails, times out, or returns invalid data:

- Do **not** break WordPress update screen
- Return original transient/plugins API response untouched
- Treat `204 No Content` as a valid **no update** outcome (not an error)
- Optionally log debug context (with redacted license data)
- Add internal action/filter for host plugin observability

---

## 10) Testing strategy

### Unit tests

- Version comparison and update eligibility
- Response mapping (API → WP object)
- License validation response mapping (`valid`, `success`, `errorCode`) and fail-closed semantics
- Cache key generation and TTL behavior
- Error/fallback paths

### Integration tests (WP test environment)

- `pre_set_site_transient_update_plugins` integration
- `plugins_api` details response integration
- Paid plugin request path with mock license provider
- `validateLicense()` request/response path, including rate-limit-safe caching behavior

### Manual QA checklist

- Fresh install with outdated version shows update
- No update available scenario (`204 No Content`)
- Invalid license scenario (paid plugin)
- Expired/unreachable API scenario
- Update + plugin info modal render correctly

---

## 11) Milestones

### Milestone 1 — Foundation

- Composer package skeleton
- Config object + HTTP client abstraction
- Basic logging and exception strategy

### Milestone 2 — Core update hook

- Implement `pre_set_site_transient_update_plugins`
- API adapter + mapping
- Transient caching

### Milestone 3 — Plugin details modal

- Implement `plugins_api` integration
- Rich sections/changelog mapping

### Milestone 4 — Paid plugin support

- License-aware update requests
- Exposed `validateLicense()` typed helper + `isLicenseValid()` convenience wrapper using `POST /v1/licenses/validate`
- License provider interface for host plugin ownership
- Headless boundary: UI remains in host plugin

### Milestone 5 — Hardening

- Test suite, docs, examples
- Backward compatibility checks across target WP/PHP versions

---

## 12) Deliverables

- Composer library with semantic versioning
- README with quickstart + advanced configuration
- Example integration snippet for a plugin bootstrap file
- CHANGELOG
- Initial release tag (`v0.1.0` or `v1.0.0`, depending on readiness)

---

## 13) Open decisions

1. Minimum supported PHP and WordPress versions?
2. For `plugins_api` modal: what should we show when update endpoint returns `204` (strict passthrough vs cached last-known metadata)?
3. Should this library include a built-in settings UI, or stay headless? _(current direction: stay headless)_
4. Should we expose PSR-3 logger support as a hard dependency or optional?

---

## 14) Definition of done (v1)

- Plugin author can install via Composer and register updater with <10 lines of code
- WordPress update UI correctly shows available updates from JCORE API
- Plugin details modal works from custom API data
- Paid plugin flow supports license-aware responses
- Library exposes headless license validation API (`validateLicense()` + `isLicenseValid()`) backed by `/v1/licenses/validate` for host plugin UI flows
- Network/API failures fail safely
- Automated tests cover core paths
- README is sufficient for first-time integration
