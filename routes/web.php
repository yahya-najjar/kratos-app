<?php

use App\Http\Controllers\RecordingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/fetch-recording/{number}', [RecordingController::class, 'fetchRecording']);
