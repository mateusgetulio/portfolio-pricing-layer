<?php

use App\Http\Controllers\DemoController;
use Illuminate\Support\Facades\Route;

Route::get('/demo/scenario', [DemoController::class, 'scenario'])->name('demo.scenario');
