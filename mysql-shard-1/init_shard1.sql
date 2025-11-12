-- leaderboard-system/mysql-shard-1/init_shard1.sql
CREATE DATABASE IF NOT EXISTS ${MYSQL_DATABASE};
USE ${MYSQL_DATABASE};

-- Create the sharded table (player_scores)
CREATE TABLE IF NOT EXISTS player_scores (
    player_id BIGINT UNSIGNED NOT NULL,
    game_id INT UNSIGNED NOT NULL,
    score INT UNSIGNED NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (player_id, game_id)
);

-- Create application user and grant permissions
CREATE USER '${MYSQL_APP_USER}'@'%' IDENTIFIED BY '${MYSQL_APP_PASSWORD}';
GRANT ALL PRIVILEGES ON ${MYSQL_DATABASE}.* TO '${MYSQL_APP_USER}'@'%';
FLUSH PRIVILEGES;