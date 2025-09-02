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
        var_dump($request->all());
    }
}
