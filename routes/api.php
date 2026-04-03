<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\SupplierController;

$projectByIdPath = '/projects/{id}';

// Public API routes (no auth for now)
Route::get('/suppliers', [SupplierController::class, 'index']);
Route::post('/suppliers', [SupplierController::class, 'store']);
Route::patch('/suppliers/{id}', [SupplierController::class, 'update']);
Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy']);

Route::post('/component-supplier', [SupplierController::class, 'assign']);
Route::get('/component-supplier/{component_id}', [SupplierController::class, 'getByComponent']);
Route::delete('/component-supplier/{id}', [SupplierController::class, 'destroyAssignment']);

Route::get('/categories', [PricingController::class, 'categories']);
Route::post('/categories', [PricingController::class, 'storeCategory']);
Route::get('/components', [PricingController::class, 'components']);
Route::get('/components/{category}', [PricingController::class, 'components']);
Route::post('/components', [PricingController::class, 'storeComponent']);

Route::patch('/items/categories/{id}', [PricingController::class, 'updateCategory']);
Route::delete('/items/categories/{id}', [PricingController::class, 'destroyCategory']);
Route::patch('/items/components/{id}', [PricingController::class, 'updateItemComponent']);
Route::delete('/items/components/{id}', [PricingController::class, 'destroyItemComponent']);

Route::post('/add-component', [PricingController::class, 'addComponent']);
Route::get('/calculate/{projectId}', [PricingController::class, 'calculate']);
Route::delete('/components/{id}', [PricingController::class, 'removeComponent']);

Route::get('/projects', [ProjectController::class, 'index']);
Route::post('/projects', [ProjectController::class, 'store']);
Route::get($projectByIdPath, [ProjectController::class, 'show']);
Route::patch($projectByIdPath, [ProjectController::class, 'update']);
Route::delete($projectByIdPath, [ProjectController::class, 'destroy']);
Route::patch('/project-components/{id}', [ProjectController::class, 'updateComponent']);
Route::delete('/project-components/{id}', [ProjectController::class, 'destroyComponent']);

Route::post('/generate-quote', [QuoteController::class, 'generate']);
Route::get('/quotes', [QuoteController::class, 'index']);
