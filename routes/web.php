<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubMemberController;
use App\Http\Controllers\ClubStaffController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PairingController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentExportController;
use App\Http\Controllers\TournamentParticipantController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/locale/{locale}', [LocaleController::class, 'update'])
    ->whereIn('locale', config('locales.supported'))
    ->name('locale.update');

// Clubs
Route::get('/clubs', [ClubController::class, 'index'])->name('clubs.index');

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/clubs/create', [ClubController::class, 'create'])->name('clubs.create');
    Route::post('/clubs', [ClubController::class, 'store'])->name('clubs.store');
    Route::post('/clubs/{club}/archive', [ClubController::class, 'archive'])->name('clubs.archive');
    Route::post('/clubs/{club}/restore', [ClubController::class, 'restore'])->name('clubs.restore');
    Route::delete('/clubs/{club}', [ClubController::class, 'destroy'])->name('clubs.destroy');
});

Route::middleware(['auth', 'role:arbiter,admin'])->group(function () {
    Route::get('/clubs/{club}/edit', [ClubController::class, 'edit'])->name('clubs.edit');
    Route::put('/clubs/{club}', [ClubController::class, 'update'])->name('clubs.update');
    Route::patch('/clubs/{club}', [ClubController::class, 'update']);
    Route::post('/clubs/{club}/staff', [ClubStaffController::class, 'store'])->name('clubs.staff.store');
    Route::delete('/clubs/{club}/staff/{user}', [ClubStaffController::class, 'destroy'])->name('clubs.staff.destroy');
    Route::post('/clubs/{club}/members', [ClubMemberController::class, 'store'])->name('clubs.members.store');
    Route::delete('/clubs/{club}/members/{player}', [ClubMemberController::class, 'destroy'])->name('clubs.members.destroy');
});

Route::get('/clubs/{club}', [ClubController::class, 'show'])->name('clubs.show');

// Players
Route::get('/players', [PlayerController::class, 'index'])->name('players.index');

Route::middleware(['auth', 'role:arbiter,admin'])->group(function () {
    Route::get('/players/create', [PlayerController::class, 'create'])->name('players.create');
    Route::post('/players', [PlayerController::class, 'store'])->name('players.store');
    Route::get('/players/import', [PlayerController::class, 'importForm'])->name('players.import');
    Route::post('/players/import', [PlayerController::class, 'import'])->name('players.import.store');
    Route::get('/players/export/{format}', [PlayerController::class, 'export'])->name('players.export');
    Route::get('/players/{player}/edit', [PlayerController::class, 'edit'])->name('players.edit');
    Route::put('/players/{player}', [PlayerController::class, 'update'])->name('players.update');
    Route::patch('/players/{player}', [PlayerController::class, 'update']);
    Route::delete('/players/{player}', [PlayerController::class, 'destroy'])->name('players.destroy');
});

Route::get('/players/{player}', [PlayerController::class, 'show'])->name('players.show');

// Tournaments
Route::get('/tournaments', [TournamentController::class, 'index'])->name('tournaments.index');

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('admin.users.create');
        Route::post('/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::post('/users/{user}/block', [UserController::class, 'block'])->name('admin.users.block');
        Route::post('/users/{user}/unblock', [UserController::class, 'unblock'])->name('admin.users.unblock');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('admin.users.reset-password');
    });
});

Route::middleware(['auth', 'role:arbiter,admin'])->group(function () {
    Route::get('/tournaments/create', [TournamentController::class, 'create'])->name('tournaments.create');
    Route::post('/tournaments', [TournamentController::class, 'store'])->name('tournaments.store');
    Route::get('/tournaments/{tournament}/edit', [TournamentController::class, 'edit'])->name('tournaments.edit');
    Route::put('/tournaments/{tournament}', [TournamentController::class, 'update'])->name('tournaments.update');
    Route::patch('/tournaments/{tournament}', [TournamentController::class, 'update']);
    Route::delete('/tournaments/{tournament}', [TournamentController::class, 'destroy'])->name('tournaments.destroy');
    Route::post('/tournaments/{tournament}/start', [TournamentController::class, 'start'])->name('tournaments.start');
    Route::post('/tournaments/{tournament}/complete', [TournamentController::class, 'complete'])->name('tournaments.complete');
    Route::post('/tournaments/{tournament}/participants', [TournamentParticipantController::class, 'store'])->name('tournaments.participants.store');
    Route::delete('/tournaments/{tournament}/participants/{participant}', [TournamentParticipantController::class, 'destroy'])->name('tournaments.participants.destroy');
    Route::post('/tournaments/{tournament}/rounds', [PairingController::class, 'generate'])->name('tournaments.rounds.generate');
    Route::put('/tournaments/{tournament}/pairings/{pairing}/result', [PairingController::class, 'updateResult'])->name('tournaments.pairings.result');
});

Route::get('/tournaments/{tournament}', [TournamentController::class, 'show'])->name('tournaments.show');

Route::get('/tournaments/{tournament}/export/{format}', [TournamentExportController::class, 'export'])->name('tournaments.export')->middleware(['auth', 'role:arbiter,admin']);

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
