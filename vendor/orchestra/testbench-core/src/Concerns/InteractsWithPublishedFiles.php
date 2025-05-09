<?php

namespace Orchestra\Testbench\Concerns;

use Illuminate\Support\Collection;

use function Orchestra\Sidekick\join_paths;

/**
 * @internal
 */
trait InteractsWithPublishedFiles
{
    /**
     * Determine if trait teardown has been registered.
     *
     * @var bool
     */
    protected bool $interactsWithPublishedFilesTeardownRegistered = false;

    /**
     * List of existing migration files.
     *
     * @var array<int, string>|null
     */
    protected ?array $cachedExistingMigrationsFiles = null;

    /**
     * Setup Interacts with Published Files environment.
     *
     * @internal
     *
     * @return void
     */
    protected function setUpInteractsWithPublishedFiles(): void
    {
        $this->cacheExistingMigrationsFiles();

        $this->cleanUpPublishedFiles();
        $this->cleanUpPublishedMigrationFiles();
    }

    /**
     * Teardown Interacts with Published Files environment.
     *
     * @internal
     *
     * @return void
     */
    protected function tearDownInteractsWithPublishedFiles(): void
    {
        if ($this->interactsWithPublishedFilesTeardownRegistered === false) {
            $this->cleanUpPublishedFiles();
            $this->cleanUpPublishedMigrationFiles();
        }

        $this->interactsWithPublishedFilesTeardownRegistered = true;
    }

    /**
     * Cache existing migration files.
     *
     * @internal
     *
     * @return void
     */
    protected function cacheExistingMigrationsFiles()
    {
        $this->cachedExistingMigrationsFiles ??= Collection::make(
            $this->app['files']->files($this->app->databasePath('migrations'))
        )->filter(static fn ($file) => str_ends_with($file, '.php'))
            ->all();
    }

    /**
     * Assert file does contains data.
     *
     * @api
     *
     * @param  array<int, string>  $contains
     * @return void
     */
    protected function assertFileContains(array $contains, string $file, string $message = ''): void
    {
        $this->assertFilenameExists($file);

        $haystack = $this->app['files']->get(
            $this->app->basePath($file)
        );

        foreach ($contains as $needle) {
            $this->assertStringContainsString($needle, $haystack, $message);
        }
    }

    /**
     * Assert file doesn't contains data.
     *
     * @api
     *
     * @param  array<int, string>  $contains
     * @return void
     */
    protected function assertFileDoesNotContains(array $contains, string $file, string $message = ''): void
    {
        $this->assertFilenameExists($file);

        $haystack = $this->app['files']->get(
            $this->app->basePath($file)
        );

        foreach ($contains as $needle) {
            $this->assertStringNotContainsString($needle, $haystack, $message);
        }
    }

    /**
     * Assert file doesn't contains data.
     *
     * @api
     *
     * @param  array<int, string>  $contains
     * @return void
     */
    protected function assertFileNotContains(array $contains, string $file, string $message = ''): void
    {
        $this->assertFileDoesNotContains($contains, $file, $message);
    }

    /**
     * Assert file does contains data.
     *
     * @api
     *
     * @param  array<int, string>  $contains
     * @return void
     */
    protected function assertMigrationFileContains(array $contains, string $file, string $message = '', ?string $directory = null): void
    {
        $migrationFile = $this->findFirstPublishedMigrationFile($file, $directory);

        $this->assertTrue(! \is_null($migrationFile), "Assert migration file {$file} does exist");

        $haystack = $this->app['files']->get($migrationFile);

        foreach ($contains as $needle) {
            $this->assertStringContainsString($needle, $haystack, $message);
        }
    }

    /**
     * Assert file doesn't contains data.
     *
     * @api
     *
     * @param  array<int, string>  $contains
     * @return void
     */
    protected function assertMigrationFileDoesNotContains(array $contains, string $file, string $message = '', ?string $directory = null): void
    {
        $migrationFile = $this->findFirstPublishedMigrationFile($file, $directory);

        $this->assertTrue(! \is_null($migrationFile), "Assert migration file {$file} does exist");

        $haystack = $this->app['files']->get($migrationFile);

        foreach ($contains as $needle) {
            $this->assertStringNotContainsString($needle, $haystack, $message);
        }
    }

