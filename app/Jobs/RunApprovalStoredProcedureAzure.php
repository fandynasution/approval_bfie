<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RunApprovalStoredProcedureAzure implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $entity_cd;
    protected $doc_no;
    protected $type;
    protected $module;
    protected $level_no;
    protected $encryptedData;
    protected $app_url;

    /**
     * Create a new job instance.
     */
    public function __construct($entity_cd, $doc_no, $type, $module, $level_no, $encryptedData, $app_url)
    {
        $this->entity_cd     = $entity_cd;
        $this->doc_no        = $doc_no;
        $this->type          = $type;
        $this->module        = $module;
        $this->level_no      = $level_no;
        $this->encryptedData = $encryptedData;
        $this->app_url       = $app_url;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            DB::connection('BFIE')->statement("
                SET NOCOUNT ON; 
                EXEC mgr.x_send_mail_approval_azure_ins ?, ?, ?, ?, ?, ?, ?
            ", [
                $this->entity_cd,
                $this->doc_no,
                $this->type,
                $this->module,
                $this->level_no,
                $this->encryptedData,
                $this->app_url
            ]);

            Log::channel('sendmailapproval')->info("Stored procedure berhasil dijalankan untuk doc_no {$this->doc_no} entity {$this->entity_cd}");
        } catch (\Exception $e) {
            Log::channel('sendmailapproval')->error("Error jalankan SP doc_no {$this->doc_no}: ".$e->getMessage());
        }
    }
}
