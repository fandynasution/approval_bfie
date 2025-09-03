<?php

namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class CbModuleService
{
    public function getDetails(string $type, string $entity_cd, string $doc_no, string $trx_type = null): Collection
    {
        switch($type) {
            case 'E': return $this->getCbFupdDetails($entity_cd, $doc_no);
            case 'D': return $this->getCbRpbDetails($entity_cd, $doc_no);
            case 'G': return $this->getCbRumDetails($entity_cd, $doc_no);
            case 'U': return $this->getCbPpuDetails($entity_cd, $doc_no, $trx_type);
            case 'V': return $this->getCbPpuDetails($entity_cd, $doc_no, $trx_type);
            default: return collect([]);
        }
    }

    private function getCbFupdDetails($entity_cd, $doc_no)
    {
        try {
            return DB::connection('BFIE')
                ->table('mgr.cb_pay_trx_bank_hd as hd')
                ->join('mgr.cb_pay_trx_bank_dt as dt', function($join) {
                    $join->on('hd.entity_cd', '=', 'dt.entity_cd')
                        ->on('hd.doc_no', '=', 'dt.doc_no');
                })
                ->select('hd.descs', 'hd.doc_no', 'dt.amount')
                ->where('hd.entity_cd',$entity_cd)
                ->where('hd.doc_no',$doc_no)
                ->get();
        } catch (\Exception $e) {
            \Log::error('getCbFupdDetails error: '.$e->getMessage());
            return collect([]);
        }
    }

    private function getCbRpbDetails($entity_cd, $doc_no)
    {
        try {
            return DB::connection('BFIE')
                ->table('mgr.cb_pay_trx_rpb_hd as rpbhd')
                ->select(
                    DB::raw("REPLACE(REPLACE(REPLACE(REPLACE(rpbhd.descs, CHAR(10), ''), CHAR(9), ''), CHAR(13), ''), '\"', '') as descs"),
                    'rpbhd.currency_cd', 
                    'rpbhd.trx_amt', 
                    'rpbhd.doc_no')
                ->where('rpbhd.entity_cd',$entity_cd)
                ->where('rpbhd.doc_no',$doc_no)
                ->get();
        } catch (\Exception $e) {
            \Log::error('getCbRpbDetails error: '.$e->getMessage());
            return collect([]);
        }
    }

    private function getCbRumDetails($entity_cd, $doc_no)
    {
        try {
            return DB::connection('BFIE')
                ->table('mgr.cb_cash_replenish_hd as rumhd')
                ->select(
                    DB::raw("REPLACE(REPLACE(REPLACE(REPLACE(rumhd.descs, CHAR(10), ''), CHAR(9), ''), CHAR(13), ''), '\"', '') as descs"),
                    'rumhd.currency_cd', 
                    'rumhd.total_amt')
                ->where('rumhd.entity_cd',$entity_cd)
                ->where('rumhd.replenish_doc',$doc_no)
                ->get();
        } catch (\Exception $e) {
            \Log::error('getCbRumDetails error: '.$e->getMessage());
            return collect([]);
        }
    }

    private function getCbPpuDetails($entity_cd, $doc_no, $trx_type)
    {
        try {
            return DB::connection('BFIE')
                ->table('mgr.cb_ppu_ldg as ldg')
                ->select(
                    'ldg.forex',
                    DB::raw("COALESCE(REPLACE(REPLACE(REPLACE(NULLIF(ldg.pay_to, ''), CHAR(10), ''), CHAR(9), ''), CHAR(13), ''), 'EMPTY') as pay_to"),
                    'ldg.ppu_amt',
                    'ldg.ppu_no',
                    DB::raw("COALESCE(REPLACE(REPLACE(REPLACE(NULLIF(ldg.document_link, ''), CHAR(10), ''), CHAR(9), ''), CHAR(13), ''), 'EMPTY') as document_link"),
                    DB::raw("REPLACE(REPLACE(REPLACE(REPLACE(ldg.descs, CHAR(10), ''), CHAR(9), ''), CHAR(13), ''), '\"', '') as descs")
                )
                ->where('ldg.entity_cd',$entity_cd)
                ->where('ldg.ppu_no',$doc_no)
                ->where('ldg.trx_type',$trx_type)
                ->get();
        } catch (\Exception $e) {
            \Log::error('getCbPpuDetails error: '.$e->getMessage());
            return collect([]);
        }
    }
}
