<?php

namespace App\Cms;

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The health of the installation, for the dashboard: what is deployed, recent
 * errors, and anything that needs attention.
 */
class SystemStatus
{
    /**
     * Written by deploy.sh after every deploy.
     */
    public const string DEPLOY_FILE = 'app/deploy.json';

    /**
     * Only the end of the log is read, so a huge log stays cheap.
     */
    private const int LOG_TAIL_BYTES = 2_000_000;

    public function __construct(private Application $app, private Migrator $migrator) {}

    /**
     * @return array{
     *     deploy: array{commit: string|null, committed_at: string|null, deployed_at: string|null},
     *     php: string, laravel: string, environment: string,
     *     errors: array{count: int, latest: array{at: string, message: string}|null},
     *     disk: array{free: int|null, total: int|null},
     *     database_size: int|null, uploads_size: int,
     *     warnings: list<array{level: string, message: string}>
     * }
     */
    public function report(): array
    {
        $disk = $this->disk();
        $errors = $this->recentErrors();

        return [
            'deploy' => $this->deploy(),
            'php' => PHP_VERSION,
            'laravel' => $this->app->version(),
            'environment' => $this->app->environment(),
            'errors' => $errors,
            'disk' => $disk,
            'database_size' => $this->databaseSize(),
            'uploads_size' => $this->uploadsSize(),
            'warnings' => $this->warnings($disk, $errors),
        ];
    }

    /**
     * @return array{commit: string|null, committed_at: string|null, deployed_at: string|null}
     */
    private function deploy(): array
    {
        $file = storage_path(self::DEPLOY_FILE);
        $deploy = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return [
            'commit' => is_array($deploy) ? ($deploy['commit'] ?? null) : $this->gitCommit(),
            'committed_at' => is_array($deploy) ? ($deploy['committed_at'] ?? null) : null,
            'deployed_at' => is_array($deploy) ? ($deploy['deployed_at'] ?? null) : null,
        ];
    }

    /**
     * The checked out commit, read straight from .git, for installs that were
     * not deployed with deploy.sh (like a development machine).
     */
    private function gitCommit(): ?string
    {
        $head = base_path('.git/HEAD');

        if (! is_file($head)) {
            return null;
        }

        $ref = trim((string) file_get_contents($head));

        if (! str_starts_with($ref, 'ref: ')) {
            return substr($ref, 0, 7) ?: null;
        }

        $name = substr($ref, 5);
        $file = base_path(".git/{$name}");

        if (is_file($file)) {
            return substr(trim((string) file_get_contents($file)), 0, 7) ?: null;
        }

        // Refs can also live in packed-refs.
        $packed = base_path('.git/packed-refs');

        if (is_file($packed) && preg_match('/^([0-9a-f]{40}) '.preg_quote($name, '/').'$/m', (string) file_get_contents($packed), $match)) {
            return substr($match[1], 0, 7);
        }

        return null;
    }

    /**
     * Errors (and worse) logged in the last 24 hours by this environment.
     *
     * @return array{count: int, latest: array{at: string, message: string}|null}
     */
    private function recentErrors(): array
    {
        $since = now()->subDay();
        $environment = preg_quote($this->app->environment(), '/');
        $count = 0;
        $latest = null;

        foreach (glob(storage_path('logs/laravel*.log')) ?: [] as $file) {
            if (filemtime($file) < $since->getTimestamp()) {
                continue;
            }

            $size = (int) filesize($file);
            $handle = fopen($file, 'r');

            if ($handle === false) {
                continue;
            }

            fseek($handle, max(0, $size - self::LOG_TAIL_BYTES));
            $tail = (string) stream_get_contents($handle);
            fclose($handle);

            preg_match_all("/^\\[(\\d{4}-\\d{2}-\\d{2}[ T][\\d:]{8})[^\\]]*\\] {$environment}\\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/m", $tail, $matches, PREG_SET_ORDER);

            foreach ($matches as [, $at, , $message]) {
                try {
                    $time = CarbonImmutable::parse($at, config('app.timezone'));
                } catch (Throwable) {
                    continue;
                }

                if ($time->lessThan($since)) {
                    continue;
                }

                $count++;

                if ($latest === null || $time->greaterThan($latest['time'])) {
                    $latest = ['time' => $time, 'message' => $message];
                }
            }
        }

        return [
            'count' => $count,
            'latest' => $latest ? [
                'at' => $latest['time']->toIso8601String(),
                'message' => mb_strimwidth(trim($latest['message']), 0, 300, '…'),
            ] : null,
        ];
    }

    /**
     * @return array{free: int|null, total: int|null}
     */
    private function disk(): array
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        return [
            'free' => $free === false ? null : (int) $free,
            'total' => $total === false ? null : (int) $total,
        ];
    }

    private function databaseSize(): ?int
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return null;
        }

        $file = (string) DB::connection()->getConfig('database');

        return is_file($file) ? (int) filesize($file) : null;
    }

    /**
     * Uploaded images and their resized copies. Walking the folder is slow
     * with many files, so the result is cached for ten minutes.
     */
    private function uploadsSize(): int
    {
        return Cache::remember('system-status.uploads-size', 600, function () {
            $root = storage_path('app/public');

            if (! is_dir($root)) {
                return 0;
            }

            $size = 0;

            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                $size += $file->isFile() ? $file->getSize() : 0;
            }

            return $size;
        });
    }

    /**
     * @param  array{free: int|null, total: int|null}  $disk
     * @param  array{count: int, latest: array{at: string, message: string}|null}  $errors
     * @return list<array{level: string, message: string}>
     */
    private function warnings(array $disk, array $errors): array
    {
        $warnings = [];

        $pending = $this->pendingMigrations();

        if ($pending > 0) {
            $warnings[] = ['level' => 'critical', 'message' => "{$pending} database ".($pending === 1 ? 'migration has' : 'migrations have').' not run. Run deploy.sh (or php artisan migrate) on the server.'];
        }

        if ($this->app->isProduction() && config('app.debug')) {
            $warnings[] = ['level' => 'critical', 'message' => 'Debug mode is on in production. Set APP_DEBUG=false in .env, then run php artisan optimize.'];
        }

        if ($errors['count'] > 0) {
            $warnings[] = ['level' => 'serious', 'message' => "{$errors['count']} ".($errors['count'] === 1 ? 'error was' : 'errors were').' logged in the last 24 hours.'];
        }

        if ($this->app->isDownForMaintenance()) {
            $warnings[] = ['level' => 'serious', 'message' => 'The site is in maintenance mode. Visitors see "back soon". Bring it back with php artisan up.'];
        }

        if ($disk['free'] !== null && $disk['total'] && $disk['free'] / $disk['total'] < 0.1) {
            $warnings[] = ['level' => 'warning', 'message' => 'Less than 10% disk space is left.'];
        }

        if (! file_exists(public_path('storage'))) {
            $warnings[] = ['level' => 'serious', 'message' => 'Uploaded images can not be shown: the public/storage link is missing. Run php artisan storage:link.'];
        }

        return $warnings;
    }

    private function pendingMigrations(): int
    {
        try {
            if (! $this->migrator->repositoryExists()) {
                return 0;
            }

            $files = $this->migrator->getMigrationFiles($this->migrator->paths() ?: [database_path('migrations')]);
            $ran = $this->migrator->getRepository()->getRan();

            return count(array_diff(array_keys($files), $ran));
        } catch (Throwable) {
            return 0;
        }
    }
}
