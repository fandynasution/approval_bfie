<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

use App\Http\Controllers\MailDataController as MailData;

Route::POST('/maildata', [MailData::class, 'receive']);
Route::GET('/processdata/{module}/{status}/{encrypt}', [MailData::class, 'processData']);
Route::POST('/getaccess', [MailData::class, 'getAccess']);

use App\Http\Controllers\PoRequestController as PoRequest;
Route::GET('/porequest/{status}/{encrypt}', [PoRequest::class, 'processData']);
Route::POST('/porequest/getaccess', [PoRequest::class, 'getaccess']);

use App\Http\Controllers\StaffActionController as StaffAction;
Route::POST('/staffaction_por', [StaffAction::class, 'staffaction_por']);
Route::POST('/fileexist', [StaffAction::class, 'fileexist']);

use App\Http\Controllers\GetApprControllers as GetAppr;
Route::POST('/getappr', [GetAppr::class, 'Index']);
Route::POST('/getapprDetail', [GetAppr::class, 'Detail']);