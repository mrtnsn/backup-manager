<?php

namespace Mrtnsn\BackupManager\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Database\DatabaseManager;
use League\Flysystem\Plugin\ListWith;

class Import extends Command
{
    protected $signature = 'backup-manager:import';
    protected $description = 'Overwrite your database with a previous backup';

    private $databaseManager;
    private $filesystem;
    private $carbon;

    private $disk;

    public function __construct(DatabaseManager $databaseManager, Factory $filesystem, Carbon $carbon)
    {
        $this->databaseManager = $databaseManager;
        $this->filesystem = $filesystem;

        $this->disk = config('backup-manager.disk');

        parent::__construct();
        $this->carbon = $carbon;
    }

    public function handle()
    {
        // Adds the ListWith plugin to the selected disk for this instance
        $this->filesystem->disk($this->disk)->addPlugin(new ListWith());

        // Inform about which database is going to be affected
        $this->warn(
            'This will remove all data and tables in ' . '"' .
            config('database.connections.mysql.database') . '"'
        );

        // The user must manually confirm
        if (!$this->confirm('Do you wish to continue? [y|N]')) {
            $this->info('Database left intact');
            return;
        }

        $this->info('Retrieving backups');

        // Get alle the directories from the root folder
        $backupFolders = $this->getRootDirectories();

        // Let the user decide which to import
        $folderToImport = $this->choice(
            'Which backup do you want to restore?',
            $backupFolders
        );

        $this->info('Retrieving versions');

        // Get the versions found within the selected folder
        $versions = $this->getVersionsFromSelectedFolder($folderToImport);

        $this->table(
            [
                'Version',
                'Tag',
                'Modified'
            ],
            $versions->map(function ($version) {
                return [
                    $version['version'],
                    $version['tag'],
                    $this->carbon
                        ->createFromTimestamp($version['modified'])
                        ->format(config('backup-manager.timestampFormat'))
                ];
            })
                ->toArray()
        );

        // Display the versions to the user and let the user decide which version to import
        $versionToRestore = $this->choice(
            'Which version do you want to restore?',
            $versions->pluck('version')->toArray()
        );

        // Turn the filename's into an array
        $filesToImport = $this->getFilesToImport($versions, $versionToRestore);

        $schema = $this->getSchemaFromChosenVersion($versions, $versionToRestore);

        $this->importSchema($schema, $folderToImport);

        // Progress bar for user feedback
        $bar = $this->output->createProgressBar(count($filesToImport));

        // Loop through each file and import it
        $this->importFiles($filesToImport, $folderToImport, $bar);

        // Finish the bar
        $bar->finish();

        // Inform the user of success, add EOL to start and end so that the message isn't printed on the same line
        // as the progress bar
        $this->info(PHP_EOL . 'Database imported' . PHP_EOL);
    }

    private function getRootDirectories()
    {
        // Return all directories we find in the root folder
        return $this->filesystem->disk($this->disk)->allDirectories(config('backup-manager.rootFolder'));
    }

    private function getVersionsFromSelectedFolder($folderToImport)
    {
        // Make a collection of all found files in the chosen folder
        return collect($this->filesystem->disk($this->disk)->listWith(['timestamp'], $folderToImport))
            ->filter(function ($file) {
                // Remove the version.txt file that we generate
                return $file['filename'] !== 'version';
            })
            ->filter(function ($file) {
                // Remove empty files
                return $file['size'] !== 0;
            })
            ->groupBy(function ($file) {
                // Group by the version number with should be the last entry when exploded on _
                $fileChunks = explode('_', $file['filename']);
                return $fileChunks[count($fileChunks) - 1];
            })
            ->transform(function ($file, $key) {
                // Get chunks
                $fileChunks = explode('_', $file->first()['filename']);

                // Modify the collection to only include the data we need
                return [
                    'version' => $key,
                    'schema' => $this->getSchema($file, $key, $fileChunks),
                    'filenames' => $this->getFilenames($file, $key, $fileChunks),
                    'modified' => $this->getModified($file),
                    'tag' => $fileChunks[count($fileChunks) - 2]
                ];
            });
    }

    private function getFilesToImport($versions, $versionToRestore)
    {
        // Find the first entry matching the chosen version and return the filenames array
        foreach ($versions as $version) {
            // All user input from the console is a string so we typecast it back to a int
            if ($version['version'] === (int)$versionToRestore) {
                return $version['filenames'];
            }
        }
    }

    private function importSchema($schema, $folderToImport)
    {
        // Build the full path
        $fullPathToRestore = $folderToImport . '/' . $schema . '.sql';

        $this->databaseManager->beginTransaction();
        $this->databaseManager->unprepared($this->filesystem->disk($this->disk)->get($fullPathToRestore));
        $this->databaseManager->commit();
    }

    private function importFiles($filesToImport, $folderToImport, $bar)
    {
        foreach ($filesToImport as $fileToImport) {
            // Build the full path
            $fullPathToRestore = $folderToImport . '/' . $fileToImport . '.sql';

            // Begin a database transaction
            $this->databaseManager->beginTransaction();

            // Set the user defined global settings for mysql
            foreach (config('backup-manager.mysqlGlobalSettings') as $globalSetting) {
                $this->databaseManager->statement($globalSetting);
            }

            // Insert the data from the file
            $this->databaseManager->unprepared($this->filesystem->disk($this->disk)->get($fullPathToRestore));

            // Commit the changes to the database
            $this->databaseManager->commit();

            // Increment the bar
            $bar->advance();
        };
    }

    private function getSchemaFromChosenVersion($versions, $versionToRestore)
    {
        foreach ($versions as $version) {
            if ($version['version'] === (int) $versionToRestore) {
                return $version['schema'];
            }
        }
    }

    private function getSchema($file, $key, $fileChunks)
    {
        return $file->filter(function ($file) use ($key, $fileChunks) {
            return $file['filename'] === config('database.connections.mysql.database') .
                '_schema_' .
                $fileChunks[count($fileChunks) - 2] .
                '_' .
                $key;
        })->first()['filename'];
    }

    private function getFilenames($file, $key, $fileChunks)
    {
        return $file
            ->map(function ($file) {
                return $file['filename'];
            })
            ->filter(function ($file) use ($key, $fileChunks) {
                return $file !== config('database.connections.mysql.database') .
                    '_schema_' .
                    $fileChunks[count($fileChunks) - 2] .
                    '_' .
                    $key;
            });
    }

    private function getModified($file)
    {
        return $file->avg(function ($file) {
            return $file['timestamp'];
        });
    }
}
