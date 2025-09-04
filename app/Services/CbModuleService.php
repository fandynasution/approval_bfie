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
            $results = DB::connection('BFIE')
                ->table('mgr.cb_pay_trx_bank_hd as hd')
                ->join('mgr.cb_pay_trx_bank_dt as dt', function($join) {
                    $join->on('hd.entity_cd', '=', 'dt.entity_cd')
                        ->on('hd.doc_no', '=', 'dt.doc_no');
                })
                ->select(
                    'hd.descs',
                    'hd.doc_no',
                    'dt.amount',
                    DB::raw("
                        ISNULL((
                            SELECT STRING_AGG(
                                CASE
                                    WHEN url_file_attachment IS NULL THEN 'EMPTY'
                                    WHEN CHARINDEX('http', url_file_attachment) = 0 THEN 'EMPTY'
                                    ELSE url_file_attachment
                                END, ';'
                            )
                            FROM mgr.cb_fupd_file_attach WITH (NOLOCK)
                            WHERE entity_cd = '".addslashes($entity_cd)."' 
                            AND doc_no = '".addslashes($doc_no)."'
                        ), 'EMPTY') as url_file
                    "),
                    DB::raw("
                        ISNULL((
                            SELECT STRING_AGG(
                                CASE
                                    WHEN file_name IS NULL THEN 'EMPTY'
                                    ELSE file_name
                                END, ';'
                            )
                            FROM mgr.cb_fupd_file_attach WITH (NOLOCK)
                            WHERE entity_cd = '".addslashes($entity_cd)."' 
                            AND doc_no = '".addslashes($doc_no)."'
                        ), 'EMPTY') as file_name
                    ")
                )
                ->where('hd.entity_cd', $entity_cd)
                ->where('hd.doc_no', $doc_no)
                ->get();

            // ubah hasil string menjadi array
            $results->transform(function ($item) {
                $item->url_file = $item->url_file === 'EMPTY'
                    ? []
                    : explode(';', $item->url_file);

                $item->file_name = $item->file_name === 'EMPTY'
                    ? []
                    : explode(';', $item->file_name);

                return $item;
            });

            return $results;
        } catch (\Exception $e) {
            \Log::error('getCbFupdDetails error: '.$e->getMessage());
            return collect([]);
        }
    }

    private function getCbRpbDetails($entity_cd, $doc_no)
    {
        try {
            $results = DB::connection('BFIE')
                ->table('mgr.cb_pay_trx_rpb_hd as rpbhd')
                ->select(
                    DB::raw("REPLACE(REPLACE(REPLACE(REPLACE(rpbhd.descs, CHAR(10), ''), CHAR(9), ''), CHAR(13), ''), '\"', '') as descs"),
                    'rpbhd.currency_cd', 
                    'rpbhd.trx_amt', 
                    'rpbhd.doc_no',
                    DB::raw("
                        ISNULL((
                            SELECT STRING_AGG(
                                CASE
                                    WHEN url_file_attachment IS NULL THEN 'EMPTY'
                                    WHEN CHARINDEX('http', url_file_attachment) = 0 THEN 'EMPTY'
                                    ELSE url_file_attachment
                                END, ';'
                            )
                            FROM mgr.cb_rpb_file_attach WITH (NOLOCK)
                            WHERE entity_cd = '".addslashes($entity_cd)."' 
                            AND doc_no = '".addslashes($doc_no)."'
                        ), 'EMPTY') as url_file
                    "),
                    DB::raw("
                        ISNULL((
                            SELECT STRING_AGG(
                                CASE
                                    WHEN file_name IS NULL THEN 'EMPTY'
                                    ELSE file_name
                                END, ';'
                            )
                            FROM mgr.cb_rpb_file_attach WITH (NOLOCK)
                            WHERE entity_cd = '".addslashes($entity_cd)."' 
                            AND doc_no = '".addslashes($doc_no)."'
                        ), 'EMPTY') as file_name
                    ")
                    )
                ->where('rpbhd.entity_cd',$entity_cd)
                ->where('rpbhd.doc_no',$doc_no)
                ->get();

                // ubah hasil string menjadi array
                $results->transform(function ($item) {
                    $item->url_file = $item->url_file === 'EMPTY'
                        ? []
                        : explode(';', $item->url_file);
    
                    $item->file_name = $item->file_name === 'EMPTY'
                        ? []
                        : explode(';', $item->file_name);
    
                    return $item;
                });
    
                return $results;
        } catch (\Exception $e) {
            \Log::error('getCbRpbDetails error: '.$e->getMessage());
            return collect([]);
        }
    }

    private function getCbRumDetails($entity_cd, $doc_no)
    {
        try {
            $results = DB::connection('BFIE')
                ->table('mgr.cb_cash_replenish_hd as rumhd')
                ->select(
                    DB::raw("REPLACE(REPLACE(REPLACE(REPLACE(rumhd.descs, CHAR(10), ''), CHAR(9), ''), CHAR(13), ''), '\"', '') as descs"),
                    'rumhd.currency_cd', 
                    'rumhd.total_amt',
                    DB::raw("
                        ISNULL((
                            SELECT STRING_AGG(
                                CASE
                                    WHEN url_file_attachment IS NULL THEN 'EMPTY'
                                    WHEN CHARINDEX('http', url_file_attachment) = 0 THEN 'EMPTY'
                                    ELSE url_file_attachment
                                END, ';'
                            )
                            FROM mgr.cb_rum_file_attach WITH (NOLOCK)
                            WHERE entity_cd = '".addslashes($entity_cd)."' 
                            AND doc_no = '".addslashes($doc_no)."'
                        ), 'EMPTY') as url_file
                    "),
                    DB::raw("
                        ISNULL((
                            SELECT STRING_AGG(
                                CASE
                                    WHEN file_name IS NULL THEN 'EMPTY'
                                    ELSE file_name
                                END, ';'
                            )
                            FROM mgr.cb_rum_file_attach WITH (NOLOCK)
                            WHERE entity_cd = '".addslashes($entity_cd)."' 
                            AND doc_no = '".addslashes($doc_no)."'
                        ), 'EMPTY') as file_name
                    ")
                )
                ->where('rumhd.entity_cd',$entity_cd)
                ->where('rumhd.replenish_doc',$doc_no)
                ->get();

                // ubah hasil string menjadi array
                $results->transform(function ($item) {
                    $item->url_file = $item->url_file === 'EMPTY'
                        ? []
                        : explode(';', $item->url_file);
    
                    $item->file_name = $item->file_name === 'EMPTY'
                        ? []
                        : explode(';', $item->file_name);
    
                    return $item;
                });
    
                return $results;
        } catch (\Exception $e) {
            \Log::error('getCbRumDetails error: '.$e->getMessage());
            return collect([]);
        }
    }

    private function getCbPpuDetails($entity_cd, $doc_no, $trx_type)
    {
        try {
            $results = DB::connection('BFIE')
                ->table('mgr.cb_ppu_ldg as ldg')
                ->select(
                    'ldg.forex',
                    DB::raw("COALESCE(REPLACE(REPLACE(REPLACE(NULLIF(ldg.pay_to, ''), CHAR(10), ''), CHAR(9), ''), CHAR(13), ''), 'EMPTY') as pay_to"),
                    'ldg.ppu_amt',
                    'ldg.ppu_no',
                    DB::raw("COALESCE(REPLACE(REPLACE(REPLACE(NULLIF(ldg.document_link, ''), CHAR(10), ''), CHAR(9), ''), CHAR(13), ''), 'EMPTY') as document_link"),
                    DB::raw("REPLACE(REPLACE(REPLACE(REPLACE(ldg.descs, CHAR(10), ''), CHAR(9), ''), CHAR(13), ''), '\"', '') as descs"),
                    DB::raw("
                        ISNULL((
                            SELECT STRING_AGG(
                                CASE
                                    WHEN url_file_attachment IS NULL THEN 'EMPTY'
                                    WHEN CHARINDEX('http', url_file_attachment) = 0 THEN 'EMPTY'
                                    ELSE url_file_attachment
                                END, ';'
                            )
                            FROM mgr.cb_ppu_file_attach WITH (NOLOCK)
                            WHERE entity_cd = '".addslashes($entity_cd)."' 
                            AND doc_no = '".addslashes($doc_no)."'
                        ), 'EMPTY') as url_file
                    "),
                    DB::raw("
                        ISNULL((
                            SELECT STRING_AGG(
                                CASE
                                    WHEN file_name IS NULL THEN 'EMPTY'
                                    ELSE file_name
                                END, ';'
                            )
                            FROM mgr.cb_ppu_file_attach WITH (NOLOCK)
                            WHERE entity_cd = '".addslashes($entity_cd)."' 
                            AND doc_no = '".addslashes($doc_no)."'
                        ), 'EMPTY') as file_name
                    ")
                )
                ->where('ldg.entity_cd',$entity_cd)
                ->where('ldg.ppu_no',$doc_no)
                ->where('ldg.trx_type',$trx_type)
                ->get();

            // ubah hasil string menjadi array
            $results->transform(function ($item) {
                $item->url_file = $item->url_file === 'EMPTY'
                    ? []
                    : explode(';', $item->url_file);

                $item->file_name = $item->file_name === 'EMPTY'
                    ? []
                    : explode(';', $item->file_name);

                return $item;
            });

            return $results;
        } catch (\Exception $e) {
            \Log::error('getCbPpuDetails error: '.$e->getMessage());
            return collect([]);
        }
    }
}
