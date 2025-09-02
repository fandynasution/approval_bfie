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
        $entity_cd = $request->entity_cd;
        $email_addr = $request->email_addr;

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

        return response()->json($query);
    }
}
