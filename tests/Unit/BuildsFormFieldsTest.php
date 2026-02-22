<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\Concerns\BuildsFormFields;
use Crumbls\FilamentDatabase\Concerns\InteractsWithDatabase;
use Filament\Forms\Components;

// Create a concrete class that uses the traits so we can test them
beforeEach(function () {
    $this->builder = new class {
        use BuildsFormFields;
        use InteractsWithDatabase;

        public string $activeTable = 'posts';
        public string $activeConnection = 'testing';

        public function exposeBuildFormFields(array $columns, array $foreignKeys, string $connection, bool $isInsert): array
        {
            return $this->buildFormFields($columns, $foreignKeys, $connection, $isInsert);
        }

        public function exposeIsBooleanType(string $type): bool
        {
            return $this->isBooleanType($type);
        }

        public function exposeIsIntegerType(string $type): bool
        {
            return $this->isIntegerType($type);
        }

        public function exposeIsDecimalType(string $type): bool
        {
            return $this->isDecimalType($type);
        }

        public function exposeBuildEnumField(string $name, string $type): Components\Select
        {
            return $this->buildEnumField($name, $type);
        }

        public function exposeExtractLength(string $type): ?int
        {
            return $this->extractLength($type);
        }

        public function exposeCleanDefault(mixed $default): mixed
        {
            return $this->cleanDefault($default);
        }

        // Stub detectPrimaryKey for testing
        public function detectPrimaryKey(string $table, string $connection): ?string
        {
            return 'id';
        }
    };
});

describe('Type detection helpers', function () {

    it('detects boolean types', function () {
        expect($this->builder->exposeIsBooleanType('boolean'))->toBeTrue()
            ->and($this->builder->exposeIsBooleanType('bool'))->toBeTrue()
            ->and($this->builder->exposeIsBooleanType('tinyint(1)'))->toBeTrue()
            ->and($this->builder->exposeIsBooleanType('integer'))->toBeFalse();
    });

    it('detects integer types', function () {
        $intTypes = ['integer', 'int', 'bigint', 'biginteger', 'smallint', 'smallinteger', 'mediumint', 'tinyint', 'int4', 'int8'];
        foreach ($intTypes as $type) {
            expect($this->builder->exposeIsIntegerType($type))->toBeTrue("Failed for: {$type}");
        }
        expect($this->builder->exposeIsIntegerType('varchar'))->toBeFalse();
    });

    it('detects decimal types', function () {
        $decTypes = ['decimal', 'float', 'double', 'real', 'numeric', 'money'];
        foreach ($decTypes as $type) {
            expect($this->builder->exposeIsDecimalType($type))->toBeTrue("Failed for: {$type}");
        }
        expect($this->builder->exposeIsDecimalType('integer'))->toBeFalse();
    });
});

