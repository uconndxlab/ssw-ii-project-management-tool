<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\AgreementController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ContactFamilyController;
use App\Http\Controllers\ActivityTypeController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // Agreements - visible to all authenticated users (with visibility filtering in controller)
    Route::resource('agreements', AgreementController::class);
    
    // HTMX endpoints for agreement user management
    Route::post('/agreements/{agreement}/assign-user', [AgreementController::class, 'assignUser'])->name('agreements.assign-user');
    Route::delete('/agreements/{agreement}/remove-user/{user}', [AgreementController::class, 'removeUser'])->name('agreements.remove-user');
    
    // HTMX endpoints for agreement deliverable management
    Route::post('/agreements/{agreement}/add-deliverable', [AgreementController::class, 'addDeliverable'])->name('agreements.add-deliverable');
    Route::delete('/agreements/{agreement}/remove-deliverable/{deliverable}', [AgreementController::class, 'removeDeliverable'])->name('agreements.remove-deliverable');
    
    // Activities - visible to all authenticated users (with visibility filtering in controller)
    Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');
    Route::get('/activities/create', [ActivityController::class, 'create'])->name('activities.create');
    Route::post('/activities', [ActivityController::class, 'store'])->name('activities.store');
    Route::get('/activities/{activity}', [ActivityController::class, 'show'])->name('activities.show');
    Route::get('/activities/{activity}/edit', [ActivityController::class, 'edit'])->name('activities.edit');
    Route::put('/activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');
    Route::delete('/activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');
    
    // HTMX endpoint for activity participant selection
    Route::get('/activities/participants-for-agreement', [ActivityController::class, 'getParticipantsForAgreement'])
        ->name('activities.participants-for-agreement');
    
    // HTMX endpoint for filtering activity types by contact family
    Route::get('/activity-types/by-family', [ActivityTypeController::class, 'getByFamily'])->name('activity-types.by-family');
    
    // Reports
    Route::get('/reports/activities', [ReportController::class, 'activities'])->name('reports.activities');
    
    // Organizations - viewable by all, admin-only for create/edit/delete
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');
    
    // Admin routes
    Route::middleware('role:admin')->group(function () {
        Route::resource('contact-families', ContactFamilyController::class)->except(['show']);
        Route::resource('activity-types', ActivityTypeController::class)->except(['show']);
        Route::resource('states', StateController::class);
        Route::resource('organizations', OrganizationController::class)->except(['index', 'show']);
        Route::resource('programs', ProgramController::class);
    });
    
    // Admin user management
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    });
});

