# jcore-update

Composer-installable WordPress library for integrating plugin updates with the JCORE Update API.

## Install

```bash
composer require jcodigital/jcore-update
```

## Quickstart

```php
use Jcore\Update\Config\UpdateConfig;
use Jcore\Update\Hooks\PluginUpdateHooks;
use Jcore\Update\Support\PluginHelper;

$config = new UpdateConfig(
    pluginFile: __FILE__,
    slug: 'my-plugin-slug',
    version: PluginHelper::getVersion(__FILE__),
    apiBaseUrl: 'https://api.example.com/v1',
    licenseKey: get_option('my_plugin_license_key') ?: null,
);

$updater = new PluginUpdateHooks($config);
$updater->register();
```

## Full example plugin bootstrap

See `examples/wordpress-plugin-bootstrap.php` for a complete example including:

- updater registration
- license option storage
- settings page form
- `validateLicense()` usage in a sanitize callback

## License validation (headless)

This library validates keys against `POST /v1/licenses/validate` but intentionally does not render any UI.

```php
$result = $updater->validateLicense($licenseKey);

if ($result->isSuccess() && $result->valid) {
    // persist valid state in your plugin
} else {
    // show error/notice in your plugin UI
}
```

Convenience helper:

```php
$isValid = $updater->isLicenseValid($licenseKey);
```

## Semantic Versioning & Major Update Protection

By default, `jcore-update` protects against unintended breaking changes by filtering out major version updates from WordPress's automatic update manifest:

- If you are on `1.2.0`, minor and patch releases (`1.x`) update as normal.
- Major releases (`2.x`) will not show up in the manifest automatically.
- When a major version is available, an inline notice with an override button (**"Allow upgrade to v2.x"**) appears on the Plugins page.
- Clicking this button performs a one-time bump allowing updates for that target major version (e.g., `2.x`), while continuing to block subsequent major versions (e.g., `3.x`).
- Once updated to `2.x`, future `2.x` updates proceed as normal.

### Disabling Major Version Filtering

If you want all updates (including major versions) to be offered directly without requiring user confirmation:

```php
$config = new UpdateConfig(
    pluginFile: __FILE__,
    slug: 'my-plugin-slug',
    version: PluginHelper::getVersion(__FILE__),
    apiBaseUrl: 'https://api.example.com/v1',
    filterMajorUpdates: false,
);
```

## Notes

- `GET /v1/update-check` is used for update checks and plugin detail payloads.
- `204 No Content` is treated as a valid “no update” state.
- License keys are never stored in cache keys directly (hashes are used).
- UI elements (settings pages, admin notices, forms) remain the host plugin’s responsibility.

## Development

### Code Quality

Run `phpcs` to check coding standards:

```bash
composer lint
```

To automatically fix most coding standard issues:

```bash
composer fix
```

### Testing

The library uses PHPUnit for testing. Run the tests with:

```bash
composer test
```
