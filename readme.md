# 💼 Backup Manager for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mrtnsn/backup-manager.svg?style=flat-square)](https://packagist.org/packages/mrtnsn/backup-manager)
[![Total Downloads](https://img.shields.io/packagist/dt/mrtnsn/backup-manager.svg?style=flat-square)](https://packagist.org/packages/mrtnsn/backup-manager)

Easy to use backup manager for Laravel.

**Features**
- Quick installation
- Integration with Laravels queue and filesystem
- Support for multiple Laravel versions (`5.1 => 5.5`)
- Each table is stored in it's own file

## Installation

### Composer
```
composer require mrtnsn/backup-manager
```

### Provider
Add the service provider to `providers` in `config/app.php` so that it's registered
in your Laravel application.

```
Mrtnsn\BackupManager\BackupManagerProvider::class,
```

### Publish
If you need to change the default config you have to publish the config file.

```
php artisan vendor:publish --provider="Mrtnsn\BackupManager\BackupManagerProvider"
```

## Usage

### Commands
The package exposes two artisan commands, one for `export` and one for `import`.

#### Export
Export will use your current credentials and config to export the database
to your desired location.

This command gives no feedback since it's meant to be run from the scheduler.
This prevent filling up log files with unnecessary data.

The command can be execute manually with a custom tag, this can be usefull if multiple developers
are testing different databases on a staging server and need to quickly change between them.

##### Running it from schedule (app/Console/Kernel.php)
```php
$schedule->command('backup-manager:export')
    ->daily();
```

##### Running it manually
```
php artisan backup-manager:export
```

##### Running it manually with custom tag
```
php artisan backup-manager:export --tag=newFeature
```

#### Import
Import will use your current credentials and config to import a selected backup.

This command is built to be run manually as it needs feedback to get the right backup.

You get to choose which `subFolder` and which `version` you want to restore.
After this is selected it will loop through all files matching those parameteres
and drop the table before importing it again.

This will cause minimal downtime as it only affects one table at a time.

##### Running it manually
```
php artisan backup-manager:import
```

## Default config
|Setting|Default|Description|
|---	|---	|---	|
|disk|local|The Laravel filesystems disk to use|
|rootFolder|backup-manager|The root folder where all backups will be stored|
|subFolder|`date('Y-m-d')`|The sub folder all the backups for that instance will be stored in.|
|tag|`env('APP_ENV')`|Tag is added after the table name and before the version (e.g. {table}_{tag}_1, {table}_{tag}_2, etc.)|
|overwrite|`false`|If false the version will be incremented for each new save within the same sub folder. If true only version 1 will exist and each new save within the same sub folder will overwrite the content.|
|visibility|private|Visibility of the file, recommended to leave this as private. For more info take a look at the flysystem API: https://flysystem.thephpleague.com/api/|
|timestampFormat|`Y-m-d H:i:s`|This format is used for displaying the timestamps of imports|
|ignoreTables|`[]`|Tables you want to ignore from the backup.|

## Roadmap
- Tests 
- `backup-manager:inspect` to see files and info about a backup
- Notification system for failed backups
- Support for PostgreSQL