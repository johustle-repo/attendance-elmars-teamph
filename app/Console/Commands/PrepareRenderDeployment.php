<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PrepareRenderDeployment extends Command
{
    protected $signature = 'app:prepare-render-deployment';

    protected $description = 'Run deployment tasks only once per Render commit and avoid reseeding on every restart.';

    public function handle(): int
    {
        if ($this->isRunningOnRender() && ! $this->hasPersistentRenderDatabaseConfigured()) {
            $this->components->error('Render must use a persistent Postgres database. Set DB_CONNECTION=pgsql and provide DB_URL from a Render Postgres service.');

            return self::FAILURE;
        }

        $currentCommit = env('RENDER_GIT_COMMIT');

        if ($currentCommit && $this->hasProcessedCommit($currentCommit)) {
            $this->components->info("Deployment tasks already completed for commit {$currentCommit}.");

            return self::SUCCESS;
        }

        $this->components->info('Running database migrations.');

        if ($this->runArtisanCommand('migrate', ['--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        if (! Schema::hasTable('users') || ! User::query()->exists()) {
            $this->components->info('No users detected. Running database seeders.');

            if ($this->runArtisanCommand('db:seed', ['--force' => true]) !== self::SUCCESS) {
                return self::FAILURE;
            }
        } else {
            $this->components->info('Skipping seeders because application data already exists.');
        }

        if ($currentCommit) {
            $this->markCommitAsProcessed($currentCommit);
        }

        return self::SUCCESS;
    }

    private function runArtisanCommand(string $command, array $arguments = []): int
    {
        $exitCode = Artisan::call($command, $arguments);
        $this->output->write(Artisan::output());

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function hasProcessedCommit(string $commit): bool
    {
        if (! Schema::hasTable('deployment_state')) {
            return false;
        }

        return DB::table('deployment_state')
            ->where('key', $this->deploymentStateKey())
            ->value('value') === $commit;
    }

    private function markCommitAsProcessed(string $commit): void
    {
        if (! Schema::hasTable('deployment_state')) {
            return;
        }

        $timestamp = now();

        DB::table('deployment_state')->updateOrInsert(
            ['key' => $this->deploymentStateKey()],
            [
                'value' => $commit,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        );
    }

    private function isRunningOnRender(): bool
    {
        return filled(env('RENDER_SERVICE_ID')) || filled(env('RENDER')) || filled(env('RENDER_GIT_COMMIT'));
    }

    private function hasPersistentRenderDatabaseConfigured(): bool
    {
        return config('database.default') === 'pgsql' && filled(env('DB_URL'));
    }

    private function deploymentStateKey(): string
    {
        return 'render:last_processed_commit';
    }
}
