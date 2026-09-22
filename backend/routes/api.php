<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\TargetController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/categories', [CategoryController::class, 'index']);

Route::get('/targets', [TargetController::class, 'index'])
    ->middleware('auth:sanctum');

Route::post('/targets', [TargetController::class, 'store'])
    ->middleware('auth:sanctum');

Route::get('/targets/{target}', [TargetController::class, 'show'])
    ->middleware('auth:sanctum');

Route::patch('/targets/{target}', [TargetController::class, 'update'])
    ->middleware('auth:sanctum');
