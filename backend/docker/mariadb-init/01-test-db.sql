-- Pre-create the test database and grant the application user full access.
-- MariaDB's docker image only grants MYSQL_USER on MYSQL_DATABASE; CI starts
-- with a fresh volume and the test harness (tests/bootstrap.php) needs to
-- create + truncate `kytario_test`. This script only runs on the very first
-- DB startup (i.e. when the data volume is empty), which is exactly the CI
-- shape.
CREATE DATABASE IF NOT EXISTS `kytario_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `kytario_test`.* TO 'kytario'@'%';
FLUSH PRIVILEGES;
