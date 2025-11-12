<?php

use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\ScoreController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {
    // Score management
    Route::post('/scores', [ScoreController::class, 'storeScore'])->name('scores.store');

    // Leaderboard queries
    Route::get('/leaderboards', [LeaderboardController::class, 'getLeaderboard'])->name('leaderboards.get');
    Route::get('/leaderboards/{gameId}/player/{playerId}', [LeaderboardController::class, 'getPlayerRank'])->name('leaderboards.player_rank');
});
