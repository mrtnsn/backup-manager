<?php

namespace Mrtnsn\BackupManager\Jobs\BackupSchemaJob;

use App\Jobs\Job;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;

class Laravel51BackupSchemaJob extends Job implements SelfHandling, ShouldQueue
{
    use BackupSchemaJob;
}
