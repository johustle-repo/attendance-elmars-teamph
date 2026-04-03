<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

class InstallApplication extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:install
        {--seed : Seed demo accounts after migrating}
        {--force : Force database operations in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prepare the application for a fresh install';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Preparing application install...');

        try {
            $this->ensureEnvironmentFile();
            $this->ensureSqliteDatabase();
            $this->ensureApplicationKey();
            $this->ensureStorageLink();
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $migrationExitCode = $this->call('migrate', array_filter([
            '--seed' => $this->option('seed'),
            '--force' => $this->option('force'),
        ]));

        if ($migrationExitCode !== self::SUCCESS) {
            $this->components->error('Database migrations failed. Check your database settings in .env and try again.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Install complete.');
        $this->line('Next steps: `npm run dev` for local development or `npm run build` for a production-style build.');

        if (! $this->option('seed')) {
            $this->line('Run `php artisan db:seed` later if you want the demo accounts.');
        }

        return self::SUCCESS;
    }

    private function ensureEnvironmentFile(): void
    {
        $environmentFile = base_path('.env');

        if (is_file($environmentFile)) {
            $this->line(' - Using existing `.env` file.');

            return;
        }

        $exampleFile = base_path('.env.example');

        if (! is_file($exampleFile)) {
            throw new RuntimeException('The .env.example file is missing.');
        }

        if (! copy($exampleFile, $environmentFile)) {
            throw new RuntimeException('Unable to copy .env.example to .env.');
        }

        $this->line(' - Created `.env` from `.env.example`.');
    }

    private function ensureSqliteDatabase(): void
    {
        if (config('database.default') !== 'sqlite') {
            $this->line(' - Using the configured `'.config('database.default').'` database connection.');

            return;
        }

        $databasePath = config('database.connections.sqlite.database');

        if (! is_string($databasePath) || $databasePath === '' || $databasePath === ':memory:') {
            $this->line(' - SQLite connection is using an in-memory or custom database.');

            return;
        }

        $absolutePath = $this->isAbsolutePath($databasePath)
            ? $databasePath
            : base_path($databasePath);

        $directory = dirname($absolutePath);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the SQLite database directory.');
        }

        if (! is_file($absolutePath) && ! touch($absolutePath)) {
            throw new RuntimeException('Unable to create the SQLite database file.');
        }

        $this->line(' - SQLite database is ready at `'.$absolutePath.'`.');
    }

    private function ensureApplicationKey(): void
    {
        if (filled(config('app.key'))) {
            $this->line(' - Application key already exists.');

            return;
        }

        $exitCode = $this->call('key:generate', ['--ansi' => true]);

        if ($exitCode !== self::SUCCESS) {
            throw new RuntimeException('Unable to generate the application key.');
        }

        $this->line(' - Generated a new application key.');
    }

    private function ensureStorageLink(): void
    {
        $storageLink = public_path('storage');

        if (is_link($storageLink) || is_dir($storageLink)) {
            $this->line(' - Public storage link already exists.');

            return;
        }

        $exitCode = $this->call('storage:link', ['--ansi' => true]);

        if ($exitCode !== self::SUCCESS) {
            $this->warn(' - Storage link could not be created automatically. You can run `php artisan storage:link` later if needed.');

            return;
        }

        $this->line(' - Created the public storage link.');
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:\\\\/', $path) === 1;
    }
}