<?php

namespace App\Http\Controllers;

use App\Http\Requests\GetLeaderboardRequest;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;

class LeaderboardController extends Controller
{
    public function __construct(protected LeaderboardService $leaderboardService)
    {
        //
    }

    /**
     * Get the leaderboard for a specific game.
     *
     * @param GetLeaderboardRequest $request
     * @return JsonResponse
     */
    public function getLeaderboard(GetLeaderboardRequest $request): JsonResponse
    {
        $gameId = $request->input('game_id');
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 100);

        $leaderboard = $this->leaderboardService->getLeaderboard($gameId, $offset, $limit);

        return response()->json([
            'game_id' => $gameId,
            'leaderboard' => $leaderboard,
            'offset' => $offset,
            'limit' => $limit,
        ]);
    }

    /**
     * Get a player's rank in a specific game's leaderboard.
     *
     * @param int $gameId
     * @param int $playerId
     * @return JsonResponse
     */
    public function getPlayerRank(int $gameId, int $playerId): JsonResponse
    {
        $playerRank = $this->leaderboardService->getPlayerRank($gameId, $playerId);

        return response()->json([
            'game_id' => $gameId,
            'player_id' => $playerId,
            'rank' => $playerRank['rank'],
            'score' => $playerRank['score'],
        ]);
    }
}
