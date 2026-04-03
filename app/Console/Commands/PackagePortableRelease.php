<?php

namespace App\Console\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

class PackagePortableRelease extends Command
{
    /** @var array<int, string>|null */
    private ?array $devVendorPaths = null;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:package-portable
        {--output=dist/portable : Output directory for the portable package}
        {--name=duscaff-attendance-portable : Folder and archive name for the package}
        {--without-current-data : Reset the packaged SQLite database for a fresh distribution}
        {--force : Overwrite an existing portable package}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a portable Windows release folder that can be copied to another computer';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $outputDirectory = base_path(trim((string) $this->option('output'), '\\/'));
        $packageName = trim((string) $this->option('name'));
        $includeCurrentData = ! (bool) $this->option('without-current-data');
        $packageDirectory = $outputDirectory.DIRECTORY_SEPARATOR.$packageName;
        $archivePath = $outputDirectory.DIRECTORY_SEPARATOR.$packageName.'.zip';

        if ($packageName === '') {
            $this->components->error('The package name cannot be empty.');

            return self::FAILURE;
        }

        try {
            $this->ensurePortableBuildRequirements();
            $this->prepareOutputDirectory($outputDirectory, $packageDirectory, $archivePath);
            $this->clearRuntimeCaches();

            $devPackageCount = count($this->getDevVendorPaths());
            $this->components->info("Copying application files into the portable package (excluding {$devPackageCount} dev-only vendor packages)...");

            foreach ($this->runtimeDirectories() as $directory) {
                $this->copyDirectory(
                    base_path($directory),
                    $packageDirectory.DIRECTORY_SEPARATOR.$directory,
                    $directory,
                );
            }

            foreach ($this->runtimeFiles() as $file) {
                $this->copyFile(
                    base_path($file),
                    $packageDirectory.DIRECTORY_SEPARATOR.$file,
                );
            }

            $this->prepareStorageDirectory($packageDirectory);
            if (! $includeCurrentData) {
                $this->resetPackageDataForDistribution($packageDirectory);
            }
            $this->copyPortableTemplates($packageDirectory);
            $this->writePortableNotes($packageDirectory, $includeCurrentData);

            if (class_exists(ZipArchive::class)) {
                $this->createArchive($packageDirectory, $archivePath);
            } else {
                $this->warn('ZipArchive is not available, so only the portable folder was created.');
            }
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Portable package ready.');
        $this->line('Folder: `'.$packageDirectory.'`');

        if (is_file($archivePath)) {
            $this->line('Archive: `'.$archivePath.'`');
        }

        $this->line('Copy the portable folder or zip file to another Windows computer, then run `start-portable.bat` there.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function runtimeDirectories(): array
    {
        return [
            'app',
            'bootstrap',
            'config',
            'database',
            'public',
            'resources',
            'routes',
            'vendor',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function runtimeFiles(): array
    {
        return [
            'artisan',
            '.env.example',
            'composer.json',
            'composer.lock',
        ];
    }

    private function ensurePortableBuildRequirements(): void
    {
        if (! is_file(base_path('vendor/autoload.php'))) {
            throw new RuntimeException('Vendor dependencies are missing. Run `composer install` first.');
        }

        if (! is_file(public_path('build/manifest.json'))) {
            throw new RuntimeException('Built frontend assets are missing. Run `npm run build` first.');
        }
    }

    private function prepareOutputDirectory(
        string $outputDirectory,
        string $packageDirectory,
        string $archivePath,
    ): void {
        $force = (bool) $this->option('force');

        if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0777, true) && ! is_dir($outputDirectory)) {
            throw new RuntimeException('Unable to create the portable output directory.');
        }

        if ((is_dir($packageDirectory) || is_file($archivePath)) && ! $force) {
            throw new RuntimeException('The portable output already exists. Re-run the command with `--force` to overwrite it.');
        }

        if (is_dir($packageDirectory)) {
            $this->deleteDirectory($packageDirectory);
        }

        if (is_file($archivePath) && ! unlink($archivePath)) {
            throw new RuntimeException('Unable to remove the existing portable archive.');
        }
    }

    private function clearRuntimeCaches(): void
    {
        $this->components->info('Clearing cached views and bootstrap files before packaging...');
        $this->callSilent('optimize:clear');
    }

    private function copyDirectory(
        string $source,
        string $destination,
        string $relativeRoot,
    ): void {
        if (! is_dir($source)) {
            return;
        }

        if (! is_dir($destination) && ! mkdir($destination, 0777, true) && ! is_dir($destination)) {
            throw new RuntimeException('Unable to create directory `'.$destination.'`.');
        }

        $items = scandir($source);

        if ($items === false) {
            throw new RuntimeException('Unable to read directory `'.$source.'`.');
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $source.DIRECTORY_SEPARATOR.$item;
            $destinationPath = $destination.DIRECTORY_SEPARATOR.$item;
            $relativePath = $relativeRoot.DIRECTORY_SEPARATOR.$item;

            if ($this->shouldSkipPath($relativePath, $sourcePath)) {
                continue;
            }

            if (is_dir($sourcePath) && ! is_link($sourcePath)) {
                $this->copyDirectory($sourcePath, $destinationPath, $relativePath);

                continue;
            }

            $this->copyFile($sourcePath, $destinationPath);
        }
    }

    private function copyFile(string $source, string $destination): void
    {
        $directory = dirname($destination);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create directory `'.$directory.'`.');
        }

        if (! copy($source, $destination)) {
            throw new RuntimeException('Unable to copy `'.$source.'`.');
        }
    }

    private function shouldSkipPath(string $relativePath, string $absolutePath): bool
    {
        $normalizedPath = str_replace('\\', '/', $relativePath);

        if (is_link($absolutePath)) {
            return true;
        }

        $staticSkips = [
            'public/hot',
            'public/storage',
        ];

        if (in_array($normalizedPath, $staticSkips, true)) {
            return true;
        }

        foreach ($this->getDevVendorPaths() as $devPath) {
            if ($normalizedPath === $devPath || str_starts_with($normalizedPath, $devPath.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function getDevVendorPaths(): array
    {
        if ($this->devVendorPaths !== null) {
            return $this->devVendorPaths;
        }

        $lockPath = base_path('composer.lock');

        if (! is_file($lockPath)) {
            return $this->devVendorPaths = [];
        }

        $contents = file_get_contents($lockPath);

        if ($contents === false) {
            return $this->devVendorPaths = [];
        }

        $lock = json_decode($contents, true);

        if (! isset($lock['packages-dev']) || ! is_array($lock['packages-dev'])) {
            return $this->devVendorPaths = [];
        }

        $paths = [];
        foreach ($lock['packages-dev'] as $package) {
            if (isset($package['name']) && is_string($package['name'])) {
                $paths[] = 'vendor/'.$package['name'];
            }
        }

        return $this->devVendorPaths = $paths;
    }

    private function prepareStorageDirectory(string $packageDirectory): void
    {
        $targetStorage = $packageDirectory.DIRECTORY_SEPARATOR.'storage';

        $this->copyDirectory(
            storage_path('app'),
            $targetStorage.DIRECTORY_SEPARATOR.'app',
            'storage/app',
        );

        foreach ([
            'framework/cache/data',
            'framework/sessions',
            'framework/testing',
            'framework/views',
            'logs',
        ] as $directory) {
            $path = $targetStorage.DIRECTORY_SEPARATOR.$directory;

            if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
                throw new RuntimeException('Unable to create storage directory `'.$path.'`.');
            }
        }

        foreach ($this->storageGitignoreTemplates() as $source => $destination) {
            if (is_file($source)) {
                $this->copyFile($source, $targetStorage.DIRECTORY_SEPARATOR.$destination);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function storageGitignoreTemplates(): array
    {
        return [
            storage_path('app/.gitignore') => 'app/.gitignore',
            storage_path('app/private/.gitignore') => 'app/private/.gitignore',
            storage_path('app/public/.gitignore') => 'app/public/.gitignore',
            storage_path('framework/.gitignore') => 'framework/.gitignore',
            storage_path('framework/cache/.gitignore') => 'framework/cache/.gitignore',
            storage_path('framework/cache/data/.gitignore') => 'framework/cache/data/.gitignore',
            storage_path('framework/sessions/.gitignore') => 'framework/sessions/.gitignore',
            storage_path('framework/testing/.gitignore') => 'framework/testing/.gitignore',
            storage_path('framework/views/.gitignore') => 'framework/views/.gitignore',
            storage_path('logs/.gitignore') => 'logs/.gitignore',
        ];
    }

    private function copyPortableTemplates(string $packageDirectory): void
    {
        $templates = [
            'deploy/portable/.env.portable.example' => '.env.portable.example',
            'deploy/portable/prepare-portable.bat' => 'prepare-portable.bat',
            'deploy/portable/start-portable.bat' => 'start-portable.bat',
            'deploy/portable/PORTABLE-README.md' => 'PORTABLE-README.md',
        ];

        foreach ($templates as $source => $destination) {
            $absoluteSource = base_path($source);

            if (! is_file($absoluteSource)) {
                throw new RuntimeException('Portable template `'.$source.'` is missing.');
            }

            $this->copyFile(
                $absoluteSource,
                $packageDirectory.DIRECTORY_SEPARATOR.$destination,
            );
        }
    }

    private function resetPackageDataForDistribution(string $packageDirectory): void
    {
        $databasePath = $packageDirectory.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sqlite';
        $databaseDirectory = dirname($databasePath);

        if (! is_dir($databaseDirectory) && ! mkdir($databaseDirectory, 0777, true) && ! is_dir($databaseDirectory)) {
            throw new RuntimeException('Unable to create the packaged database directory.');
        }

        if (file_put_contents($databasePath, '') === false) {
            throw new RuntimeException('Unable to reset the packaged SQLite database.');
        }
    }

    private function writePortableNotes(string $packageDirectory, bool $includeCurrentData): void
    {
        $sqlitePath = base_path('database/database.sqlite');
        $databaseIncluded = $includeCurrentData
            && is_file($sqlitePath)
            && filesize($sqlitePath) !== 0;
        $notes = [
            'Portable package generated on: '.now()->toDateTimeString(),
            'SQLite database included: '.($databaseIncluded ? 'yes' : 'no'),
            'Target start command: start-portable.bat',
            'Target setup command: prepare-portable.bat',
        ];

        $contents = implode(PHP_EOL, $notes).PHP_EOL;
        $notesPath = $packageDirectory.DIRECTORY_SEPARATOR.'PORTABLE-INFO.txt';

        if (file_put_contents($notesPath, $contents) === false) {
            throw new RuntimeException('Unable to write the portable info file.');
        }
    }

    private function createArchive(string $packageDirectory, string $archivePath): void
    {
        $this->components->info('Creating zip archive...');

        $zip = new ZipArchive();
        $opened = $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new RuntimeException('Unable to create the portable zip archive.');
        }

        $packageName = basename($packageDirectory);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $packageDirectory,
                FilesystemIterator::SKIP_DOTS,
            ),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $absolutePath = $item->getPathname();
            $relativePath = $packageName.'/'.ltrim(
                str_replace(
                    str_replace('\\', '/', $packageDirectory),
                    '',
                    str_replace('\\', '/', $absolutePath),
                ),
                '/',
            );

            if ($item->isDir()) {
                $zip->addEmptyDir($relativePath);

                continue;
            }

            $zip->addFile($absolutePath, $relativePath);
        }

        $zip->close();
    }

    private function deleteDirectory(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS,
            ),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            $linkTarget = @readlink($path);
            $isDirectoryLike = $item->isDir()
                || is_dir($path)
                || ($linkTarget !== false && is_dir($linkTarget));

            if ($isDirectoryLike) {
                if (! @rmdir($path)) {
                    throw new RuntimeException('Unable to remove directory `'.$path.'`.');
                }

                continue;
            }

            if (! unlink($path)) {
                throw new RuntimeException('Unable to remove file `'.$path.'`.');
            }
        }

        if (! rmdir($directory)) {
            throw new RuntimeException('Unable to remove directory `'.$directory.'`.');
        }
    }
}
