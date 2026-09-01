<?php

declare(strict_types=1);

namespace Crumbls\FilamentDatabase\Models;

use Illuminate\Database\Eloquent\Model;

class DynamicModel extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    public $incrementing = false;

    public static function forTable(string $table, string $connection, ?string $primaryKey = null): static
    {
        $instance = new static();
        $instance->setTable($table);
        $instance->setConnection($connection);

        if ($primaryKey !== null) {
            $instance->setKeyName($primaryKey);
            $instance->incrementing = true;
        }

        return $instance;
    }

    public function newInstance($attributes = [], $exists = false): static
    {
        $model = parent::newInstance($attributes, $exists);

        $model->setKeyName($this->getKeyName());
        $model->setIncrementing($this->getIncrementing());
        $model->setKeyType($this->getKeyType());

        return $model;
    }
}
