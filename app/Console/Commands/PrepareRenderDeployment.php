<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class PrepareRenderDeployment extends Command
{
    protected $signature = 'app:prepare-render-deployment';

    protected $description = 'Run deployment tasks only once per Render commit and avoid reseeding on every restart.';

    public function handle(): int
    {
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
        if (! $this->canUsePersistentCache()) {
            return false;
        }

        return Cache::get($this->deploymentCacheKey()) === $commit;
    }

    private function markCommitAsProcessed(string $commit): void
    {
        if (! $this->canUsePersistentCache()) {
            return;
        }

        Cache::forever($this->deploymentCacheKey(), $commit);
    }

    private function canUsePersistentCache(): bool
    {
        if (config('cache.default') !== 'database') {
            return true;
        }

        return Schema::hasTable(config('cache.stores.database.table', 'cache'));
    }

    private function deploymentCacheKey(): string
    {
        return 'deploy:last_processed_render_commit';
    }
}
