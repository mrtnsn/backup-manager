<?php

namespace Mrtnsn\BackupManager\Jobs\BackupSchemaJob;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class Laravel55BackupSchemaJob implements ShouldQueue
{
    use BackupSchemaJob, InteractsWithQueue, Queueable, SerializesModels;
}
