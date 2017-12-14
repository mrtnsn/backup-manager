<?php

namespace Mrtnsn\BackupManager;

use Illuminate\Support\ServiceProvider;
use Mrtnsn\BackupManager\Commands\Export;
use Mrtnsn\BackupManager\Commands\Import;

class BackupManagerProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes(
            [
                __DIR__ . '/../publishes/config/backup-manager.php' => config_path('backup-manager.php'),
            ],
            'config'
        );
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../publishes/config/backup-manager.php',
            'backup-manager'
        );

        $this->commands([
            Export::class,
            Import::class
        ]);
    }
}
