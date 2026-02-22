<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\Concerns\InteractsWithDatabase;
use Illuminate\Support\Facades\DB;

describe('Row CRUD Operations', function () {

    beforeEach(function () {
        $this->db = new class {
            use InteractsWithDatabase;

            public string $activeTable = '';
            public string $activeConnection = 'testing';

            public function detectPrimaryKey(string $table, string $connection): ?string
            {
                return 'id';
            }
        };
        $this->seedTestData();
    });

    it('browses rows with pagination', function () {
        $result = $this->db->getRows('users', 'testing', 1, 10);

        expect($result->total())->toBe(2)
            ->and($result->items())->toHaveCount(2);
    });

    it('paginates correctly', function () {
        $result = $this->db->getRows('users', 'testing', 1, 1);

        expect($result->total())->toBe(2)
            ->and($result->items())->toHaveCount(1)
            ->and($result->lastPage())->toBe(2);
    });

    it('inserts a row', function () {
        $result = $this->db->insertRow('users', [
            'name' => 'Charlie',
            'email' => 'charlie@example.com',
            'password' => 'secret',
            'is_admin' => false,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ], 'testing');

        expect($result)->toBeTrue();
        expect(DB::connection('testing')->table('users')->count())->toBe(3);
    });

    it('updates a row', function () {
        $affected = $this->db->updateRow('users', ['id' => 1], ['name' => 'Alice Updated'], 'testing');

        expect($affected)->toBe(1);
        $user = DB::connection('testing')->table('users')->where('id', 1)->first();
        expect($user->name)->toBe('Alice Updated');
    });

    it('deletes a row', function () {
        $affected = $this->db->deleteRow('users', ['id' => 2], 'testing');

        expect($affected)->toBe(1);
        expect(DB::connection('testing')->table('users')->count())->toBe(1);
    });

    it('handles update with multiple where conditions', function () {
        $affected = $this->db->updateRow('users', ['name' => 'Alice', 'email' => 'alice@example.com'], ['is_admin' => false], 'testing');

        expect($affected)->toBe(1);
        $user = DB::connection('testing')->table('users')->where('id', 1)->first();
        expect((bool) $user->is_admin)->toBeFalse();
    });

    it('returns zero affected rows for non-matching delete', function () {
        $affected = $this->db->deleteRow('users', ['id' => 999], 'testing');
        expect($affected)->toBe(0);
    });
});
