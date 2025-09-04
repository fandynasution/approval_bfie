<?php

namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class CmModuleService
{
    public function getDetails(string $type, string $entity_cd, string $doc_no, string $ref_no = null): Collection
    {
        switch($type) {
            case 'A': return $this->getCmProgressDetails($entity_cd, $doc_no, $ref_no);
            case 'D': return $this->getCmProgressDetails($entity_cd, $doc_no, $ref_no);
            case 'E': return $this->getCmEntryDetails($entity_cd, $doc_no);
            default: return collect([]);
        }
    }

    private function getCmEntryDetails($entity_cd, $doc_no)
    {
        try {
            $results = DB::connection('BFIE')
                ->table('mgr.pl_contract as plc')
                ->select(
                    DB::raw("COALESCE(NULLIF(plc.works_descs, ''), 'No Work Description')"),
                    'plc.contract_no', 
                    'plc.currency_cd', 
                    'plc.contract_amt',
                    'plc.auth_vo',
                    DB::raw("COALESCE(REPLACE(REPLACE(REPLACE(NULLIF(plc.url_link, ''),CHAR(10), ''),CHAR(9), ''),CHAR(13), ''),'EMPTY')"),
                    DB::raw("
                        ISNULL((
                            SELECT STRING_AGG(
                                CASE
                                    WHEN url_file_attachment IS NULL THEN 'EMPTY'
                                    WHEN CHARINDEX('http', url_file_attachment) = 0 THEN 'EMPTY'
                                    ELSE url_file_attachment
                                END, ';'
                            )
                            FROM mgr.cm_contract_file_attach WITH (NOLOCK)
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
                            FROM mgr.cm_contract_file_attach WITH (NOLOCK)
                            WHERE entity_cd = '".addslashes($entity_cd)."' 
                            AND doc_no = '".addslashes($doc_no)."'
                        ), 'EMPTY') as file_name
                    ")
                )
                ->where('plc.entity_cd',$entity_cd)
                ->where('plc.contract_no',$doc_no)
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
            \Log::error('getCmEntryDetails error: '.$e->getMessage());
            return collect([]);
        }
    }

    private function getCmProgressDetails($entity_cd, $doc_no, $ref_no)
    {
        try {
            $results = DB::connection('BFIE')
                ->table('mgr.cm_progress as cp')
                ->join('mgr.pl_contract as plc', function ($join) {
                    $join->on('cp.entity_cd', '=', 'plc.entity_cd')
                        ->on('cp.contract_no', '=', 'plc.contract_no');
                })->select(
                    DB::raw("COALESCE(NULLIF(plc.works_descs, ''), 'No Work Description')"),
                    'cp.progress_no', 
                    'cp.curr_progress',  
                    'cp.curr_progress_amt', 
                    'cp.prev_progress',
                    'cp.prev_progress_amt', 
                    DB::raw("COALESCE(REPLACE(REPLACE(REPLACE(NULLIF(cp.surveyor, ''),CHAR(10), ''),CHAR(9), ''),CHAR(13), ''),'No Surveyor')"),
                    'cp.contract_no', 
                    DB::raw("COALESCE(REPLACE(REPLACE(REPLACE(NULLIF(cp.url_link, ''),CHAR(10), ''),CHAR(9), ''),CHAR(13), ''),'EMPTY')"),
                    DB::raw("
                        ISNULL((
                            SELECT STRING_AGG(
                                CASE
                                    WHEN url_file_attachment IS NULL THEN 'EMPTY'
                                    WHEN CHARINDEX('http', url_file_attachment) = 0 THEN 'EMPTY'
                                    ELSE url_file_attachment
                                END, ';'
                            )
                            FROM mgr.cm_progress_file_attach WITH (NOLOCK)
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
                            FROM mgr.cm_progress_file_attach WITH (NOLOCK)
                            WHERE entity_cd = '".addslashes($entity_cd)."' 
                            AND doc_no = '".addslashes($doc_no)."'
                        ), 'EMPTY') as file_name
                    ")
                )
                ->where('cp.entity_cd',$entity_cd)
                ->where('cp.progress_no',$doc_no)
                ->where('cp.contract_no',$ref_no)
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
            \Log::error('getCmProgressDetails error: '.$e->getMessage());
            return collect([]);
        }
    }

    private function getCmVarianDetails($entity_cd, $doc_no, $ref_no)
    {
        try {
            $results = DB::connection('BFIE')
                ->table('mgr.cm_vo_hd as voh')
                ->join('mgr.pl_contract as plc', function ($join) {
                    $join->on('voh.entity_cd', '=', 'plc.entity_cd')
                        ->on('voh.contract_no', '=', 'plc.contract_no');
                })->select(
                    'voh.submission_amt', 
                    'voh.approved_amt',
                    'plc.currency_cd',
                    DB::raw("COALESCE(REPLACE(REPLACE(REPLACE(NULLIF(voh.url_link, ''),CHAR(10), ''),CHAR(9), ''),CHAR(13), ''),'EMPTY')"),
                    DB::raw("
                        ISNULL((
                            SELECT STRING_AGG(
                                CASE
                                    WHEN url_file_attachment IS NULL THEN 'EMPTY'
                                    WHEN CHARINDEX('http', url_file_attachment) = 0 THEN 'EMPTY'
                                    ELSE url_file_attachment
                                END, ';'
                            )
                            FROM mgr.cm_vo_file_attach WITH (NOLOCK)
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
                            FROM mgr.cm_vo_file_attach WITH (NOLOCK)
                            WHERE entity_cd = '".addslashes($entity_cd)."' 
                            AND doc_no = '".addslashes($doc_no)."'
                        ), 'EMPTY') as file_name
                    ")
                )
                ->where('voh.entity_cd',$entity_cd)
                ->where('voh.vo_no',$doc_no)
                ->where('voh.contract_no',$ref_no)
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
            \Log::error('getCmVoDetails error: '.$e->getMessage());
            return collect([]);
        }
    }
}
