<?php

namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class CmModuleService
{
    public function getDetails(string $type, string $entity_cd, string $doc_no, string $trx_type = null): Collection
    {
        switch($type) {
            case 'E': return $this->getCmEntryDetails($entity_cd, $doc_no);
            default: return collect([]);
        }
    }

    private function getCmEntryDetails($entity_cd, $doc_no)
    {
        try {
            return DB::connection('BFIE')
                ->table('mgr.pl_contract as plc')
                ->select(
                    DB::raw("COALESCE(NULLIF(plc.works_descs, ''), 'No Work Description')"),
                    'plc.contract_no', 
                    'plc.currency_cd', 
                    'plc.contract_amt',
                    'plc.auth_vo',
                    DB::raw("COALESCE(REPLACE(REPLACE(REPLACE(NULLIF(plc.url_link, ''),CHAR(10), ''),CHAR(9), ''),CHAR(13), ''),'EMPTY')")
                )
                ->where('plc.entity_cd',$entity_cd)
                ->where('plc.contract_no',$doc_no)
                ->get();
        } catch (\Exception $e) {
            \Log::error('getCmEntryDetails error: '.$e->getMessage());
            return collect([]);
        }
    }
}
