<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Consultant\ConsultantController;

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

Route::group([
    'prefix' => 'v1',
], function () {
    Route::get('consultants', [ConsultantController::class, 'index']);
    Route::get('consultants/reports', [ConsultantController::class, 'getReport']);
    Route::get('consultants/{type}/graph', [ConsultantController::class, 'getDataGraph']);
});
