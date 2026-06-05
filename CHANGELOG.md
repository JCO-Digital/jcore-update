# Changelog

## v1.2.0 (2026-06-05)

#### Features

- ci: add support for pushing build artifacts to a distribution repository (2a482a6)

#### Build System

- composer: lower minimum php requirement to 8.1 (3882910)

#### Continuous Integration

- github: use current branch for release push operations (de20c03)
- github: allow pnpm version to be configured in reusable workflow (8ca90e1)
- github: exclude node_modules from plugin publish sync (e544a67)
- github: support custom excludes via .distignore in publishing workflow (b4f28f8)
- github: allow dist repo publish step to handle missing ssh key gracefully (e1dda6d)
- github: pin pnpm version to 11 in reusable plugin publish workflow (078f4e8)
- github: improve validation and hostname parsing for dist repo publishing (507780e)

## v1.1.0 (2026-05-28)

#### Features

- support: add PluginHelper to retrieve version from plugin header (8b70a90)

#### Continuous Integration

- github: add optional build step to reusable publish workflow (3f89699)
- github: automate build and zip steps in publish workflow (426717d)

#### Maintenance

- config: add foonver configuration file (6419c3c)

### v1.0.1 (2026-05-28)

#### Refactor

- hooks: remove custom transient caching (329bb57)

#### Continuous Integration

- github: add reusable workflow for publishing plugins (cec5c44)

#### Maintenance

- phpunit: remove cached test results (06f8347)

## v1.0.0 (2026-05-27)

#### Features

- init: implement JCORE Update API client and WordPress hooks (acbac1d)

#### Documentation

- tests: add docblocks and PHPDoc to test files and bootstrap (a8935f2)

#### Tests

- core: add PHPUnit testing framework and test suite (448e904)

#### Maintenance

- gitignore: ignore phpunit cache directory (0e3f595)

