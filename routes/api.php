<?php

use App\Http\Controllers\Api\ActivateBlueprintController;
use App\Http\Controllers\Api\AddBlueprintRevisionController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\MeController;
use App\Http\Controllers\Api\CreateBlueprintController;
use App\Http\Controllers\Api\DiscoverBlueprintsController;
use App\Http\Controllers\Api\DiscoverTeacherBlueprintsController;
use App\Http\Controllers\Api\ExecuteBlueprintController;
use App\Http\Controllers\Api\ExecuteTeacherBlueprintController;
use App\Http\Controllers\Api\FreezeBlueprintRevisionController;
use App\Http\Controllers\Api\ListBlueprintRevisionsController;
use App\Http\Controllers\Api\ListBlueprintsController;
use App\Http\Controllers\Api\PromoteBlueprintRevisionController;
use App\Http\Controllers\Api\ShowBlueprintController;
use App\Http\Controllers\Api\ShowBlueprintRevisionController;
use App\Http\Controllers\Api\ShowExecutionController;
use App\Http\Controllers\Api\DeprecateBlueprintController;
use App\Http\Controllers\Api\SunsetBlueprintController;
use Illuminate\Support\Facades\Route;

Route::post(
    '/auth/login',
    LoginController::class,
);

Route::middleware('auth:sanctum')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/auth/me',
        MeController::class,
    );

    Route::post(
        '/auth/logout',
        LogoutController::class,
    );

    /*
    |--------------------------------------------------------------------------
    | Blueprint discovery and reading
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/blueprints',
        ListBlueprintsController::class,
    );

    Route::get(
        '/blueprints/discover',
        DiscoverBlueprintsController::class,
    );

    Route::get(
        '/teacher/blueprints/discover',
        DiscoverTeacherBlueprintsController::class,
    );

    Route::get(
        '/blueprints/{blueprint}',
        ShowBlueprintController::class,
    );

    Route::get(
        '/blueprints/{blueprint}/revisions',
        ListBlueprintRevisionsController::class,
    );

    Route::get(
        '/blueprints/{blueprint}/revisions/{revision}',
        ShowBlueprintRevisionController::class,
    );

    /*
    |--------------------------------------------------------------------------
    | Blueprint mutations
    |--------------------------------------------------------------------------
    */

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
        '/blueprints/{blueprint}/activate',
        ActivateBlueprintController::class,
    );

    Route::post(
        '/blueprints/{blueprint}/deprecate',
        DeprecateBlueprintController::class,
    );

    Route::post(
        '/blueprints/{blueprint}/sunset',
        SunsetBlueprintController::class,
    );

    /*
    |--------------------------------------------------------------------------
    | Execution
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/blueprints/{blueprint}/execute',
        ExecuteBlueprintController::class,
    );

    Route::post(
        '/teacher/blueprints/{blueprint}/execute',
        ExecuteTeacherBlueprintController::class,
    );

    Route::post(
        '/teacher/tools/{tool}/execute',
        ExecuteTeacherBlueprintController::class,
    );

    Route::get(
        '/executions/{execution}',
        ShowExecutionController::class,
    );

});

