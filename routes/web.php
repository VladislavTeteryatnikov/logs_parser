<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LogController;

Route::get('/', [LogController::class, 'index'])->name('logs');

Route::get('/logs/table', [LogController::class, 'getTable'])->name('logs.table');
