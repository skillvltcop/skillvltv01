<?php

use App\Http\Controllers\Web\Admin\AuthorizationDashboardController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\NewPasswordController;
use App\Http\Controllers\Web\Auth\PasswordResetLinkController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\Teacher\Tools\TeacherToolController;
use App\Http\Controllers\Web\Teacher\Tools\ToolsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:3,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/admin/authorization', AuthorizationDashboardController::class)
    ->middleware('auth')
    ->name('admin.authorization.index');
Route::post('/locale', LocaleController::class)->name('locale.update');

Route::get('/teacher/tools', ToolsController::class)
    ->middleware('auth')
    ->name('teacher.tools.index');

Route::get('/teacher/tools/{slug}', TeacherToolController::class)
    ->middleware('auth')
    ->name('teacher.tools.show');

Route::get('/', function () {
    return redirect()->route('teacher.tools.index');
})->middleware('auth');
