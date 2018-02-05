<?php

namespace Mrtnsn\BackupManager\Jobs\BackupTableJob;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class Laravel55BackupTableJob implements ShouldQueue
{
    use BackupTableJob, InteractsWithQueue, Queueable, SerializesModels;
}
