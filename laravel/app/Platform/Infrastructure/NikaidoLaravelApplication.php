<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure;

use Illuminate\Foundation\Application;

final class NikaidoLaravelApplication extends Application
{
    public function __construct($basePath = null)
    {
        parent::__construct($basePath);

        $this->useAppPath($this->basePath('laravel/app'));
        $this->useBootstrapPath($this->basePath('laravel/bootstrap'));
        $this->useConfigPath($this->basePath('laravel/config'));
        $this->useDatabasePath($this->basePath('laravel/database'));
        $this->useLangPath($this->basePath('laravel/lang'));
        $this->usePublicPath($this->basePath('laravel/public'));
        $this->useStoragePath($this->basePath('laravel/storage'));
        $this->useEnvironmentPath($this->basePath());
    }

    public function resourcePath($path = ''): string
    {
        return $this->basePath('laravel/resources'.($path !== '' ? '/'.$path : ''));
    }
}
