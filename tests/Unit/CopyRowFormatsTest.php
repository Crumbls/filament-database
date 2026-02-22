<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\Pages\DatabaseManager;

describe('Copy Row Formats and Utilities', function () {

    beforeEach(function () {
        $this->manager = new class extends DatabaseManager {
            // Expose protected methods for testing
            public function testMapDbTypeToSchemaType(string $dbType): string
            {
                return $this->mapDbTypeToSchemaType($dbType);
            }

            public function testProcessNullCheckboxes(array $data): array
            {
                return $this->processNullCheckboxes($data);
            }
        };
    });

    describe('mapDbTypeToSchemaType', function () {

        it('maps varchar to string', function () {
            $result = $this->manager->testMapDbTypeToSchemaType('varchar');
            expect($result)->toBe('string');
        });

        it('maps bigint to bigInteger', function () {
            $result = $this->manager->testMapDbTypeToSchemaType('bigint');
            expect($result)->toBe('bigInteger');
        });

        it('maps timestamp to dateTime', function () {
            $result = $this->manager->testMapDbTypeToSchemaType('timestamp');
            expect($result)->toBe('dateTime');
        });

        it('maps json to json', function () {
            $result = $this->manager->testMapDbTypeToSchemaType('json');
            expect($result)->toBe('json');
        });

        it('maps datetime to dateTime', function () {
            $result = $this->manager->testMapDbTypeToSchemaType('datetime');
            expect($result)->toBe('dateTime');
        });

        it('maps int to integer', function () {
            $result = $this->manager->testMapDbTypeToSchemaType('int');
            expect($result)->toBe('integer');
        });

        it('maps text to text', function () {
            $result = $this->manager->testMapDbTypeToSchemaType('text');
            expect($result)->toBe('text');
        });

        it('maps bool to boolean', function () {
            $result = $this->manager->testMapDbTypeToSchemaType('bool');
            expect($result)->toBe('boolean');
        });

        it('maps boolean to boolean', function () {
            $result = $this->manager->testMapDbTypeToSchemaType('boolean');
            expect($result)->toBe('boolean');
        });

        it('maps date to date', function () {
            $result = $this->manager->testMapDbTypeToSchemaType('date');
            expect($result)->toBe('date');
        });

        it('maps time to time', function () {
            $result = $this->manager->testMapDbTypeToSchemaType('time');
            expect($result)->toBe('time');
        });

        it('maps decimal to decimal', function () {
            $result = $this->manager->testMapDbTypeToSchemaType('decimal');
            expect($result)->toBe('decimal');
        });

        it('defaults unknown types to string', function () {
            $result = $this->manager->testMapDbTypeToSchemaType('unknown_type');
            expect($result)->toBe('string');
        });

        it('handles case insensitivity', function () {
            expect($this->manager->testMapDbTypeToSchemaType('VARCHAR'))->toBe('string');
            expect($this->manager->testMapDbTypeToSchemaType('BIGINT'))->toBe('bigInteger');
            expect($this->manager->testMapDbTypeToSchemaType('TIMESTAMP'))->toBe('dateTime');
        });
    });

    describe('processNullCheckboxes', function () {

        it('sets field to null when checkbox is checked', function () {
            $data = [
                'name' => 'Alice',
                'bio' => 'Some bio text',
                'bio__null' => true, // Checkbox checked
            ];

            $result = $this->manager->testProcessNullCheckboxes($data);

            expect($result)->toHaveKey('name')
                ->and($result['name'])->toBe('Alice')
                ->and($result)->toHaveKey('bio')
                ->and($result['bio'])->toBeNull()
                ->and($result)->not->toHaveKey('bio__null');
        });

        it('keeps value when checkbox is unchecked', function () {
            $data = [
                'name' => 'Bob',
                'bio' => 'Bob bio',
                'bio__null' => false, // Checkbox not checked
            ];

            $result = $this->manager->testProcessNullCheckboxes($data);

            expect($result)->toHaveKey('bio')
                ->and($result['bio'])->toBe('Bob bio')
                ->and($result)->not->toHaveKey('bio__null');
        });

        it('strips all __null keys from result', function () {
            $data = [
                'name' => 'Charlie',
                'name__null' => false,
                'email' => 'charlie@example.com',
                'email__null' => false,
                'bio' => '',
                'bio__null' => true,
            ];

            $result = $this->manager->testProcessNullCheckboxes($data);

            expect($result)->not->toHaveKey('name__null')
                ->and($result)->not->toHaveKey('email__null')
                ->and($result)->not->toHaveKey('bio__null')
                ->and($result)->toHaveKey('name')
                ->and($result)->toHaveKey('email')
                ->and($result)->toHaveKey('bio')
                ->and($result['bio'])->toBeNull();
        });

        it('handles multiple null checkboxes', function () {
            $data = [
                'field1' => 'value1',
                'field1__null' => false,
                'field2' => 'value2',
                'field2__null' => true,
                'field3' => 'value3',
                'field3__null' => true,
            ];

            $result = $this->manager->testProcessNullCheckboxes($data);

            expect($result['field1'])->toBe('value1')
                ->and($result['field2'])->toBeNull()
                ->and($result['field3'])->toBeNull();
        });

        it('works with fields that have no __null checkbox', function () {
            $data = [
                'name' => 'Diana',
                'email' => 'diana@example.com',
            ];

            $result = $this->manager->testProcessNullCheckboxes($data);

            expect($result)->toBe($data);
        });

        it('handles empty data array', function () {
            $result = $this->manager->testProcessNullCheckboxes([]);
            expect($result)->toBe([]);
        });
    });
});
