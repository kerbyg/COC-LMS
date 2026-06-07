<?php
/**
 * Production Database Configuration
 * ----------------------------------
 * 1. Copy this file: config/db.config.example.php → config/db.config.php
 * 2. Fill in your InfinityFree (or cPanel) MySQL credentials below
 * 3. DO NOT commit db.config.php — it is listed in .gitignore
 *
 * Where to find your credentials on InfinityFree:
 *   Control Panel → MySQL Databases → your database row
 */

define('DB_HOST',    'sql100.infinityfree.com');   // shown in cPanel MySQL section
define('DB_NAME',    'your_database_name');         // e.g. if0_12345678_cit_lms
define('DB_USER',    'your_database_user');         // e.g. if0_12345678
define('DB_PASS',    'your_database_password');
define('DB_CHARSET', 'utf8mb4');
