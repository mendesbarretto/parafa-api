<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CityController;

Route::middleware('api')->group(function () {
    // Empresas (Customers)
    Route::get('/empresas', [CustomerController::class, 'index']);
    Route::get('/empresas/{id}', [CustomerController::class, 'show']);
    Route::post('/empresas', [CustomerController::class, 'store']);
    Route::put('/empresas/{id}', [CustomerController::class, 'update']);
    Route::delete('/empresas/{id}', [CustomerController::class, 'destroy']);
    
    // Categorias
    Route::get('/categorias', [CategoryController::class, 'index']);
    Route::get('/categorias/{id}', [CategoryController::class, 'show']);
    
    // Cidades e Estados
    Route::get('/cidades', [CityController::class, 'index']);
    Route::get('/cidades/{id}', [CityController::class, 'show']);
    Route::get('/estados', [CityController::class, 'states']);
});
