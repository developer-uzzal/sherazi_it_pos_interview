<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

