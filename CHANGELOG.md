# Changelog

## [3.1.0] - 2026-10-06

### Added

- A connection-level overview and SQL workspace, with clearer connection context and query status.
- Grouped transfer, schema, and destructive actions with clearer warnings and empty states.

### Changed

- SQL history is scoped to its connection and cleared from the active view when switching connections.
- Exports are capped at 10,000 rows by default. The cap is configurable through `max_export_rows`.
- CSV imports process rows in 500-row chunks to keep memory usage bounded.
- The development lockfile now uses Filament 5.8.2 and CommonMark 2.10.3, resolving the advisories found before release.

### Security

- Read-only SQL runs under database read-only controls on supported drivers, with a configurable server-side timeout on PostgreSQL, MySQL, and MariaDB.
- The SQL runner is disabled when table visibility restrictions are configured.
- CSV exports prefix common spreadsheet formula cells so Excel opens them as text. Use JSON or SQL export when exact values are required.

[3.1.0]: https://github.com/Crumbls/filament-database/compare/3.0.1...3.1.0
