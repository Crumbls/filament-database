# Upgrade Notes - New Features

## Summary

Eight new features have been added to the Filament Database package. All existing functionality remains unchanged and all tests pass.

## New Files

```
src/Actions/ExportTable.php   - Export helper class
src/Actions/ImportTable.php   - Import helper class
```

## Modified Files

```
src/Pages/DatabaseManager.php                              - Main page with new actions
src/Concerns/InteractsWithDatabase.php                     - Added renameColumn(), modifyColumn()
src/Concerns/BuildsFormFields.php                          - Added NULL checkbox logic
resources/views/pages/database-manager.blade.php           - Table search + row counts
resources/views/pages/partials/structure.blade.php         - Edit column button
resources/views/pages/partials/sql.blade.php               - SQL history display
```

## Breaking Changes

**None.** All changes are additive.

## New Public Properties

- `DatabaseManager::$tableSearch` - For table dropdown filtering
- `DatabaseManager::$sqlHistory` - Array of recent SQL queries

## New Public Methods

- `InteractsWithDatabase::renameColumn()` - Rename a column
- `InteractsWithDatabase::modifyColumn()` - Modify column properties
- `DatabaseManager::getEditColumnAction()` - Get edit action for a column
- `DatabaseManager::loadHistoryQuery()` - Load query from history

## New Protected Methods

- `DatabaseManager::processNullCheckboxes()` - Process __null checkbox fields
- `DatabaseManager::mapDbTypeToSchemaType()` - Map DB types to schema types

## Behavior Changes

### Table Dropdown
- Now includes a search input for filtering
- Shows row counts next to table names (e.g., "users (1,234)")
- Row counts are cached per request for performance

### Table Columns
- All columns now have a copy icon on hover
- Clicking copies the cell value to clipboard

### Structure Tab
- Each column now has an "Edit" button (when not in read-only mode)
- Edit modal allows changing name, type, nullable, and default value

### SQL Tab
- Query history now appears below the SQL textarea
- Last 20 queries are tracked with execution time and row count
- Click any history item to reload that query

### Insert/Edit Forms
- Nullable string/text fields now have a "Set to NULL" checkbox
- Checking the box explicitly sets NULL instead of empty string

## Configuration

No new configuration options added. All features work out of the box.

## Performance Impact

### Minimal
- Export uses streaming (no memory overhead)
- Import processes rows one at a time
- SQL history limited to 20 items

### Row Counts
- **MySQL**: Fast (uses `information_schema`)
- **SQLite/PostgreSQL**: May be slow on very large tables (uses COUNT queries)
- Cached per request to avoid repeated queries

## Security Considerations

- Import blocked in read-only mode (export allowed)
- Destructive column operations respect read-only and preventDestructive settings
- Export uses proper escaping for SQL format
- File uploads restricted to CSV files

## Testing

All 111 existing tests pass. Consider adding tests for new features:

```bash
php vendor/bin/pest
```

## Rollback

If you need to revert these changes:

1. Delete `src/Actions/ExportTable.php` and `src/Actions/ImportTable.php`
2. Restore previous versions of modified files from git
3. Run tests to verify

## Questions?

See `FEATURES_ADDED.md` for detailed documentation of each feature.
