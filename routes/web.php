<?php

use App\Http\Controllers\Auth\InvitedRegisterController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IdentityController;
use App\Http\Controllers\IdentityGroupController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LotController;
use App\Http\Controllers\VenueController;
use Illuminate\Support\Facades\Route;

// 招待付き登録（認証不要）
Route::get('/register/{code}', [InvitedRegisterController::class, 'show'])->name('register.show');
Route::post('/register/{code}', [InvitedRegisterController::class, 'store'])->name('register.store');

// 認証必須
Route::middleware('auth')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::resource('attendances', AttendanceController::class);

    Route::get('/identities', [IdentityController::class, 'index'])->name('identities.index');
    Route::get('/identities/create', [IdentityController::class, 'create'])->name('identities.create');
    Route::post('/identities', [IdentityController::class, 'store'])->name('identities.store');
    Route::get('/identities/{fcMembership}', [IdentityController::class, 'show'])->name('identities.show');
    Route::get('/identities/{fcMembership}/edit', [IdentityController::class, 'edit'])->name('identities.edit');
    Route::put('/identities/{fcMembership}', [IdentityController::class, 'update'])->name('identities.update');
    Route::delete('/identities/{fcMembership}', [IdentityController::class, 'destroy'])->name('identities.destroy');

    Route::resource('identity-groups', IdentityGroupController::class)->except(['show']);
    Route::post('/identity-groups/reorder', [IdentityGroupController::class, 'reorder'])->name('identity-groups.reorder');

    Route::get('/lots', [LotController::class, 'index'])->name('lots.index');

    Route::get('/venues/{venue}', [VenueController::class, 'show'])->name('venues.show');
    Route::put('/venues/{venue}/note', [VenueController::class, 'updateNote'])->name('venues.update-note');

    Route::resource('invitations', InvitationController::class)->only(['index', 'store', 'destroy']);

    // 会場サジェストAPI
    Route::get('/api/venues/suggest', [VenueController::class, 'suggest'])->name('api.venues.suggest');
});
