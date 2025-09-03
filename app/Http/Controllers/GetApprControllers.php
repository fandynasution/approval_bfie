<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PDO;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;


class GetApprControllers extends Controller
{
    public function Index(Request $request)
    {
        // ✅ Daftar field yang diperbolehkan
        $allowedKeys = ['entity_cd', 'email_addr'];

        // 🚨 Cek kalau ada field di luar allowedKeys
        $requestKeys = array_keys($request->all());
        $extraKeys = array_diff($requestKeys, $allowedKeys);

        if (!empty($extraKeys)) {
            return response()->json([
                'success' => false,
                'message' => 'Request hanya boleh berisi entity_cd dan email_addr',
                'invalid_fields' => array_values($extraKeys)
            ], 400);
        }

        $entity_cd = $request->entity_cd;
        $email_addr = $request->email_addr;

        // 🚨 Blokir juga kalau kosong
        if (empty($entity_cd) || empty($email_addr)) {
            return response()->json([
                'success' => false,
                'message' => 'entity_cd dan email_addr wajib diisi'
            ], 400);
        }

        $query = DB::connection('BFIE')
            ->table('mgr.cb_cash_request_appr_azure as a')
            ->select('a.doc_no', 'a.email_addr', 'a.entity_cd', 'a.level_no')
            ->where('a.status', 'P')
            ->where('a.email_addr', $email_addr)
            ->where('a.entity_cd', $entity_cd)
            ->whereRaw('a.level_no = (
                select min(b.level_no)
                from mgr.cb_cash_request_appr_azure b
                where b.doc_no = a.doc_no
                and b.entity_cd = a.entity_cd
                and b.email_addr = a.email_addr
                and b.status = \'P\'
            )')
            ->distinct()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $query
        ]);
    }

    public function Detail(Request $request)
    {
        // ✅ Daftar field yang diperbolehkan
        $allowedKeys = ['entity_cd', 'email_addr', 'doc_no', 'level_no'];

        // 🚨 Cek kalau ada field di luar allowedKeys
        $requestKeys = array_keys($request->all());
        $extraKeys = array_diff($requestKeys, $allowedKeys);

        if (!empty($extraKeys)) {
            return response()->json([
                'success' => false,
                'message' => 'Request hanya boleh berisi entity_cd, email_addr, doc_no dan level_no',
                'invalid_fields' => array_values($extraKeys)
            ], 400);
        }

        $entity_cd = $request->entity_cd;
        $email_addr = $request->email_addr;
        $doc_no = $request->doc_no;
        $level_no = $request->level_no;

        // 🚨 Blokir juga kalau kosong
        if (empty($entity_cd) || empty($email_addr)) {
            return response()->json([
                'success' => false,
                'message' => 'entity_cd, email_addr, doc_no dan level_no wajib diisi'
            ], 400);
        }

        $query = DB::connection('BFIE')
            ->table('mgr.cb_cash_request_appr_azure as a')
            ->select('a.doc_no', 'a.entity_cd', 'a.level_no', 'a.type', 'a.module') // pilih kolom
            ->where('a.status', 'P')
            ->where('a.email_addr', $email_addr)
            ->where('a.entity_cd', $entity_cd)
            ->where('a.doc_no', $doc_no)     // filter doc_no
            ->where('a.level_no', $level_no) // filter level_no
            ->whereRaw('a.level_no = (
                select min(b.level_no)
                from mgr.cb_cash_request_appr_azure b
                where b.doc_no = a.doc_no
                and b.entity_cd = a.entity_cd
                and b.email_addr = a.email_addr
                and b.status = \'P\'
            )')
            ->get();

        $data = $query->map(function($item) {
            $details = [];

            if ($item->module === 'PO' && $item->type === 'Q') {
                $details = $this->getPoRequestDetails($item->entity_cd, $item->doc_no);
            } else if ($item->module === 'PO' && $item->type === 'A') {
                $details = $this->getPoOrderDetails($item->entity_cd, $item->doc_no);
            } else if ($item->module === 'PO' && $item->type === 'S') {
                $details = $this->getPoOrderDetails($item->entity_cd, $item->doc_no);
            }

            // tambahkan sub array "details"
            $item->details = $details;

            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    private function getPoRequestDetails($entity_cd, $doc_no)
    {
        return DB::connection('BFIE')
            ->table('mgr.po_request_hd as h')
            ->join('mgr.po_request_dt as d', function($join) {
                $join->on('h.entity_cd', '=', 'd.entity_cd')
                    ->on('h.request_no', '=', 'd.request_no');
            })
            ->select(
                'h.descs',
                'h.currency_cd',
                'h.source',
                DB::raw('ISNULL(SUM(d.total_price), 0.00) as total_price')
            )
            ->where('h.entity_cd', $entity_cd)
            ->where('h.request_no', $doc_no)
            ->groupBy('h.descs', 'h.currency_cd', 'h.source')
            ->get();
    }

    private function getPoOrderDetails($entity_cd, $doc_no)
    {
        return DB::connection('BFIE')
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
                ")
            )
            ->where('h.entity_cd', $entity_cd)
            ->where('h.order_no', $doc_no)
            ->get();
    }
}
