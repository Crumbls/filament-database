<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\Models\DynamicModel;

describe('DynamicModel', function () {

    it('binds to the correct table and connection', function () {
        $model = DynamicModel::forTable('users', 'testing');

        expect($model->getTable())->toBe('users')
            ->and($model->getConnectionName())->toBe('testing');
    });

    it('sets primary key and enables incrementing', function () {
        $model = DynamicModel::forTable('users', 'testing', 'id');

        expect($model->getKeyName())->toBe('id')
            ->and($model->incrementing)->toBeTrue();
    });

    it('defaults key name to id when no primary key set', function () {
        $model = DynamicModel::forTable('users', 'testing');

        expect($model->getKeyName())->toBe('id');
    });

    it('has timestamps disabled', function () {
        $model = DynamicModel::forTable('users', 'testing');

        expect($model->usesTimestamps())->toBeFalse();
    });

    it('is not guarded', function () {
        $model = DynamicModel::forTable('users', 'testing');

        expect($model->getGuarded())->toBe([]);
    });

    it('preserves table and connection on newInstance', function () {
        DynamicModel::forTable('posts', 'testing', 'id');
        $instance = (new DynamicModel)->newInstance(['title' => 'Test']);

        expect($instance->getTable())->toBe('posts')
            ->and($instance->getConnectionName())->toBe('testing')
            ->and($instance->getKeyName())->toBe('id')
            ->and($instance->incrementing)->toBeTrue();
    });

    it('can query the database', function () {
        $this->seedTestData();

        $model = DynamicModel::forTable('users', 'testing', 'id');
        $results = $model->newQuery()->get();

        expect($results)->toHaveCount(2)
            ->and($results->first()->name)->toBe('Alice');
    });
});
