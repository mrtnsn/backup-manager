<?php

return [
    // The filesystems disk to use, see config/filesystems.php
    'disk' => 'local',

    // The root folder where all backups will be stored.
    // E.g. for a default Laravel app this will be /storage/app/backup-manager
    'rootFolder' => 'backup-manager',

    // The sub folder all the backups for that instance will be stored in.
    // By using a date with day you will get a folder for each day
    'subFolder' => date('Y-m-d'),

    // Tag is added after the table name and before the version (e.g. {table}_{tag}_1, {table}_{tag}_2, etc.)
    // Recommend using your environment for this tag, e.g. production, local, etc.
    'tag' => env('APP_ENV'),

    // If false the version will be incremented for each new save within the same sub folder.
    // If true only version 1 will exist and each new save within the same sub folder will overwrite the content.
    'overwrite' => false,

    // Visibility of the file, recommended to leave this as private.
    // For more info take a look at the flysystem API: https://flysystem.thephpleague.com/api/
    'visibility' => 'private',

    // This format is used for displaying the timestamps of imports, depending on what interval
    // your backups are created you might only need to use Y-m-d og some other configuration
    'timestampFormat' => 'Y-m-d H:i:s',

    // Tables you want to ignore from the backup.
    // Every table you list here will be ignored and will be left untouched by the import.
    'ignoreTables' => [],

    // The package divides each tables data into chunks, this is maximal chunk size in MB
    // 50 MB is a sensible default, but this may wary depending on your server setup
    // Must be an integer, float or something that handles typecasting to float
    'chunkSize' => 5
];
