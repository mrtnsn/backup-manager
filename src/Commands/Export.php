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
    private $chunkSize;

    public function __construct(DatabaseManager $databaseManager, Factory $filesystem)
    {
        $this->databaseManager = $databaseManager;
        $this->filesystem = $filesystem;

        $this->disk = config('backup-manager.disk');
        $this->chunkSize = (float) config('backup-manager.chunkSize');

        parent::__construct();
    }

    public function handle()
    {
        // Build the MySQL query
        $sql = $this->buildSqlQuery();

        // Get all the tables from the database
        $tables = $this->databaseManager->select($sql);

        // Check if a version.txt file exists, if not create it.
        // Retrieve the version form the file and increment it, then save the file.
        $version = (int) $this->getAndUpdateVersion();

        list($major, $minor) = explode('.', app()->version());
        $backupTableClass = '\Mrtnsn\BackupManager\Jobs\BackupTableJob\Laravel' . $major . $minor . 'BackupTableJob';
        $backupSchemaClass = '\Mrtnsn\BackupManager\Jobs\BackupSchemaJob\Laravel' . $major . $minor . 'BackupSchemaJob';

        dispatch(new $backupSchemaClass($version, $this->option('tag')));

        // Loop through each of the tables and pass it to the BackupTableJob, this job should queue by default.
        foreach ($tables as $key => $table) {
            // Check if the table is larger than the defined chunk size
            if ((float) $table->table_size > $this->chunkSize) {
                // Calculates the size per row in the database by dividing the size by the number of rows
                $sizePerRow = $table->table_size / $table->table_rows;
                // Calculates how many rows fit within a given chunk
                $numberOfRowsPerChunk = (int) floor($this->chunkSize / $sizePerRow);
                // Calculates the number of chunks
                $numberOfChunks = (int) ceil($table->table_rows / $numberOfRowsPerChunk);

                // Iterate over $numberOfChunks
                for ($i = 1; $i <= $numberOfChunks; $i++) {
                    // Calculate the first parameter of MYSQL LIMIT X, Y
                    $limitFromRow = ($i - 1) * $numberOfRowsPerChunk;
                    // Set the number of rows to collect, the second parameter of MYSQL LIMIT X, Y
                    $limitNumberOfRows = $numberOfRowsPerChunk;

                    // Dispatch the job corresponding to the correct Laravel version
                    dispatch(new $backupTableClass(
                        $table->table_name,
                        $version,
                        $this->option('tag'),
                        $limitFromRow,
                        $limitNumberOfRows
                    ));
                }
            } else {
                // Dispatch the job corresponding to the correct Laravel version
                dispatch(new $backupTableClass($table->table_name, $version, $this->option('tag')));
            }
        }
    }

    private function buildSqlQuery()
    {
        // Basic select to get the table_name's from the database
        $sql = 'SELECT
            table_name, 
            table_rows,
            round(((data_length + index_length) / 1024 / 1024), 2) `table_size` 
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