    /**
     * Assert file doesn't contains data.
     *
     * @api
     *
     * @param  array<int, string>  $contains
     * @return void
     */
    protected function assertMigrationFileNotContains(array $contains, string $file, string $message = '', ?string $directory = null): void
    {
        $this->assertMigrationFileDoesNotContains($contains, $file, $message, $directory);
    }

    /**
     * Assert filename exists.
     *
     * @api
     *
     * @param  string  $file
     * @return void
     */
    protected function assertFilenameExists(string $file): void
    {
        $appFile = $this->app->basePath($file);

        $this->assertTrue($this->app['files']->exists($appFile), "Assert file {$file} does exist");
    }

    /**
     * Assert filename not exists.
     *
     * @api
     *
     * @param  string  $file
     * @return void
     */
    protected function assertFilenameDoesNotExists(string $file): void
    {
        $appFile = $this->app->basePath($file);

        $this->assertTrue(! $this->app['files']->exists($appFile), "Assert file {$file} doesn't exist");
    }

    /**
     * Assert filename not exists.
     *
     * @api
     *
     * @param  string  $file
     * @return void
     */
    protected function assertFilenameNotExists(string $file): void
    {
        $this->assertFilenameDoesNotExists($file);
    }

    /**
     * Assert migration filename exists.
     *
     * @api
     *
     * @param  string  $file
     * @param  string|null  $directory
     * @return void
     */
    protected function assertMigrationFileExists(string $file, ?string $directory = null): void
    {
        $migrationFile = $this->findFirstPublishedMigrationFile($file, $directory);

        $this->assertTrue(! \is_null($migrationFile), "Assert migration file {$file} does exist");
    }

    /**
     * Assert migration filename not exists.
     *
     * @api
     *
     * @param  string  $file
     * @param  string|null  $directory
     * @return void
     */
    protected function assertMigrationFileDoesNotExists(string $file, ?string $directory = null): void
    {
        $migrationFile = $this->findFirstPublishedMigrationFile($file, $directory);

        $this->assertTrue(\is_null($migrationFile), "Assert migration file {$file} doesn't exist");
    }

    /**
     * Assert migration filename not exists.
     *
     * @api
     *
     * @param  string  $file
     * @param  string|null  $directory
     * @return void
     */
    protected function assertMigrationFileNotExists(string $file, ?string $directory = null): void
    {
        $this->assertMigrationFileNotExists($file, $directory);
    }

    /**
     * Removes generated files.
     *
     * @internal
     *
     * @return void
     */
    protected function cleanUpPublishedFiles(): void
    {
        $this->app['files']->delete(
            Collection::make($this->files ?? [])
                ->transform(fn ($file) => $this->app->basePath($file))
                ->map(fn ($file) => str_contains($file, '*') ? [...$this->app['files']->glob($file)] : $file)
                ->flatten()
                ->filter(fn ($file) => $this->app['files']->exists($file))
                ->reject(static fn ($file) => str_ends_with($file, '.gitkeep') || str_ends_with($file, '.gitignore'))
                ->all()
        );
    }

    /**
     * Removes generated migration files.
     *
     * @api
     *
     * @param  string  $file
     * @param  string|null  $directory
     * @return void
     */
    protected function findFirstPublishedMigrationFile(string $filename, ?string $directory = null): ?string
    {
        $migrationPath = ! \is_null($directory)
            ? $this->app->basePath($directory)
            : $this->app->databasePath('migrations');

        return $this->app['files']->glob(join_paths($migrationPath, "*{$filename}"))[0] ?? null;
    }

    /**
     * Removes generated migration files.
     *
     * @internal
     *
     * @return void
     */
    protected function cleanUpPublishedMigrationFiles(): void
    {
        $this->app['files']->delete(
            Collection::make($this->app['files']->files($this->app->databasePath('migrations')))
                ->reject(fn ($file) => \in_array($file, $this->cachedExistingMigrationsFiles))
                ->filter(static fn ($file) => str_ends_with($file, '.php'))
                ->all()
        );
    }
}
