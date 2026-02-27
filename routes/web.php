<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\EngagementController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ProgramController;
use Illuminate\Support\Facades\Route;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return view('dashboard');
    })->name('dashboard');
    
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // Projects - visible to all authenticated users (with visibility filtering in controller)
    Route::resource('projects', ProjectController::class);
    
    // HTMX endpoints for project user management
    Route::post('/projects/{project}/assign-user', [ProjectController::class, 'assignUser'])->name('projects.assign-user');
    Route::delete('/projects/{project}/remove-user/{user}', [ProjectController::class, 'removeUser'])->name('projects.remove-user');
    
    // Engagements - visible to all authenticated users (with visibility filtering in controller)
    Route::get('/engagements', [EngagementController::class, 'index'])->name('engagements.index');
    Route::get('/engagements/create', [EngagementController::class, 'create'])->name('engagements.create');
    Route::post('/engagements', [EngagementController::class, 'store'])->name('engagements.store');
    Route::get('/engagements/{engagement}', [EngagementController::class, 'show'])->name('engagements.show');
    Route::get('/engagements/{engagement}/edit', [EngagementController::class, 'edit'])->name('engagements.edit');
    Route::put('/engagements/{engagement}', [EngagementController::class, 'update'])->name('engagements.update');
    Route::delete('/engagements/{engagement}', [EngagementController::class, 'destroy'])->name('engagements.destroy');
    
    // Reports
    Route::get('/reports/engagements', [ReportController::class, 'engagements'])->name('reports.engagements');
    
    // Admin routes
    Route::middleware('role:admin')->group(function () {
        Route::resource('states', StateController::class)->except(['show']);
        Route::resource('organizations', OrganizationController::class)->except(['show']);
        Route::resource('programs', ProgramController::class)->except(['show']);
    });
    
    // Admin user management
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    });
});

