-- leaderboard-system/proxysql/init_proxysql.sql
-- This script will run on ProxySQL startup to configure it.
-- It will connect to the admin interface (default port 6032)

-- Load the runtime configuration into memory.
-- You might need to adjust based on when this script runs vs. ProxySQL internal state.
LOAD MYSQL VARIABLES TO RUNTIME;
LOAD MYSQL SERVERS TO RUNTIME;
LOAD MYSQL USERS TO RUNTIME;
LOAD MYSQL QUERY RULES TO RUNTIME;
LOAD MYSQL LOAD BALANCING TO RUNTIME;

-- --- Configure Backend MySQL Servers ---

-- Master (Hostgroup 10 - for writes, non-sharded reads if needed)
INSERT INTO mysql_servers (hostgroup_id, hostname, port, weight, max_connections) VALUES (10, 'mysql-master', 3306, 100, 1000);
-- Slave (Hostgroup 10 - for reads)
INSERT INTO mysql_servers (hostgroup_id, hostname, port, weight, max_connections) VALUES (10, 'mysql-slave', 3306, 100, 1000);

-- Shards (Hostgroups 20 and 21 - for sharded reads/writes)
INSERT INTO mysql_servers (hostgroup_id, hostname, port, weight, max_connections) VALUES (20, 'mysql-shard-1', 3306, 100, 1000);
INSERT INTO mysql_servers (hostgroup_id, hostname, port, weight, max_connections) VALUES (21, 'mysql-shard-2', 3306, 100, 1000);

-- Save to disk and apply to runtime
SAVE MYSQL SERVERS TO DISK;
LOAD MYSQL SERVERS TO RUNTIME;

-- --- Configure MySQL Users ---
INSERT INTO mysql_users (username, password, default_hostgroup, default_schema, active) VALUES ('${MYSQL_APP_USER}', '${MYSQL_APP_PASSWORD}', 10, '${MYSQL_DATABASE}', 1);

-- Save to disk and apply to runtime
SAVE MYSQL USERS TO DISK;
LOAD MYSQL USERS TO RUNTIME;

-- --- Configure Query Rules for Sharding and Read/Write Splitting ---

-- Rule 1: Route writes (INSERT, UPDATE, DELETE) for player_scores to the appropriate shard hostgroup.
-- This is a placeholder. Real sharding logic needs dynamic hostgroup_id.
-- ProxySQL doesn't dynamically calculate shard IDs from queries directly.
-- This requires the application to embed shard hints or use a more advanced ProxySQL setup (e.g., Lua scripting).
-- For this prototype, we'll demonstrate basic rules.
-- A more robust solution involves application-level sharding logic or ProxySQL's Lua scripting.

-- Example: Route INSERTs to player_scores to hostgroup 20 (shard 1). This is NOT dynamic sharding.
-- For actual sharding, the application might include comments like /*sh_id:X*/, and ProxySQL rules would parse that.
-- For a simple demo, we'll route all player_scores writes to shard 1 for now, and rely on `ShardConnection.php` to tell Laravel which DB to connect to.
-- If ProxySQL handles sharding, the Laravel side needs less specific connection logic.

-- Placeholder for sharded writes (player_scores table) - needs more sophisticated logic for actual sharding
-- For a real system, you'd likely have multiple rules or a Lua script to map `player_id` to a `hostgroup_id`.
-- For now, let's assume `player_scores` could go to different shards based on `player_id`.
-- The application will be responsible for telling ProxySQL (e.g., via comments) which shard to use.

-- Route INSERT/UPDATE/DELETE on 'player_scores' to master for now. The application layer (Laravel + ShardResolver)
-- will handle which specific shard is intended, potentially setting a connection attribute that ProxySQL
-- *could* use if rules were more complex. For a robust ProxySQL-driven sharding, this would need Lua scripts.
-- Given the current plan for `ShardConnection.php` in Laravel, ProxySQL primarily acts as a connection pool
-- and potentially a simple read/write splitter if the Laravel app *doesn't* specify a hostgroup.

-- Let's define simple read/write splitting for generic queries first, and assume sharding is mostly app-driven.
-- If the application sends specific `/*hg:X*/` comments, we can use those.

-- Default read/write split (if no specific hostgroup is given by app)
INSERT INTO mysql_query_rules (rule_id, match_pattern, destination_hostgroup, active, apply) VALUES
(10, '^SELECT', 10, 1, 1), -- Route all SELECTs to hostgroup 10 (Master/Slave for reads)
(20, '^(INSERT|UPDATE|DELETE)', 10, 1, 1); -- Route all writes to hostgroup 10 (Master for writes)

-- For sharded tables, the Laravel application will specify the connection name (e.g., 'shard1_conn', 'shard2_conn').
-- ProxySQL itself doesn't automatically shard based on data without Lua scripting or explicit hints.
-- The current architecture implies Laravel `ShardConnection` attribute sets the connection.
-- If Laravel connects to `proxysql:6033` and wants to hit `mysql-shard-1`, it needs to signal this.
-- This could be done by sending a SQL comment like `/*proxy:hostgroup=20*/ SELECT ...`
-- For a simple setup, we'll assume Laravel explicitly connects to different logical DB connections
-- configured in `database.php` which all point to ProxySQL, but use connection attributes.

-- Let's make rules that will recognize hints from Laravel for sharding
INSERT INTO mysql_query_rules (rule_id, match_pattern, destination_hostgroup, active, apply, comments) VALUES
(100, '.* /*shard:1*/', 20, 1, 1, 'Route to Shard 1'),
(101, '.* /*shard:2*/', 21, 1, 1, 'Route to Shard 2');

-- Save to disk and apply to runtime
SAVE MYSQL QUERY RULES TO DISK;
LOAD MYSQL QUERY RULES TO RUNTIME;