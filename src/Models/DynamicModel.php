<?php

namespace Crumbls\FilamentDatabase\Models;

use Illuminate\Database\Eloquent\Model;

class DynamicModel extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    public $incrementing = false;

    protected static ?string $dynamicTable = null;
    protected static ?string $dynamicConnection = null;
    protected static ?string $dynamicKeyName = null;

    public static function forTable(string $table, string $connection, ?string $primaryKey = null): static
    {
        $instance = new static;
        $instance->setTable($table);
        $instance->setConnection($connection);

        if ($primaryKey) {
            $instance->setKeyName($primaryKey);
            $instance->incrementing = true;
        }

        // Store for static resolution in query builder
        static::$dynamicTable = $table;
        static::$dynamicConnection = $connection;
        static::$dynamicKeyName = $primaryKey;

        return $instance;
    }

    public function newInstance($attributes = [], $exists = false): static
    {
        $model = parent::newInstance($attributes, $exists);

        if (static::$dynamicTable) {
            $model->setTable(static::$dynamicTable);
        }
        if (static::$dynamicConnection) {
            $model->setConnection(static::$dynamicConnection);
        }
        if (static::$dynamicKeyName) {
            $model->setKeyName(static::$dynamicKeyName);
            $model->incrementing = true;
        }

        return $model;
    }

    public function getKeyName(): string
    {
        return static::$dynamicKeyName ?? 'id';
    }
}
