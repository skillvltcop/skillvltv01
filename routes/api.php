<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ExecuteBlueprintController;

Route::post(
    '/blueprints/{blueprint}/execute',
    ExecuteBlueprintController::class,
);