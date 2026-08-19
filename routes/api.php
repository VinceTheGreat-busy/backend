<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function() {
    return response()->json([
        "message" => "The server is working!",
    ]);
});