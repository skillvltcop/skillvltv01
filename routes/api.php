<?php

use App\Http\Controllers\Api\AddBlueprintRevisionController;
use App\Http\Controllers\Api\CreateBlueprintController;
use App\Http\Controllers\Api\ExecuteBlueprintController;
use App\Http\Controllers\Api\FreezeBlueprintRevisionController;
use App\Http\Controllers\Api\PromoteBlueprintRevisionController;
use App\Http\Controllers\Api\ShowExecutionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ActivateBlueprintController;
use App\Http\Controllers\Api\ShowBlueprintController;

Route::post(
    '/blueprints',
    CreateBlueprintController::class,
);

Route::post(
    '/blueprints/{blueprint}/revisions',
    AddBlueprintRevisionController::class,
);

Route::post(
    '/blueprints/{blueprint}/revisions/{revision}/freeze',
    FreezeBlueprintRevisionController::class,
);

Route::post(
    '/blueprints/{blueprint}/revisions/{revision}/promote',
    PromoteBlueprintRevisionController::class,
);

Route::post(
    '/blueprints/{blueprint}/execute',
    ExecuteBlueprintController::class,
);

Route::get(
    '/executions/{execution}',
    ShowExecutionController::class,
);

Route::post(
    '/blueprints/{blueprint}/activate',
    ActivateBlueprintController::class,
);

Route::get(
    '/blueprints/{blueprint}',
    ShowBlueprintController::class,
);