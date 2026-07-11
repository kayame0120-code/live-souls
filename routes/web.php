<?php

use App\Http\Controllers\Auth\InvitedRegisterController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IdentityController;


use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LotController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\SetlistController;
use App\Http\Controllers\TourController;
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
    // 公演日経過の「参戦した？」確認への応答（T8・自動遷移はしない）
    Route::patch('/attendances/{attendance}/confirm', [HomeController::class, 'confirmAttendance'])
        ->name('attendances.confirm');
    Route::post('/renewals/{fcMembership}/dismiss', [HomeController::class, 'dismissRenewal'])
        ->name('renewals.dismiss');

    Route::resource('attendances', AttendanceController::class);
    // 当落結果の更新（S5/S6 の入力動線・参戦詳細から）
    Route::patch('/attendance-identities/{pivotId}/result', [AttendanceController::class, 'updateResult'])
        ->name('attendance-identities.update-result');

    // グループ（idol_groups）追加
    Route::post('/idol-groups', [\App\Http\Controllers\IdolGroupController::class, 'store'])->name('idol-groups.store');

    Route::get('/identities', [IdentityController::class, 'index'])->name('identities.index');
    Route::get('/identities/create', [IdentityController::class, 'create'])->name('identities.create');
    Route::post('/identities', [IdentityController::class, 'store'])->name('identities.store');
    Route::get('/identities/{fcMembership}', [IdentityController::class, 'show'])->name('identities.show');
    Route::get('/identities/{fcMembership}/edit', [IdentityController::class, 'edit'])->name('identities.edit');
    Route::put('/identities/{fcMembership}', [IdentityController::class, 'update'])->name('identities.update');
    Route::get('/identities/{fcMembership}/duplicate', [IdentityController::class, 'duplicate'])->name('identities.duplicate');
    Route::post('/identities/{fcMembership}/duplicate', [IdentityController::class, 'storeDuplicate'])->name('identities.store-duplicate');
    Route::delete('/identities/{fcMembership}', [IdentityController::class, 'destroy'])->name('identities.destroy');




    // 当落（S9・v1.4: ツアーカード一覧→当落詳細）。create は tour より前に定義
    Route::get('/lots', [LotController::class, 'index'])->name('lots.index');
    Route::get('/lots/create', [LotController::class, 'create'])->name('lots.create');
    Route::post('/lots', [LotController::class, 'store'])->name('lots.store');
    Route::get('/lots/tours/{tour}', [LotController::class, 'showByTour'])->name('lots.tour');

    // ツアー共有マスタ（v1.4・全ユーザー可）
    Route::get('/tours/{tour}', [TourController::class, 'show'])->name('tours.show');
    Route::post('/tours/{tour}/deadlines', [TourController::class, 'updateDeadlines'])->name('tours.update-deadlines');
    Route::put('/tours/{tour}/deadlines/{deadline}', [TourController::class, 'updateDeadline'])->name('tours.update-deadline');
    Route::delete('/tours/{tour}/deadlines/{deadline}', [TourController::class, 'destroyDeadline'])->name('tours.destroy-deadline');
    Route::delete('/tours/{tour}', [TourController::class, 'destroy'])->name('tours.destroy');
    // 日程（event）はツアー配下で作成（旧 /events/create を置換）
    Route::get('/tours/{tour}/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/tours/{tour}/events', [EventController::class, 'store'])->name('events.store');

    // 公演一覧（ツアーカード）＋一括インポート（S11）
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/import', [EventController::class, 'importForm'])->name('events.import');
    Route::post('/events/import/parse', [EventController::class, 'importParse'])->name('events.import.parse');
    Route::post('/events/import/json', [EventController::class, 'importJson'])->name('events.import.json');
    Route::post('/events/import/json/store', [EventController::class, 'importJsonStore'])->name('events.import.json.store');
    Route::post('/events/import', [EventController::class, 'importStore'])->name('events.import.store');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

    // セットリスト（ツアー紐づけ・公演詳細から遷移）
    Route::get('/tours/{tour}/setlists', [SetlistController::class, 'show'])->name('setlists.show');
    Route::post('/tours/{tour}/setlists/items', [SetlistController::class, 'addItem'])->name('setlists.add-item');
    Route::delete('/tours/{tour}/setlists/items/{item}', [SetlistController::class, 'destroyItem'])->name('setlists.destroy-item');
    Route::post('/tours/{tour}/setlists/ai-parse', [SetlistController::class, 'aiParse'])->name('setlists.ai-parse');
    Route::post('/tours/{tour}/setlists/json', [SetlistController::class, 'jsonImport'])->name('setlists.json-import');
    Route::post('/tours/{tour}/setlists/bulk', [SetlistController::class, 'bulkStore'])->name('setlists.bulk-store');

    // 参戦写真（閲覧=全メンバー / 削除=投稿者のみ）
    Route::get('/photos/{photo}', [PhotoController::class, 'show'])->name('photos.show');
    Route::delete('/photos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');

    Route::get('/venues/{venue}', [VenueController::class, 'show'])->name('venues.show');
    Route::put('/venues/{venue}/note', [VenueController::class, 'updateNote'])->name('venues.update-note');

    Route::resource('invitations', InvitationController::class)->only(['index', 'store', 'destroy']);

    // 会場サジェストAPI
    Route::get('/api/venues/suggest', [VenueController::class, 'suggest'])->name('api.venues.suggest');
    // カスケード選択②：指定ツアー配下の日程（v1.5・全ユーザー読取のみ）
    Route::get('/api/tours/{tour}/events', [EventController::class, 'eventsByTour'])->name('api.tours.events');
    // 一括インポートのツアー名解決サジェスト（全ユーザー読取のみ）
    Route::get('/api/tours/suggest', [EventController::class, 'toursSuggest'])->name('api.tours.suggest');
    // Places API 会場オートフィル（失敗時フォールバック / spec §5-11）
    Route::get('/api/venues/place-lookup', [VenueController::class, 'placeLookup'])->name('api.venues.place-lookup');
});
