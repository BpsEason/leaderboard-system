<?php

namespace App\Utils;

class ShardResolver
{
    /**
     * Resolves the shard ID based on a numeric sharding key.
     *
     * @param int $shardingKey
     * @param int $numberOfShards
     * @return int The 0-indexed shard ID.
     */
    public static function resolveShardId(int $shardingKey, int $numberOfShards): int
    {
        if ($numberOfShards <= 0) {
            throw new \InvalidArgumentException("Number of shards must be a positive integer.");
        }
        return $shardingKey % $numberOfShards;
    }
}
