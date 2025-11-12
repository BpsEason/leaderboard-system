<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScoreRequest;
use App\Services\ScoreService;
use Illuminate\Http\JsonResponse;

class ScoreController extends Controller
{
    public function __construct(protected ScoreService $scoreService)
    {
        //
    }

    /**
     * Store a player's score.
     *
     * @param StoreScoreRequest $request
     * @return JsonResponse
     */
    public function storeScore(StoreScoreRequest $request): JsonResponse
    {
        $playerId = $request->input('player_id');
        $gameId = $request->input('game_id');
        $score = $request->input('score');

        $playerScore = $this->scoreService->storeScore($playerId, $gameId, $score);

        return response()->json([
            'message' => 'Score updated successfully',
            'player_score' => $playerScore,
        ], 201);
    }
}
