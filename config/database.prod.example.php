<?php
/**
 * cPanel Production Database Override Template
 *
 * Deployment steps on shared cPanel hosting:
 * 1. Copy/rename this file to `config/database.prod.php` on the server.
 * 2. Fill in the MySQL database, username, and password created in cPanel -> MySQL Databases.
 * 3. Do NOT commit `config/database.prod.php` to Git (it is excluded in `.gitignore`).
 */

$db_host = 'localhost';
$db_port = '3306';
$db_name = 'cpaneluser_elfaith';
$db_user = 'cpaneluser_dbuser';
$db_pass = 'REPLACE_WITH_STRONG_PASSWORD';