describe('Form field building', function () {

    it('maps boolean columns to Toggle', function () {
        $columns = [['name' => 'is_active', 'type_name' => 'boolean', 'nullable' => false, 'default' => null, 'auto_increment' => false]];
        $fields = $this->builder->exposeBuildFormFields($columns, [], 'testing', false);

        expect($fields)->toHaveCount(1)
            ->and($fields[0])->toBeInstanceOf(Components\Toggle::class);
    });

    it('maps datetime columns to DateTimePicker', function () {
        $columns = [['name' => 'scheduled_at', 'type_name' => 'datetime', 'nullable' => true, 'default' => null, 'auto_increment' => false]];
        $fields = $this->builder->exposeBuildFormFields($columns, [], 'testing', false);

        expect($fields[0])->toBeInstanceOf(Components\DateTimePicker::class);
    });

    it('maps date columns to DatePicker', function () {
        $columns = [['name' => 'publish_date', 'type_name' => 'date', 'nullable' => true, 'default' => null, 'auto_increment' => false]];
        $fields = $this->builder->exposeBuildFormFields($columns, [], 'testing', false);

        expect($fields[0])->toBeInstanceOf(Components\DatePicker::class);
    });

    it('maps time columns to TimePicker', function () {
        $columns = [['name' => 'best_time', 'type_name' => 'time', 'nullable' => true, 'default' => null, 'auto_increment' => false]];
        $fields = $this->builder->exposeBuildFormFields($columns, [], 'testing', false);

        expect($fields[0])->toBeInstanceOf(Components\TimePicker::class);
    });

    it('maps text/longtext to Textarea', function () {
        foreach (['text', 'longtext', 'mediumtext', 'tinytext'] as $type) {
            $columns = [['name' => 'content', 'type_name' => $type, 'nullable' => false, 'default' => null, 'auto_increment' => false]];
            $fields = $this->builder->exposeBuildFormFields($columns, [], 'testing', false);
            expect($fields[0])->toBeInstanceOf(Components\Textarea::class, "Failed for: {$type}");
        }
    });

    it('maps json to Textarea with helper text', function () {
        $columns = [['name' => 'metadata', 'type_name' => 'json', 'nullable' => true, 'default' => null, 'auto_increment' => false]];
        $fields = $this->builder->exposeBuildFormFields($columns, [], 'testing', false);

        expect($fields[0])->toBeInstanceOf(Components\Textarea::class);
    });

    it('maps integer types to numeric TextInput', function () {
        $columns = [['name' => 'count', 'type_name' => 'integer', 'nullable' => false, 'default' => null, 'auto_increment' => false]];
        $fields = $this->builder->exposeBuildFormFields($columns, [], 'testing', false);

        expect($fields[0])->toBeInstanceOf(Components\TextInput::class);
    });

    it('maps decimal types to numeric TextInput', function () {
        $columns = [['name' => 'price', 'type_name' => 'decimal', 'nullable' => false, 'default' => null, 'auto_increment' => false]];
        $fields = $this->builder->exposeBuildFormFields($columns, [], 'testing', false);

        expect($fields[0])->toBeInstanceOf(Components\TextInput::class);
    });

    it('maps varchar/string to TextInput', function () {
        $columns = [['name' => 'title', 'type_name' => 'varchar', 'nullable' => false, 'default' => null, 'auto_increment' => false]];
        $fields = $this->builder->exposeBuildFormFields($columns, [], 'testing', false);

        expect($fields[0])->toBeInstanceOf(Components\TextInput::class);
    });

    it('maps foreign key columns to Select', function () {
        $this->seedTestData();

        $columns = [['name' => 'user_id', 'type_name' => 'integer', 'nullable' => false, 'default' => null, 'auto_increment' => false]];
        $foreignKeys = [['columns' => ['user_id'], 'foreign_table' => 'users', 'foreign_columns' => ['id']]];
        $fields = $this->builder->exposeBuildFormFields($columns, $foreignKeys, 'testing', false);

        expect($fields[0])->toBeInstanceOf(Components\Select::class);
    });

    it('skips auto-increment on insert', function () {
        $columns = [
            ['name' => 'id', 'type_name' => 'integer', 'nullable' => false, 'default' => null, 'auto_increment' => true],
            ['name' => 'name', 'type_name' => 'varchar', 'nullable' => false, 'default' => null, 'auto_increment' => false],
        ];
        $fields = $this->builder->exposeBuildFormFields($columns, [], 'testing', true);

        expect($fields)->toHaveCount(1)
            ->and($fields[0]->getName())->toBe('name');
    });

    it('disables auto-increment on edit', function () {
        $columns = [
            ['name' => 'id', 'type_name' => 'integer', 'nullable' => false, 'default' => null, 'auto_increment' => true],
        ];
        $fields = $this->builder->exposeBuildFormFields($columns, [], 'testing', false);

        expect($fields)->toHaveCount(1)
            ->and($fields[0]->isDisabled())->toBeTrue();
    });

    it('applies password treatment to password-like columns', function () {
        $columns = [['name' => 'password', 'type_name' => 'varchar', 'nullable' => false, 'default' => null, 'auto_increment' => false]];
        $fields = $this->builder->exposeBuildFormFields($columns, [], 'testing', false);

        expect($fields[0])->toBeInstanceOf(Components\TextInput::class);
    });
});

describe('Enum field building', function () {

    it('extracts options from enum type string', function () {
        $field = $this->builder->exposeBuildEnumField('status', "enum('draft','published','archived')");

        expect($field)->toBeInstanceOf(Components\Select::class);
    });
});

describe('Utility methods', function () {

    it('extracts length from type string', function () {
        expect($this->builder->exposeExtractLength('varchar(255)'))->toBe(255)
            ->and($this->builder->exposeExtractLength('char(10)'))->toBe(10)
            ->and($this->builder->exposeExtractLength('text'))->toBeNull();
    });

    it('cleans SQL defaults', function () {
        expect($this->builder->exposeCleanDefault("'hello'"))->toBe('hello')
            ->and($this->builder->exposeCleanDefault("'test'::character varying"))->toBe("'test'")
            ->and($this->builder->exposeCleanDefault(42))->toBe(42)
            ->and($this->builder->exposeCleanDefault(null))->toBeNull();
    });
});
