<?php

namespace App\Providers;

use App\Attributes\ShardConnection;
use App\Utils\ShardResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

class DatabaseConnectionServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Model::preventLazyLoading(! app()->isProduction());

        // This static method will be called whenever an Eloquent model is instantiated.
        Model::retrieved(function (Model $model) {
            $this->applyShardedConnection($model);
        });

        Model::creating(function (Model $model) {
            $this->applyShardedConnection($model);
        });

        Model::updating(function (Model $model) {
            $this->applyShardedConnection($model);
        });

        Model::deleting(function (Model $model) {
            $this->applyShardedConnection($model);
        });

        // For queries not tied to an instance, e.g., Model::query() or static methods
        // This is trickier and often requires explicit setting or a global query scope.
        // For simplicity, we assume models are often instantiated or have their sharding key present.
        // A more advanced solution might involve extending the Query Builder.
    }

    /**
     * Apply the sharded database connection to the model if it has the ShardConnection attribute.
     *
     * @param Model $model
     * @return void
     */
    protected function applyShardedConnection(Model $model)
    {
        $reflection = new ReflectionClass($model);
        $attributes = $reflection->getAttributes(ShardConnection::class);

        if (empty($attributes)) {
            return;
        }

        /** @var ShardConnection $shardConnectionAttribute */
        $shardConnectionAttribute = $attributes[0]->newInstance();

        $shardingKeyColumn = $shardConnectionAttribute->keyColumn;
        $connectionPrefix = $shardConnectionAttribute->connectionPrefix;
        $numberOfShards = $shardConnectionAttribute->numberOfShards;

        // Ensure the sharding key is present in the model's attributes
        if (! $model->offsetExists($shardingKeyColumn)) {
            // For operations like `Model::all()` or `Model::where()` without the key,
            // this strategy needs refinement. Typically, sharded queries always
            // include the sharding key.
            // For now, we'll default to the primary connection or throw an error.
            // For example, when fetching all leaderboard data from shards for rebuild,
            // the `RebuildLeaderboards` command will explicitly iterate through connections.
            return;
        }

        $shardingKeyValue = $model->getAttribute($shardingKeyColumn);
        $shardId = ShardResolver::resolveShardId($shardingKeyValue, $numberOfShards);
        $connectionName = "{$connectionPrefix}_{$shardId}";

        // Check if this connection actually exists in config/database.php
        if (config("database.connections.{$connectionName}")) {
            $model->setConnection($connectionName);
        } else {
            // Fallback or throw exception if the sharded connection is not configured
            \Log::warning("Sharded connection '{$connectionName}' not found for model " . get_class($model));
            // Optionally, throw an exception
            // throw new \RuntimeException("Sharded connection '{$connectionName}' not configured.");
        }
    }
}
