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
            ->table('mgr.cb_cash_request_appr_azure')
            ->select('doc_no', 'email_addr', 'entity_cd')
            ->distinct()
            ->where('status', 'P')
            ->where('email_addr', $email_addr)
            ->where('entity_cd', $entity_cd)
            ->get();

        return response()->json($query);
    }
}
