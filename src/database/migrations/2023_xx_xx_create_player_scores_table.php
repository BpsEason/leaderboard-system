<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // This migration will run on each sharded database.
        // The DatabaseConnectionServiceProvider ensures the correct connection is used for the Model.
        // For actual migration execution across shards, you'd need a custom Artisan command.
        // For this prototype, we'll assume manual execution or a script handles it.

        // We create the table on the 'default' connection, which for a sharded model
        // means the model's assigned shard connection.
        // However, standard `php artisan migrate` will only run on the `default` connection.
        // To migrate on shards, you'd need to iterate through connections:
        // foreach (['mysql_shard_0', 'mysql_shard_1'] as $conn) {
        //     Schema::connection($conn)->create('player_scores', function (Blueprint $table) { ... });
        // }
        // For simplicity in this `docker-compose` setup, the `init_shardX.sql` scripts will create this table.
        // This migration file is kept for Laravel's internal migration tracking.

        // If you run `php artisan migrate`, it will attempt to create this on the default connection.
        // Ensure your `init_shardX.sql` creates it.
        // For a more robust solution, the `init_all_dbs.sh` script or a dedicated migration command
        // would handle applying this schema to all shards.

        Schema::create('player_scores', function (Blueprint $table) {
            $table->unsignedBigInteger('player_id');
            $table->unsignedInteger('game_id');
            $table->unsignedInteger('score')->default(0);
            $table->timestamps();

            $table->primary(['player_id', 'game_id']); // Composite primary key
            $table->index('game_id'); // Index for querying all scores of a game
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_scores');
    }
};
