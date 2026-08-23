<?php

use App\Http\Controllers\Api\ExecuteBlueprintController;
use App\Http\Controllers\Api\ShowExecutionController;
use Illuminate\Support\Facades\Route;

Route::post(
    '/blueprints/{blueprint}/execute',
    ExecuteBlueprintController::class,
);

Route::get(
    '/executions/{execution}',
    ShowExecutionController::class,
);