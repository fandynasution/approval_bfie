<?php

namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class PoModuleService
{
    public function getDetails(string $type, string $entity_cd, string $doc_no, string $ref_no = null): Collection
    {
        switch($type) {
            case 'Q': return $this->getPoRequestDetails($entity_cd, $doc_no);
            case 'A': return $this->getPoOrderDetails($entity_cd, $doc_no);
            case 'S': return $this->getPoSelectionDetails($entity_cd, $doc_no, $ref_no);
            default: return collect([]);
        }
    }

    private function getPoRequestDetails($entity_cd, $doc_no)
    {
        try {
            $results = DB::connection('BFIE')
                ->table('mgr.po_request_hd as h')
                ->join('mgr.po_request_dt as d', function($join) {
                    $join->on('h.entity_cd','=','d.entity_cd')
                        ->on('h.request_no','=','d.request_no');
                })
                ->select(
                    'h.descs',
                    'h.currency_cd',
                    'h.source',
                    DB::raw('ISNULL(SUM(d.total_price),0.00) as total_price'),
                    DB::raw("
                        ISNULL((
                            SELECT STRING_AGG(
                                CASE
                                    WHEN url_file_attachment IS NULL THEN ''
                                    WHEN CHARINDEX('http', url_file_attachment) = 0 THEN ''
                                    ELSE url_file_attachment
                                END, ';'
                            )
                            FROM mgr.po_request_file_attach WITH (NOLOCK)
                            WHERE entity_cd = '".addslashes($entity_cd)."' 
                            AND request_no = '".addslashes($doc_no)."'
                        ), '') as url_file
                    "),
                    DB::raw("
                        ISNULL((
                            SELECT STRING_AGG(
                                CASE
                                    WHEN file_name IS NULL THEN 'EMPTY'
                                    ELSE file_name
                                END, ';'
                            )
                            FROM mgr.po_request_file_attach WITH (NOLOCK)
                            WHERE entity_cd = '".addslashes($entity_cd)."' 
                            AND request_no = '".addslashes($doc_no)."'
                        ), '') as file_name
                    ")
                )
                ->where('h.entity_cd',$entity_cd)
                ->where('h.request_no',$doc_no)
                ->groupBy('h.descs','h.currency_cd','h.source')
                ->get();

            // convert string jadi array []
            $results->transform(function ($item) {
                $item->url_file = empty($item->url_file)
                    ? []
                    : array_filter(explode(';', $item->url_file));

                $item->file_name = empty($item->file_name)
                    ? []
                    : array_filter(explode(';', $item->file_name));

                return $item;
            });

            return $results;
        } catch (\Exception $e) {
            \Log::error('getPoRequestDetails error: '.$e->getMessage());
            return collect([]);
        }
    }

    private function getPoOrderDetails($entity_cd, $doc_no)
    {
        try {
            $results = DB::connection('BFIE')
                ->table('mgr.po_orderhd as h')
                ->join('mgr.po_orderdt as d', function ($join) {
                    $join->on('h.entity_cd', '=', 'd.entity_cd')
                        ->on('h.order_no', '=', 'd.order_no');
                })
                ->select(
                    'h.remark',
                    'h.remarks',
                    'h.currency_cd',
                    DB::raw("
                        CASE 
                            WHEN h.currency_cd = 'IDR' 
                                THEN ISNULL((
                                    SELECT TOP 1 a.amount 
                                    FROM mgr.cb_cash_request_appr a 
                                    WHERE a.entity_cd = h.entity_cd 
                                    AND a.doc_no = h.order_no
                                ), 0.00)
                            ELSE ISNULL((
                                    SELECT TOP 1 h2.po_amt 
                                    FROM mgr.po_orderhd h2 
                                    WHERE h2.entity_cd = h.entity_cd 
                                    AND h2.order_no = h.order_no
                                ), 0.00)
                        END AS amount
                    "),
                    DB::raw("
                        (
                            SELECT ISNULL(
                                STRING_AGG(
                                    CASE
                                        WHEN s.supplier_name IS NULL THEN 'No Supplier'
                                        ELSE REPLACE(REPLACE(REPLACE(REPLACE(s.supplier_name, CHAR(10), ''), CHAR(9), ''), CHAR(13), ''), '\"', '')
                                    END, '; '
                                ), 'No Supplier'
                            )
                            FROM mgr.v_po_quote_compare_non_cor s WITH (NOLOCK)
                            WHERE s.entity_cd = h.entity_cd
                            AND s.doc_no = h.order_no
                        ) AS supplier_name
                    "),
                    DB::raw("
                        ISNULL((
                            SELECT STRING_AGG(
                                CASE
                                    WHEN url_file_attachment IS NULL THEN ''
                                    WHEN CHARINDEX('http', url_file_attachment) = 0 THEN ''
                                    ELSE url_file_attachment
                                END, ';'
                            )
                            FROM mgr.po_order_file_attach WITH (NOLOCK)
                            WHERE entity_cd = '".addslashes($entity_cd)."' 
                            AND doc_no = '".addslashes($doc_no)."'
                        ), '') as url_file
                    "),
                    DB::raw("
                        ISNULL((
                            SELECT STRING_AGG(
                                CASE
                                    WHEN file_name IS NULL THEN 'EMPTY'
                                    ELSE file_name
                                END, ';'
                            )
                            FROM mgr.po_order_file_attach WITH (NOLOCK)
                            WHERE entity_cd = '".addslashes($entity_cd)."' 
                            AND doc_no = '".addslashes($doc_no)."'
                        ), '') as file_name
                    ")
                )
                ->where('h.entity_cd', $entity_cd)
                ->where('h.order_no', $doc_no)
                ->get();

            // convert string jadi array []
            $results->transform(function ($item) {
                $item->url_file = empty($item->url_file)
                    ? []
                    : array_filter(explode(';', $item->url_file));

                $item->file_name = empty($item->file_name)
                    ? []
                    : array_filter(explode(';', $item->file_name));

                return $item;
            });

            return $results;
        } catch (\Exception $e) {
            \Log::error('getPoOrderDetails error: '.$e->getMessage());
            return collect([]);
        }
    }

    private function getPoSelectionDetails($entity_cd, $doc_no, $ref_no)
    {
        try {
            $results = DB::connection('BFIE')
                ->table('mgr.po_quote_group as gr')
                ->join('mgr.po_quote_hd as hd', function ($join) {
                    $join->on('gr.entity_cd', '=', 'hd.entity_cd')
                        ->on('gr.doc_no', '=', 'hd.doc_no');
                })
                ->leftJoin('mgr.po_temp_alloc as al', function ($join) {
                    $join->on('gr.entity_cd', '=', 'al.entity_cd')
                        ->on('gr.doc_no', '=', 'al.doc_no');
                })
                ->select(
                    'gr.entity_cd',
                    'gr.doc_no',
                    'hd.copy_ref_no',
                    'hd.currency_cd',
                    DB::raw("ISNULL(SUM(al.price - al.disc_amt + al.tax_amt), 0.00) AS total_alloc"),
                    DB::raw("
                        (
                            SELECT ISNULL(
                                STRING_AGG(
                                    CASE
                                        WHEN v.supplier_name IS NULL THEN 'No Supplier'
                                        ELSE REPLACE(REPLACE(REPLACE(REPLACE(v.supplier_name, CHAR(9), ''), CHAR(10), ''), CHAR(13), ''), '\"', '')
                                    END, '; '
                                ), 'No Supplier'
                            )
                            FROM mgr.v_po_quote_compare_non_cor v WITH (NOLOCK)
                            WHERE v.entity_cd = gr.entity_cd
                            AND v.doc_no    = gr.doc_no
                            AND v.request_no = hd.copy_ref_no
                        ) AS supplier_name
                    "),
                    DB::raw("
                        ISNULL((
                            SELECT STRING_AGG(
                                CASE
                                    WHEN url_file_attachment IS NULL THEN ''
                                    WHEN CHARINDEX('http', url_file_attachment) = 0 THEN ''
                                    ELSE url_file_attachment
                                END, ';'
                            )
                            FROM mgr.po_selection_file_attach WITH (NOLOCK)
                            WHERE entity_cd = '".addslashes($entity_cd)."' 
                            AND doc_no = '".addslashes($doc_no)."'
                        ), '') as url_file
                    "),
                    DB::raw("
                        ISNULL((
                            SELECT STRING_AGG(
                                CASE
                                    WHEN file_name IS NULL THEN 'EMPTY'
                                    ELSE file_name
                                END, ';'
                            )
                            FROM mgr.po_selection_file_attach WITH (NOLOCK)
                            WHERE entity_cd = '".addslashes($entity_cd)."' 
                            AND doc_no = '".addslashes($doc_no)."'
                        ), '') as file_name
                    ")
                )
                ->where('gr.entity_cd', $entity_cd)
                ->where('gr.doc_no', $doc_no)
                ->groupBy('gr.entity_cd', 'gr.doc_no', 'hd.currency_cd', 'hd.copy_ref_no')
                ->get();

            // convert string jadi array []
            $results->transform(function ($item) {
                $item->url_file = empty($item->url_file)
                    ? []
                    : array_filter(explode(';', $item->url_file));

                $item->file_name = empty($item->file_name)
                    ? []
                    : array_filter(explode(';', $item->file_name));

                return $item;
            });

            return $results;
        } catch (\Exception $e) {
            \Log::error('getPoSelectionDetails error: '.$e->getMessage());
            return collect([]);
        }
    }
}
