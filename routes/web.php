<?php

use App\Http\Controllers\Auth\InvitedRegisterController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IdentityController;
use App\Http\Controllers\IdentityGroupController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LotController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\VenueController;
use Illuminate\Support\Facades\Route;

// 招待付き登録（認証不要）。コード総当たり対策でレート制限
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/register/{code}', [InvitedRegisterController::class, 'show'])->name('register.show');
    Route::post('/register/{code}', [InvitedRegisterController::class, 'store'])->name('register.store');
});

// 認証必須
Route::middleware('auth')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::resource('attendances', AttendanceController::class);
    // 当落結果の更新（S5/S6 の入力動線・参戦詳細から）
    Route::patch('/attendance-identities/{pivotId}/result', [AttendanceController::class, 'updateResult'])
        ->name('attendance-identities.update-result');

    Route::get('/identities', [IdentityController::class, 'index'])->name('identities.index');
    Route::get('/identities/create', [IdentityController::class, 'create'])->name('identities.create');
    Route::post('/identities', [IdentityController::class, 'store'])->name('identities.store');
    Route::get('/identities/{fcMembership}', [IdentityController::class, 'show'])->name('identities.show');
    Route::get('/identities/{fcMembership}/edit', [IdentityController::class, 'edit'])->name('identities.edit');
    Route::put('/identities/{fcMembership}', [IdentityController::class, 'update'])->name('identities.update');
    Route::delete('/identities/{fcMembership}', [IdentityController::class, 'destroy'])->name('identities.destroy');

    Route::resource('identity-groups', IdentityGroupController::class)->except(['show']);
    Route::post('/identity-groups/reorder', [IdentityGroupController::class, 'reorder'])->name('identity-groups.reorder');

    // 当落・申込登録・一括インポート（S9/S11）
    Route::get('/lots', [LotController::class, 'index'])->name('lots.index');
    Route::get('/lots/create', [LotController::class, 'create'])->name('lots.create');
    Route::post('/lots', [LotController::class, 'store'])->name('lots.store');
    Route::get('/lots/import', [LotController::class, 'importForm'])->name('lots.import');
    Route::post('/lots/import/parse', [LotController::class, 'importParse'])->name('lots.import.parse');
    Route::post('/lots/import', [LotController::class, 'importStore'])->name('lots.import.store');

    // 参戦写真（閲覧=全メンバー / 削除=投稿者のみ）
    Route::get('/photos/{photo}', [PhotoController::class, 'show'])->name('photos.show');
    Route::delete('/photos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');

    Route::get('/venues/{venue}', [VenueController::class, 'show'])->name('venues.show');
    Route::put('/venues/{venue}/note', [VenueController::class, 'updateNote'])->name('venues.update-note');

    Route::resource('invitations', InvitationController::class)->only(['index', 'store', 'destroy']);

    // 会場サジェストAPI
    Route::get('/api/venues/suggest', [VenueController::class, 'suggest'])->name('api.venues.suggest');
    // 公演名サジェスト（メンバー横断・読み取りのみ / 規約0-6③）
    Route::get('/api/events/suggest', [VenueController::class, 'eventSuggest'])->name('api.events.suggest');
    // Places API 会場オートフィル（失敗時フォールバック / spec §5-11）
    Route::get('/api/venues/place-lookup', [VenueController::class, 'placeLookup'])->name('api.venues.place-lookup');
});
