<?php

use App\Http\Controllers\invoice;
use App\Http\Controllers\items;
use App\Http\Controllers\login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('auth/login', [login::class, 'login']);
Route::post('auth/logout', [login::class, 'logout']);

Route::post('items/addItem', [items::class, 'insert']);
Route::post('items/updateItem', [items::class, 'update']);
Route::get('items/getItems', [items::class, 'get']);
Route::get('items/deleteItem', [items::class, 'delete']);


Route::post('invoice/upsert', [invoice::class, 'upsert']);
Route::post('invoice/upsertUpdate', [invoice::class, 'upsertUpdate']);
Route::get('invoice/getinvoices', [invoice::class, 'getInvoices']);
Route::get('invoice/getInvDetails', [invoice::class, 'getDetails']);
Route::post('invoice/deleteInvoice', [invoice::class, 'deleteInvoice']);
Route::post('invoice/UpdateHeader', [invoice::class, 'updateHeader']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
