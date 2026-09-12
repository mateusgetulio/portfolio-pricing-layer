<?php

use App\Http\Controllers\DemoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/demo');

Route::get('/demo', [DemoController::class, 'show'])->name('demo');
