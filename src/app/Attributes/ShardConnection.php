<?php

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ShardConnection
{
    public function __construct(
        public string $keyColumn = 'player_id', // Column used for sharding key
        public string $connectionPrefix = 'mysql_shard', // e.g., 'mysql_shard_1', 'mysql_shard_2'
        public int $numberOfShards = 2 // Total number of shards
    ) {}
}
