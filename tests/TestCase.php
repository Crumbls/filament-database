<?php

declare(strict_types=1);

namespace Crumbls\FilamentDatabase\Tests;

use Crumbls\FilamentDatabase\FilamentDatabasePlugin;
use Crumbls\FilamentDatabase\FilamentDatabaseServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            FilamentServiceProvider::class,
            SupportServiceProvider::class,
            FormsServiceProvider::class,
            TablesServiceProvider::class,
            ActionsServiceProvider::class,
            NotificationsServiceProvider::class,
            WidgetsServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            FilamentDatabaseServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('database.connections.secondary', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));

        // Register a Filament panel with our plugin
        $app->resolving('filament', function ($filament) {
            $filament->serving(function () {
                //
            });
        });
    }

    protected function defineDatabaseMigrations(): void
    {
        // handled in setUpDatabase
    }

    protected function setUpDatabase(): void
    {
        $schema = Schema::connection('testing');

        if (!$schema->hasTable('users')) {
            $schema->create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->boolean('is_admin')->default(false);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('categories')) {
            $schema->create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('posts')) {
            $schema->create('posts', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->longText('body')->nullable();
                $table->foreignId('user_id')->constrained('users');
                $table->foreignId('category_id')->nullable()->constrained('categories');
                $table->json('metadata')->nullable();
                $table->decimal('rating', 3, 2)->nullable();
                $table->boolean('published')->default(false);
                $table->date('publish_date')->nullable();
                $table->dateTime('scheduled_at')->nullable();
                $table->time('best_time')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Create a fresh plugin instance with optional configuration.
     */
    protected function makePlugin(): FilamentDatabasePlugin
    {
        return new FilamentDatabasePlugin();
    }

    /**
     * Seed test data into tables.
     */
    protected function seedTestData(): void
    {
        $db = \Illuminate\Support\Facades\DB::connection('testing');

        $db->table('users')->insert([
            ['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'secret', 'is_admin' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret', 'is_admin' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $db->table('categories')->insert([
            ['name' => 'Tech', 'description' => 'Technology posts', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Life', 'description' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $db->table('posts')->insert([
            ['title' => 'Hello World', 'body' => 'First post', 'user_id' => 1, 'category_id' => 1, 'metadata' => '{}', 'rating' => 4.5, 'published' => true, 'publish_date' => '2024-01-01', 'scheduled_at' => '2024-01-01 09:00:00', 'best_time' => '09:00:00', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
