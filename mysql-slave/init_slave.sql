-- leaderboard-system/mysql-slave/init_slave.sql
-- Wait for master to be ready before configuring replication (ProxySQL will handle read/write splitting)
-- This script primarily ensures the database and user exist if not already copied from master.
CREATE DATABASE IF NOT EXISTS ${MYSQL_DATABASE};
CREATE USER '${MYSQL_APP_USER}'@'%' IDENTIFIED BY '${MYSQL_APP_PASSWORD}';
GRANT SELECT ON ${MYSQL_DATABASE}.* TO '${MYSQL_APP_USER}'@'%'; -- Slave typically only needs SELECT
FLUSH PRIVILEGES;

-- Slave will be configured for replication AFTER master is ready, typically via an external script or manually.
-- For docker-compose, this might involve running a command after all services are up.
-- Example (DO NOT run here directly, this is for illustration for an external script):
-- CHANGE MASTER TO MASTER_HOST='mysql-master', MASTER_USER='repl_user', MASTER_PASSWORD='repl_password', MASTER_PORT=3306;
-- START SLAVE;