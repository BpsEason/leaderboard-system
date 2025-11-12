#!/bin/bash
# leaderboard-system/scripts/init_all_dbs.sh

echo "Waiting for all MySQL and ProxySQL services to be healthy..."

# Wait for mysql-master to be healthy
docker-compose exec -T mysql-master mysqladmin ping -h localhost --silent
while [ $? -ne 0 ]; do
  echo "Waiting for mysql-master to be up..."
  sleep 5
  docker-compose exec -T mysql-master mysqladmin ping -h localhost --silent
done
echo "mysql-master is up."

# Wait for mysql-slave
docker-compose exec -T mysql-slave mysqladmin ping -h localhost --silent
while [ $? -ne 0 ]; do
  echo "Waiting for mysql-slave to be up..."
  sleep 5
  docker-compose exec -T mysql-slave mysqladmin ping -h localhost --silent
done
echo "mysql-slave is up."

# Wait for mysql-shard-1
docker-compose exec -T mysql-shard-1 mysqladmin ping -h localhost --silent
while [ $? -ne 0 ]; do
  echo "Waiting for mysql-shard-1 to be up..."
  sleep 5
  docker-compose exec -T mysql-shard-1 mysqladmin ping -h localhost --silent
done
echo "mysql-shard-1 is up."

# Wait for mysql-shard-2
docker-compose exec -T mysql-shard-2 mysqladmin ping -h localhost --silent
while [ $? -ne 0 ]; do
  echo "Waiting for mysql-shard-2 to be up..."
  sleep 5
  docker-compose exec -T mysql-shard-2 mysqladmin ping -h localhost --silent
done
echo "mysql-shard-2 is up."

# Wait for proxysql admin port to be available
docker-compose exec -T proxysql mysql -uadmin -padmin -h127.0.0.1 -P6032 -e "SELECT 1;" > /dev/null 2>&1
while [ $? -ne 0 ]; do
  echo "Waiting for proxysql admin interface to be up..."
  sleep 5
  docker-compose exec -T proxysql mysql -uadmin -padmin -h127.0.0.1 -P6032 -e "SELECT 1;" > /dev/null 2>&1
done
echo "proxysql admin interface is up."


# --- Configure MySQL Replication (Master-Slave) ---
echo "Configuring MySQL Master-Slave replication..."
# Get master's log file and position
MASTER_LOG_FILE=$(docker-compose exec mysql-master mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "SHOW MASTER STATUS;" | grep -v 'File' | awk '{print $1}')
MASTER_LOG_POS=$(docker-compose exec mysql-master mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "SHOW MASTER STATUS;" | grep -v 'File' | awk '{print $2}')

if [ -z "$MASTER_LOG_FILE" ] || [ -z "$MASTER_LOG_POS" ]; then
    echo "ERROR: Could not get master status. Aborting replication setup."
    exit 1
fi

echo "Master Log File: $MASTER_LOG_FILE, Master Log Position: $MASTER_LOG_POS"

# Configure slave
docker-compose exec mysql-slave mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "
  CHANGE MASTER TO
  MASTER_HOST='mysql-master',
  MASTER_USER='repl_user',
  MASTER_PASSWORD='repl_password',
  MASTER_PORT=3306,
  MASTER_LOG_FILE='${MASTER_LOG_FILE}',
  MASTER_LOG_POS=${MASTER_LOG_POS};
  START SLAVE;
"
if [ $? -ne 0 ]; then
    echo "ERROR: Failed to configure mysql-slave. Check logs."
    exit 1
fi
echo "MySQL Master-Slave replication configured and started."


# --- Configure ProxySQL ---
echo "Configuring ProxySQL backend servers, users, and query rules..."

# Use ProxySQL admin client to configure
PROXYSQL_CMD="mysql -uadmin -padmin -h127.0.0.1 -P6032"

