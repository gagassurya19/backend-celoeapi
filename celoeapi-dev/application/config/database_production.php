<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------
| PRODUCTION DATABASE CONFIGURATION
| -------------------------------------------------------------------
| Konfigurasi database untuk production environment
| CeloeAPI berjalan di server yang sama dengan Moodle
|
*/

$active_group = 'default';
$query_builder = TRUE;

// Production environment - CeloeAPI dan Moodle di server yang sama
// CeloeAPI berjalan di Docker container, Moodle dan MySQL berjalan native

// Detect if running inside Docker container
$is_docker = file_exists('/.dockerenv') || (isset($_SERVER['HOSTNAME']) && strpos($_SERVER['HOSTNAME'], 'docker') !== false);

if ($is_docker) {
    // Running inside Docker container - connect to host MySQL
    $db['default'] = array(
        'dsn'	=> '',
        'hostname' => 'host.docker.internal', // Connect to host MySQL
        'port'     => getenv('CELOEAPI_DB_PORT') ?: '3306',
        'username' => getenv('CELOEAPI_DB_USER') ?: 'moodleuser',
        'password' => getenv('CELOEAPI_DB_PASS') ?: 'moodlepass',
        'database' => getenv('CELOEAPI_DB_NAME') ?: 'celoeapi',
        'dbdriver' => 'mysqli',
        'dbprefix' => '',
        'pconnect' => FALSE,
        'db_debug' => FALSE, // Disable debug di production
        'cache_on' => FALSE,
        'cachedir' => '',
        'char_set' => 'utf8mb4',
        'dbcollat' => 'utf8mb4_unicode_ci',
        'swap_pre' => '',
        'encrypt' => FALSE,
        'compress' => FALSE,
        'stricton' => FALSE,
        'failover' => array(),
        'save_queries' => FALSE // Disable query logging di production
    );

    // Database Moodle yang sudah ada (untuk source data)
    $db['moodle'] = array(
        'dsn'	=> '',
        'hostname' => 'host.docker.internal', // Connect to host MySQL
        'port'     => getenv('MOODLE_DB_PORT') ?: '3306',
        'username' => getenv('MOODLE_DB_USER') ?: 'moodleuser',
        'password' => getenv('MOODLE_DB_PASS') ?: 'moodlepass',
        'database' => getenv('MOODLE_DB_NAME') ?: 'moodle',
        'dbdriver' => 'mysqli',
        'dbprefix' => 'mdl_', // Prefix tabel Moodle
        'pconnect' => FALSE,
        'db_debug' => FALSE, // Disable debug di production
        'cache_on' => FALSE,
        'cachedir' => '',
        'char_set' => 'utf8mb4',
        'dbcollat' => 'utf8mb4_unicode_ci',
        'swap_pre' => '',
        'encrypt' => FALSE,
        'compress' => FALSE,
        'stricton' => FALSE,
        'failover' => array(),
        'save_queries' => FALSE // Disable query logging di production
    );
} else {
    // Running on host machine (development/testing)
    $db['default'] = array(
        'dsn'	=> '',
        'hostname' => getenv('CELOEAPI_DB_HOST') ?: 'localhost',
        'port'     => getenv('CELOEAPI_DB_PORT') ?: '3306',
        'username' => getenv('CELOEAPI_DB_USER') ?: 'moodleuser',
        'password' => getenv('CELOEAPI_DB_PASS') ?: 'moodlepass',
        'database' => getenv('CELOEAPI_DB_NAME') ?: 'celoeapi',
        'dbdriver' => 'mysqli',
        'dbprefix' => '',
        'pconnect' => FALSE,
        'db_debug' => FALSE,
        'cache_on' => FALSE,
        'cachedir' => '',
        'char_set' => 'utf8mb4',
        'dbcollat' => 'utf8mb4_unicode_ci',
        'swap_pre' => '',
        'encrypt' => FALSE,
        'compress' => FALSE,
        'stricton' => FALSE,
        'failover' => array(),
        'save_queries' => FALSE
    );

    $db['moodle'] = array(
        'dsn'	=> '',
        'hostname' => getenv('MOODLE_DB_HOST') ?: 'localhost',
        'port'     => getenv('MOODLE_DB_PORT') ?: '3306',
        'username' => getenv('MOODLE_DB_USER') ?: 'moodleuser',
        'password' => getenv('MOODLE_DB_PASS') ?: 'moodlepass',
        'database' => getenv('MOODLE_DB_NAME') ?: 'moodle',
        'dbdriver' => 'mysqli',
        'dbprefix' => 'mdl_',
        'pconnect' => FALSE,
        'db_debug' => FALSE,
        'cache_on' => FALSE,
        'cachedir' => '',
        'char_set' => 'utf8mb4',
        'dbcollat' => 'utf8mb4_unicode_ci',
        'swap_pre' => '',
        'encrypt' => FALSE,
        'compress' => FALSE,
        'stricton' => FALSE,
        'failover' => array(),
        'save_queries' => FALSE
    );
}
