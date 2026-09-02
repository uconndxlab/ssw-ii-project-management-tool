<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityTypeController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AgreementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactFamilyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoggingFieldController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('password-reset-enabled')->group(function () {
        Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetController::class, 'store'])->name('password.email');
        Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
    });
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    Route::post('/agreements/{agreement}/duplicate', [AgreementController::class, 'duplicate'])->name('agreements.duplicate');
    Route::resource('agreements', AgreementController::class);

    Route::get('/agreements/{agreement}/attachments/{attachment}/download', [AgreementController::class, 'downloadAttachment'])
        ->scopeBindings()
        ->name('agreements.attachments.download');

    Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');
    Route::get('/activities/create', [ActivityController::class, 'create'])->name('activities.create');
    Route::post('/activities', [ActivityController::class, 'store'])->name('activities.store');
    Route::get('/activities/{activity}', [ActivityController::class, 'show'])->name('activities.show');
    Route::get('/activities/{activity}/edit', [ActivityController::class, 'edit'])->name('activities.edit');
    Route::put('/activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');
    Route::post('/activities/{activity}/duplicate', [ActivityController::class, 'duplicate'])->name('activities.duplicate');
    Route::get('/activities/{activity}/action-logs', [ActivityController::class, 'actionLogs'])->name('activities.action-logs');
    Route::delete('/activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');

    Route::get('/activities/{activity}/logging-field-document/{context}/{fieldId}/{agreementId?}', [ActivityController::class, 'downloadLoggingFieldDocument'])
        ->name('activities.logging-field-document.download');

    Route::get('/activity-types/by-family', [ActivityTypeController::class, 'getByFamily'])->name('activity-types.by-family');

    Route::resource('organizations', OrganizationController::class);
    Route::resource('states', StateController::class);
    Route::resource('projects', ProjectController::class);
    Route::resource('programs', ProgramController::class);
    Route::resource('teams', TeamController::class);

    // admin protected in policy instead
    Route::resource('logging-fields', LoggingFieldController::class);
    Route::resource('contact-families', ContactFamilyController::class)->except(['show']);
    Route::resource('activity-types', ActivityTypeController::class)->except(['show']);

    Route::get('/supervisees', [AdminUserController::class, 'supervisees'])->name('supervisees.index');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    });
});
