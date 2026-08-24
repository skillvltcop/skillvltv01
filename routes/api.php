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
use App\Http\Controllers\Api\ShowBlueprintRevisionController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\MeController;

Route::post(
    '/blueprints',
    CreateBlueprintController::class,
);

Route::middleware('auth:sanctum')->post(
    '/blueprints/{blueprint}/revisions',
    AddBlueprintRevisionController::class,
);

Route::middleware('auth:sanctum')->post(
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

Route::middleware('auth:sanctum')->get(
    '/blueprints/{blueprint}',
    ShowBlueprintController::class,
);

Route::get(
    '/blueprints/{blueprint}/revisions/{revision}',
    ShowBlueprintRevisionController::class,
);

Route::post(
    '/auth/login',
    LoginController::class,
);

Route::middleware('auth:sanctum')->group(function () {
    Route::get(
        '/auth/me',
        MeController::class,
    );

    Route::post(
        '/auth/logout',
        LogoutController::class,
    );
});

Route::middleware('auth:sanctum')->post(
    '/blueprints',
    CreateBlueprintController::class,
);

Route::middleware('auth:sanctum')->post(
    '/blueprints/{blueprint}/revisions/{revision}/promote',
    PromoteBlueprintRevisionController::class,
);