# Backend Servers
echo "Adding backend MySQL servers to ProxySQL..."
$PROXYSQL_CMD -e "INSERT INTO mysql_servers (hostgroup_id, hostname, port, weight, max_connections) VALUES (10, 'mysql-master', 3306, 100, 1000);"
$PROXYSQL_CMD -e "INSERT INTO mysql_servers (hostgroup_id, hostname, port, weight, max_connections) VALUES (10, 'mysql-slave', 3306, 100, 1000);"
$PROXYSQL_CMD -e "INSERT INTO mysql_servers (hostgroup_id, hostname, port, weight, max_connections) VALUES (20, 'mysql-shard-1', 3306, 100, 1000);"
$PROXYSQL_CMD -e "INSERT INTO mysql_servers (hostgroup_id, hostname, port, weight, max_connections) VALUES (21, 'mysql-shard-2', 3306, 100, 1000);"
$PROXYSQL_CMD -e "LOAD MYSQL SERVERS TO RUNTIME;"
$PROXYSQL_CMD -e "SAVE MYSQL SERVERS TO DISK;"
echo "Backend MySQL servers added."

# Users
echo "Adding application user to ProxySQL..."
$PROXYSQL_CMD -e "INSERT INTO mysql_users (username, password, default_hostgroup, default_schema, active) VALUES ('${MYSQL_APP_USER}', '${MYSQL_APP_PASSWORD}', 10, '${MYSQL_DATABASE}', 1);"
$PROXYSQL_CMD -e "LOAD MYSQL USERS TO RUNTIME;"
$PROXYSQL_CMD -e "SAVE MYSQL USERS TO DISK;"
echo "Application user added."

# Query Rules (Read/Write Split and Sharding hints)
echo "Adding query rules to ProxySQL..."
# Rule for default SELECTs (goes to HG 10 - master/slave)
$PROXYSQL_CMD -e "INSERT INTO mysql_query_rules (rule_id, match_pattern, destination_hostgroup, active, apply) VALUES (100, '^SELECT', 10, 1, 1);"

# Rules for sharded writes/reads (app sends /*shard:X*/ hint)
$PROXYSQL_CMD -e "INSERT INTO mysql_query_rules (rule_id, match_pattern, destination_hostgroup, active, apply, comments) VALUES (200, '.* /*shard:0*/', 20, 1, 1, 'Route to Shard 0 (mysql-shard-1)');"
$PROXYSQL_CMD -e "INSERT INTO mysql_query_rules (rule_id, match_pattern, destination_hostgroup, active, apply, comments) VALUES (201, '.* /*shard:1*/', 21, 1, 1, 'Route to Shard 1 (mysql-shard-2)');"

# Example for write split (if not sharded) - routes INSERT/UPDATE/DELETE to master (HG 10)
# This rule should be prioritized if not caught by a more specific sharding rule.
# For sharded tables, the app should use the /*shard:X*/ hint.
$PROXYSQL_CMD -e "INSERT INTO mysql_query_rules (rule_id, match_pattern, destination_hostgroup, active, apply) VALUES (900, '^(INSERT|UPDATE|DELETE)', 10, 1, 1);" # Generic write rule

$PROXYSQL_CMD -e "LOAD MYSQL QUERY RULES TO RUNTIME;"
$PROXYSQL_CMD -e "SAVE MYSQL QUERY RULES TO DISK;"
echo "Query rules added to ProxySQL."

echo "ProxySQL configuration complete."

# --- Laravel Application Setup ---
echo "Running Laravel migrations and optimizing application..."
docker-compose exec php-fpm composer install --no-dev --optimize-autoloader
docker-compose exec php-fpm php artisan key:generate
# Note: Migrations for sharded tables are handled by init_shardX.sql.
# If you have non-sharded tables, you might run `php artisan migrate --force` here.
# For `player_scores`, the table is pre-created by init_shardX.sql
# But if you want Laravel to track it, you can run a custom command like:
# docker-compose exec php-fpm php artisan migrate --path=/database/migrations/2023_xx_xx_create_player_scores_table.php --database=mysql_shard_0
# docker-compose exec php-fpm php artisan migrate --path=/database/migrations/2023_xx_xx_create_player_scores_table.php --database=mysql_shard_1
# OR just ensure the init scripts are sufficient.

# For now, let's assume migrations for player_scores are handled by init_shardX.sql scripts.
# If you add more, non-sharded tables, you'll need `php artisan migrate`.

echo "Laravel application setup complete."
echo "All services initialized and configured successfully."