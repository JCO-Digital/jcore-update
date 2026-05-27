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

$config = new UpdateConfig(
    pluginFile: __FILE__,
    slug: 'my-plugin-slug',
    version: '1.0.0',
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
