<?php

use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Teacher\Tools\AssessmentPositioningController;
use App\Http\Controllers\Web\Teacher\Tools\ToolsController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth');

Route::get('/teacher/tools', ToolsController::class)
    ->middleware('auth')
    ->name('teacher.tools.index');

Route::get('/teacher/tools/assessment-positioning', AssessmentPositioningController::class)
    ->middleware('auth')
    ->name('teacher.tools.assessment-positioning');

Route::get('/', function () {
    return redirect()->route('teacher.tools.index');
})->middleware('auth');
