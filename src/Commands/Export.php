<?php

namespace Mrtnsn\BackupManager\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Database\DatabaseManager;

class Export extends Command
{
    protected $signature = 'backup-manager:export 
                            {--tag= : Manually overide what the tag should be for this export}';
    protected $description = 'Takes a backup of your database and stores it on your preferred filesystem';

    private $databaseManager;
    private $filesystem;
    private $disk;

    public function __construct(DatabaseManager $databaseManager, Factory $filesystem)
    {
        $this->databaseManager = $databaseManager;
        $this->filesystem = $filesystem;

        $this->disk = config('backup-manager.disk');

        parent::__construct();
    }

    public function handle()
    {
        // Build the MySQL query
        $sql = $this->buildSqlQuery();

        // Get all the tables from the database
        $tables = $this->databaseManager->select($sql);

        // Check if a version.txt file exists, if not create it.
        // Retrive the version form the file and increment it, then save the file.
        $version = $this->getAndUpdateVersion();

        // Loop through each of the tables and pass it to the BackupTableJob, this job should queue by default.
        foreach ($tables as $key => $table) {
            list($major, $minor) = explode('.', app()->version());
            $className = '\Mrtnsn\BackupManager\Jobs\Laravel' . $major . $minor . 'BackupTableJob';

            dispatch(new $className($table->table_name, (int) $version, $this->option('tag')));
        }
    }

    private function buildSqlQuery()
    {
        // Basic select to get the table_name's from the database
        $sql = 'SELECT 
             table_name 
            FROM 
             information_schema.tables
            WHERE 
             table_schema = DATABASE()';

        // If there are some table to ignore append it to the sql query
        if (config('backup-manager.ignoreTables')) {
            $sql .= '
            AND
             table_name NOT IN ("'. implode('","', config('backup-manager.ignoreTables')) .'")';
        }

        // Return the raw query for execution
        return $sql;
    }

    private function getAndUpdateVersion()
    {
        if (!config('backup-manager.overwrite') and $this->versionFileExists()) {
            $currentVersion = $this->getCurrentVersion();
            $version = (int)$currentVersion + 1;
        } else {
            $version = '1';
        }

        $this->saveNewVersion($version);

        return $version;
    }

    private function versionFileExists()
    {
        return $this->filesystem
            ->disk($this->disk)
            ->has($this->versionPath());
    }

    private function getCurrentVersion()
    {
        return $this->filesystem
            ->disk($this->disk)
            ->get($this->versionPath());
    }

    private function saveNewVersion($version)
    {
        $this->filesystem
            ->disk($this->disk)
            ->put(
                $this->versionPath(),
                (string) $version,
                config('backup-manager.visibility')
            );
    }

    private function versionPath()
    {
        return config('backup-manager.rootFolder') . '/' . config('backup-manager.subFolder') . '/version.txt';
    }
}
