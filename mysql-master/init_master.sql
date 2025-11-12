-- leaderboard-system/mysql-master/init_master.sql
-- Create replication user
CREATE USER 'repl_user'@'%' IDENTIFIED BY 'repl_password';
GRANT REPLICATION SLAVE ON *.* TO 'repl_user'@'%';
FLUSH PRIVILEGES;

-- Create application user and grant permissions
CREATE USER '${MYSQL_APP_USER}'@'%' IDENTIFIED BY '${MYSQL_APP_PASSWORD}';
GRANT ALL PRIVILEGES ON ${MYSQL_DATABASE}.* TO '${MYSQL_APP_USER}'@'%';
FLUSH PRIVILEGES;

-- Ensure the database exists
CREATE DATABASE IF NOT EXISTS ${MYSQL_DATABASE};

-- You can add initial schema for non-sharded tables here if any
-- For player_scores, migrations will handle it on shards