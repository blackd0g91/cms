<?php

use App\Cms\SystemStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // A throwaway storage folder, so tests never read or write the real log.
    $this->storage = sys_get_temp_dir().'/garmr-status-'.uniqid();
    File::makeDirectory("{$this->storage}/logs", recursive: true);
    File::makeDirectory("{$this->storage}/app", recursive: true);
    $this->originalStorage = $this->app->storagePath();
    $this->app->useStoragePath($this->storage);
});

afterEach(function () {
    $this->app->useStoragePath($this->originalStorage);
    File::deleteDirectory($this->storage);
});

function warningMessages(): array
{
    return array_column(app(SystemStatus::class)->report()['warnings'], 'message');
}

test('errors from the last 24 hours in this environment are counted', function () {
    $line = fn (string $when, string $level, string $message, string $env = 'testing') => '['.now()->sub($when)->format('Y-m-d H:i:s')."] {$env}.{$level}: {$message}";

    File::put("{$this->storage}/logs/laravel.log", implode("\n", [
        $line('2 days', 'ERROR', 'too old'),
        $line('3 hours', 'ERROR', 'first recent error'),
        '#0 /some/stack/trace/line',
        $line('1 hour', 'CRITICAL', 'the latest one'),
        $line('30 minutes', 'INFO', 'just information'),
        $line('10 minutes', 'ERROR', 'from another environment', 'local'),
    ])."\n");

    $errors = app(SystemStatus::class)->report()['errors'];

    expect($errors['count'])->toBe(2)
        ->and($errors['latest']['message'])->toBe('the latest one')
        ->and(warningMessages())->toContain('2 errors were logged in the last 24 hours.');
});

test('the deployed version comes from the file deploy.sh writes', function () {
    File::put("{$this->storage}/app/deploy.json", json_encode([
        'commit' => 'abc1234', 'committed_at' => '2026-09-29T10:00:00-03:00', 'deployed_at' => '2026-09-29T13:05:00Z',
    ]));

    expect(app(SystemStatus::class)->report()['deploy'])->toBe([
        'commit' => 'abc1234', 'committed_at' => '2026-09-29T10:00:00-03:00', 'deployed_at' => '2026-09-29T13:05:00Z',
    ]);
});

test('pending migrations are flagged', function () {
    expect(implode(' ', warningMessages()))->not->toContain('migration');

    DB::table('migrations')->latest('id')->limit(1)->delete();

    expect(warningMessages())->toContain('1 database migration has not run. Run deploy.sh (or php artisan migrate) on the server.');
});

test('debug mode in production is flagged', function () {
    $this->app['env'] = 'production';
    config(['app.debug' => true]);

    expect(implode(' ', warningMessages()))->toContain('Debug mode is on in production');
});

test('the health page includes the system status', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('cp.health'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('system.php', PHP_VERSION)
            ->where('system.environment', 'testing')
            ->has('system.warnings')
            ->has('system.disk.free'));
});
