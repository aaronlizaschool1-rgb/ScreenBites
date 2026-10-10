<?php
/**
 * ScreenBites - Database Credentials & PDO Driver Options
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

return [
    'driver'    => 'mysql',
    'host'      => '127.0.0.1',
    'port'      => 3306,
    'database'  => 'screenbites_db',
    'username'  => 'root',          // Default XAMPP MySQL user
    'password'  => '',              // Default XAMPP MySQL password is empty
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]
];