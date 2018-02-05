<?php

namespace Mrtnsn\BackupManager\Jobs\BackupTableJob;

use App\Jobs\Job;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;

class Laravel51BackupTableJob extends Job implements SelfHandling, ShouldQueue
{
    use BackupTableJob;
}
