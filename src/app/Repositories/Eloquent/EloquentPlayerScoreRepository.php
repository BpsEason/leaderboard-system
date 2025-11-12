<?php

namespace App\Repositories\Eloquent;

use App\Models\PlayerScore;
use App\Repositories\Contracts\PlayerScoreRepositoryInterface;
use App\Attributes\ShardConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionClass;

class EloquentPlayerScoreRepository implements PlayerScoreRepositoryInterface
{
    public function __construct(protected PlayerScore $model)
    {
        //
    }

    /**
     * Find a player's score by player_id and game_id.
     *
     * @param int $playerId
     * @param int $gameId
     * @return PlayerScore|null
     */
    public function findByPlayerAndGame(int $playerId, int $gameId): ?PlayerScore
    {
        // Instantiate a model with the sharding key to resolve the correct connection
        $model = (new PlayerScore())->fill(['player_id' => $playerId]);
        $model->setConnection($model->getConnectionName()); // Ensure connection is set by provider

        return $model->where('player_id', $playerId)
            ->where('game_id', $gameId)
            ->first();
    }

    /**
     * Create or update a player's score.
     *
     * @param int $playerId
     * @param int $gameId
     * @param int $score
     * @return PlayerScore
     */
    public function updateOrCreate(int $playerId, int $gameId, int $score): PlayerScore
    {
        // Instantiate a model with the sharding key to resolve the correct connection
        $model = (new PlayerScore())->fill(['player_id' => $playerId]);
        $model->setConnection($model->getConnectionName()); // Ensure connection is set by provider

        return $model->updateOrCreate(
            ['player_id' => $playerId, 'game_id' => $gameId],
            ['score' => $score]
        );
    }

    /**
     * Get all scores for a specific game across all shards.
     * This method is primarily for rebuilding leaderboards and should be used with caution due to potential performance implications.
     *
     * @param int $gameId
     * @return Collection<PlayerScore>
     */
    public function getAllScoresForGame(int $gameId): Collection
    {
        $allScores = new Collection();
        $reflection = new ReflectionClass($this->model);
        $attributes = $reflection->getAttributes(ShardConnection::class);

        if (empty($attributes)) {
            // If no sharding attribute, just query the default connection
            return $this->model->where('game_id', $gameId)->get();
        }

        /** @var ShardConnection $shardConnectionAttribute */
        $shardConnectionAttribute = $attributes[0]->newInstance();
        $connectionPrefix = $shardConnectionAttribute->connectionPrefix;
        $numberOfShards = $shardConnectionAttribute->numberOfShards;

        for ($i = 0; $i < $numberOfShards; $i++) {
            $connectionName = "{$connectionPrefix}_{$i}";
            if (config("database.connections.{$connectionName}")) {
                $scoresFromShard = $this->model->setConnection($connectionName)
                    ->where('game_id', $gameId)
                    ->get();
                $allScores = $allScores->merge($scoresFromShard);
            } else {
                \Log::warning("Sharded connection '{$connectionName}' not found during getAllScoresForGame.");
            }
        }

        return $allScores;
    }

    /**
     * Get all unique game IDs from all shards.
     * @return Collection<int>
     */
    public function getAllGameIds(): Collection
    {
        $allGameIds = new Collection();
        $reflection = new ReflectionClass($this->model);
        $attributes = $reflection->getAttributes(ShardConnection::class);

        if (empty($attributes)) {
            return $this->model->distinct('game_id')->pluck('game_id');
        }

        /** @var ShardConnection $shardConnectionAttribute */
        $shardConnectionAttribute = $attributes[0]->newInstance();
        $connectionPrefix = $shardConnectionAttribute->connectionPrefix;
        $numberOfShards = $shardConnectionAttribute->numberOfShards;

        for ($i = 0; $i < $numberOfShards; $i++) {
            $connectionName = "{$connectionPrefix}_{$i}";
            if (config("database.connections.{$connectionName}")) {
                $gameIdsFromShard = $this->model->setConnection($connectionName)
                    ->distinct('game_id')
                    ->pluck('game_id');
                $allGameIds = $allGameIds->merge($gameIdsFromShard);
            } else {
                \Log::warning("Sharded connection '{$connectionName}' not found during getAllGameIds.");
            }
        }

        return $allGameIds->unique()->values();
    }
}
