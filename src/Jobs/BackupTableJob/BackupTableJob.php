<?php

namespace Mrtnsn\BackupManager\Jobs\BackupTableJob;

use Illuminate\Contracts\Filesystem\Factory;
use Symfony\Component\Process\Process;
use Mrtnsn\BackupManager\Exceptions\ProcessException;

trait BackupTableJob
{
    private $tableName;
    private $version;
    private $tag;
    private $limitFromRow;
    private $limitNumberOfRows;

    private $rootFolder;
    private $subFolder;
    private $disk;

    public function __construct($tableName, $version, $tag, $limitFromRow = null, $limitNumberOfRows = null)
    {
        $this->tableName = $tableName;
        $this->version = $version;
        $this->tag = $tag ?: config('backup-manager.tag');
        $this->limitFromRow = $limitFromRow;
        $this->limitNumberOfRows = $limitNumberOfRows;

        $this->rootFolder = config('backup-manager.rootFolder');
        $this->subFolder = config('backup-manager.subFolder');
        $this->disk = config('backup-manager.disk');
    }

    public function handle(Factory $filesystem)
    {
        // Build the command for mysqldump
        $command = $this->buildMysqlDumpCommand();

        // Let Process run the command for us
        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            // Throw a ProcessException and concat all the errors into a single line
            throw new ProcessException($this->removeAllWhitespace(
                $process->getExitCode() . '-' . $process->getExitCodeText() . '-' . $process->getErrorOutput()
            ));
        }

        // Save the file with the output from our command
        $filesystem->disk($this->disk)
            ->put(
                $this->buildFullPath(),
                $process->getOutput(),
                config('backup-manager.visibility')
            );
    }

    private function buildMysqlDumpCommand()
    {
        $cmd = 'mysqldump ' . config('database.connections.mysql.database') .
            ' ' . $this->tableName .
            ' --host=' . config('database.connections.mysql.host') .
            ' --user=' . config('database.connections.mysql.username') .
            ' --password=' . config('database.connections.mysql.password') .
            ' --skip-extended-insert' .
            ' --no-create-info' .
            ' --no-create-db' .
            ' --compact';

        if (!is_null($this->limitFromRow) and !is_null($this->limitNumberOfRows)) {
            $cmd .= ' --where="1 limit ' . $this->limitFromRow . ', ' . $this->limitNumberOfRows . '"';
        }

        return $cmd;
    }

    private function removeAllWhitespace($string)
    {
        return str_replace(array("\r", "\n"), '', $string);
    }

    private function buildFullPath()
    {
        return $this->buildPath() . $this->buildBackupName() . $this->buildFileExtension();
    }

    private function buildPath()
    {
        return $this->rootFolder . '/' . $this->subFolder . '/';
    }

    private function buildBackupName()
    {
        $backupName = $this->tableName;

        if (!is_null($this->limitFromRow) and !is_null($this->limitNumberOfRows)) {
            $backupName .= '_' . $this->limitFromRow . '-' . $this->limitNumberOfRows;
        }

        $backupName .= '_' . $this->tag . '_' . $this->version;

        return $backupName;
    }

    private function buildFileExtension()
    {
        return '.sql';
    }
}
