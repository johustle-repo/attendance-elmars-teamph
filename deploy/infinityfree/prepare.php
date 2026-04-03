<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$packageRoot = $projectRoot.DIRECTORY_SEPARATOR.'.infinityfree-deploy';
$htdocsRoot = $packageRoot.DIRECTORY_SEPARATOR.'htdocs';

$requiredPaths = [
    $projectRoot.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php' => 'Run "composer install" before preparing the InfinityFree package.',
    $projectRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'manifest.json' => 'Run "npm run build" before preparing the InfinityFree package.',
];

foreach ($requiredPaths as $path => $message) {
    if (! is_file($path)) {
        fwrite(STDERR, "Missing required file: {$path}\n{$message}\n");
        exit(1);
    }
}

deleteDirectory($packageRoot);
ensureDirectory($htdocsRoot);

$directoriesToCopy = [
    'app',
    'bootstrap',
    'config',
    'database',
    'resources',
    'routes',
    'storage',
    'vendor',
];

foreach ($directoriesToCopy as $directory) {
    copyDirectory(
        $projectRoot.DIRECTORY_SEPARATOR.$directory,
        $htdocsRoot.DIRECTORY_SEPARATOR.$directory,
    );
}

$envSource = resolveEnvSource($projectRoot);
$filesToCopy = [
    $envSource => '.env',
    $projectRoot.DIRECTORY_SEPARATOR.'.env.example' => '.env.example',
    $projectRoot.DIRECTORY_SEPARATOR.'artisan' => 'artisan',
    $projectRoot.DIRECTORY_SEPARATOR.'composer.json' => 'composer.json',
];

if (is_file($projectRoot.DIRECTORY_SEPARATOR.'composer.lock')) {
    $filesToCopy[$projectRoot.DIRECTORY_SEPARATOR.'composer.lock'] = 'composer.lock';
}

foreach ($filesToCopy as $source => $destination) {
    copyFile($source, $htdocsRoot.DIRECTORY_SEPARATOR.$destination);
}

copyDirectory(
    $projectRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build',
    $htdocsRoot.DIRECTORY_SEPARATOR.'build',
);

$publicRoot = $projectRoot.DIRECTORY_SEPARATOR.'public';
$publicSkip = ['.htaccess', 'build', 'hot', 'index.php'];

foreach (scandir($publicRoot) ?: [] as $name) {
    if (in_array($name, ['.', '..'], true) || in_array($name, $publicSkip, true)) {
        continue;
    }

    $source = $publicRoot.DIRECTORY_SEPARATOR.$name;
    $destination = $htdocsRoot.DIRECTORY_SEPARATOR.$name;

    if (is_dir($source)) {
        copyDirectory($source, $destination);
        continue;
    }

    if (is_file($source)) {
        copyFile($source, $destination);
    }
}

copyFile(
    $projectRoot.DIRECTORY_SEPARATOR.'deploy'.DIRECTORY_SEPARATOR.'infinityfree'.DIRECTORY_SEPARATOR.'index.php',
    $htdocsRoot.DIRECTORY_SEPARATOR.'index.php',
);

copyFile(
    $projectRoot.DIRECTORY_SEPARATOR.'deploy'.DIRECTORY_SEPARATOR.'infinityfree'.DIRECTORY_SEPARATOR.'.htaccess',
    $htdocsRoot.DIRECTORY_SEPARATOR.'.htaccess',
);

$oversizedFiles = findOversizedFiles($htdocsRoot);

echo "InfinityFree package created.\n";
echo "Package path: {$htdocsRoot}\n";
echo "Environment source: {$envSource}\n";
echo "Upload the contents of this htdocs folder to your InfinityFree htdocs directory.\n";

if ($envSource === $projectRoot.DIRECTORY_SEPARATOR.'deploy'.DIRECTORY_SEPARATOR.'infinityfree'.DIRECTORY_SEPARATOR.'.env.infinityfree.example') {
    echo "Warning: the package is using the example InfinityFree environment file. Replace the packaged .env values before upload.\n";
} elseif ($envSource === $projectRoot.DIRECTORY_SEPARATOR.'.env') {
    echo "Note: the package copied your current project .env file. Double-check the database and APP_URL values before upload.\n";
}

if ($oversizedFiles !== []) {
    echo "\nInfinityFree size warnings:\n";

    foreach ($oversizedFiles as $warning) {
        echo "- {$warning}\n";
    }

    echo "Recommended next step: run \"composer install --no-dev --optimize-autoloader --working-dir=.infinityfree-deploy/htdocs\" against the generated package, then upload that package.\n";
    exit(1);
}

exit(0);

function resolveEnvSource(string $projectRoot): string
{
    $candidates = [
        $projectRoot.DIRECTORY_SEPARATOR.'deploy'.DIRECTORY_SEPARATOR.'infinityfree'.DIRECTORY_SEPARATOR.'.env.infinityfree',
        $projectRoot.DIRECTORY_SEPARATOR.'.env',
        $projectRoot.DIRECTORY_SEPARATOR.'deploy'.DIRECTORY_SEPARATOR.'infinityfree'.DIRECTORY_SEPARATOR.'.env.infinityfree.example',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    fwrite(STDERR, "No environment file was found for packaging.\n");
    exit(1);
}

function findOversizedFiles(string $root): array
{
    $warnings = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $path = $file->getPathname();
        $relativePath = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
        $size = $file->getSize();
        $extension = strtolower($file->getExtension());

        if (in_array($extension, ['php', 'html', 'js'], true) && $size > 1024 * 1024) {
            $warnings[] = sprintf("%s is %.2f MB, which is above InfinityFree's 1 MB file limit.", $relativePath, $size / 1024 / 1024);
        }

        if ($file->getFilename() === '.htaccess' && $size > 10 * 1024) {
            $warnings[] = sprintf("%s is %.2f KB, which is above InfinityFree's 10 KB .htaccess limit.", $relativePath, $size / 1024);
        }
    }

    return $warnings;
}

function copyDirectory(string $source, string $destination): void
{
    if (! is_dir($source)) {
        fwrite(STDERR, "Missing required directory: {$source}\n");
        exit(1);
    }

    ensureDirectory($destination);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $item) {
        $target = $destination.DIRECTORY_SEPARATOR.$iterator->getSubPathName();

        if ($item->isDir()) {
            ensureDirectory($target);
            continue;
        }

        copyFile($item->getPathname(), $target);
    }
}

function copyFile(string $source, string $destination): void
{
    if (! is_file($source)) {
        fwrite(STDERR, "Missing required file: {$source}\n");
        exit(1);
    }

    ensureDirectory(dirname($destination));

    if (! copy($source, $destination)) {
        fwrite(STDERR, "Failed to copy {$source} to {$destination}\n");
        exit(1);
    }
}

function deleteDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
            continue;
        }

        unlink($item->getPathname());
    }

    rmdir($directory);
}

function ensureDirectory(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    if (! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        fwrite(STDERR, "Failed to create directory: {$directory}\n");
        exit(1);
    }
}