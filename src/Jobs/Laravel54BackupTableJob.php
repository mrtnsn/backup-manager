<?php

namespace Mrtnsn\BackupManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class Laravel54BackupTableJob implements ShouldQueue
{
    use BackupTableJob, InteractsWithQueue, Queueable, SerializesModels;
}
