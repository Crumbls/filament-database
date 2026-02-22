# Filament Database

A phpMyAdmin-style database manager for [Laravel Filament](https://filamentphp.com). Browse tables, edit rows, manage schema, run SQL — all from your Filament admin panel.

Supports **SQLite**, **MySQL**, and **PostgreSQL**.

## Requirements

- PHP 8.2+
- Laravel 12+
- Filament 4+ (5 recommended)

## Installation

```bash
composer require crumbls/filament-database
```

## Setup

Register the plugin in your Filament panel provider:

```php
use Crumbls\FilamentDatabase\FilamentDatabasePlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            FilamentDatabasePlugin::make(),
        ]);
}
```

## Configuration

Publish the config:

```bash
php artisan vendor:publish --tag=filament-database-config
```

### Options

- **`connections`** — `null` (all) or array of allowed connection names
- **`read_only`** — Block all write operations
- **`rows_per_page`** — Default 25
- **`query_runner`** — Enable/disable the SQL tab
- **`navigation`** — Customize group, icon, sort order

### Plugin API

```php
FilamentDatabasePlugin::make()
    ->readOnly()                           // No writes
    ->connections(['mysql', 'sqlite'])     // Whitelist connections
```

## Features

- **Connection switcher** — hot-swap between database connections
- **Table browser** — list all tables with one-click selection
- **Row CRUD** — paginated rows, inline edit, insert, delete
- **Structure viewer** — columns with types, nullable, defaults
- **Column management** — add and drop columns
- **Index viewer** — all indexes with columns and uniqueness
- **Foreign key viewer** — constraints with on update/delete actions
- **SQL runner** — execute raw queries with tabular results
- **Table operations** — create, truncate, drop tables
- **Dark mode** — full support via Filament theming

## Security

⚠️ This package gives direct database access. Protect it:

- Use Filament's built-in authorization (`canAccess()`)
- Enable `read_only` in production
- Restrict `connections` to what's needed
- Consider a Shield/policy gate

## License

MIT
