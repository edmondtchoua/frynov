<?php

use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Auth\Http\Controllers\UserProfileController;
use App\Modules\Auth\Http\Controllers\WorkspaceController;
use App\Modules\Auth\Http\Middleware\EnsureUserBelongsToTenant;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    // Public endpoints — rate limited to prevent brute force (OWASP API4)
    Route::post('login',    [AuthController::class, 'login'])->name('login')
        ->middleware('throttle:5,1');   // 5 attempts per minute per IP
    Route::post('register', [AuthController::class, 'register'])->name('register')
        ->middleware('throttle:3,1');   // 3 registrations per minute per IP

    // Protected endpoints
    Route::middleware(['auth:sanctum', EnsureUserBelongsToTenant::class])->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
    });
});

// ── User profile (works for ALL authenticated users, incl. super-admin) ─────────
Route::middleware(['auth:sanctum'])->group(function () {
    Route::patch('me/profile',             [UserProfileController::class, 'update']);
    Route::post('me/password',             [UserProfileController::class, 'changePassword']);
    Route::get('me/sessions',              [UserProfileController::class, 'sessions']);
    Route::delete('me/sessions/{tokenId}', [UserProfileController::class, 'revokeSession']);
});

// ── Workspace management (tenant-scoped) ──────────────────────────────────────
Route::prefix('workspace')->name('workspace.')
    ->middleware(['auth:sanctum', EnsureUserBelongsToTenant::class])
    ->group(function () {
        // Team management
        Route::get('users',           [WorkspaceController::class, 'listUsers'])->name('users.index');
        Route::post('users',          [WorkspaceController::class, 'inviteUser'])->name('users.invite')->middleware('quota:users');
        Route::patch('users/{user}',  [WorkspaceController::class, 'updateUser'])->name('users.update');
        Route::delete('users/{user}', [WorkspaceController::class, 'toggleUser'])->name('users.toggle');
        Route::put('users/{user}/warehouses', [WorkspaceController::class, 'setUserWarehouses'])->name('users.warehouses');
        Route::post('users/{user}/temporary-access', [WorkspaceController::class, 'grantTemporaryAccess'])->name('users.temp-access.grant');
        Route::delete('temporary-access/{grant}', [WorkspaceController::class, 'revokeTemporaryAccess'])->name('temp-access.revoke');

        // Company settings
        Route::get('settings',        [WorkspaceController::class, 'getSettings'])->name('settings.show');
        Route::patch('settings',      [WorkspaceController::class, 'updateSettings'])->name('settings.update');

        // Onboarding provisioning
        Route::post('provision',      [WorkspaceController::class, 'provision'])->name('provision');
    });
