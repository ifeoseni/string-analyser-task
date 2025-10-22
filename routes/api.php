<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StringController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');




Route::post('/strings', [StringController::class, 'store']);
Route::delete('/strings/{string_value}', [StringController::class, 'destroy'])
    ->where('string_value', '.*');