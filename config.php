<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = getenv('MOODLE_DATABASE_TYPE') ?: 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost    = getenv('MOODLE_DATABASE_HOST') ?: 'mariadb';
$CFG->dbname    = getenv('MOODLE_DATABASE_NAME') ?: 'moodle';
$CFG->dbuser    = getenv('MOODLE_DATABASE_USER') ?: 'moodle';
$CFG->dbpass    = getenv('MOODLE_DATABASE_PASSWORD') ?: '';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = [
    'dbpersist' => 0,
    'dbport' => getenv('MOODLE_DATABASE_PORT_NUMBER') ?: 3306,
    'dbsocket' => '',
    'dbcollation' => 'utf8mb4_unicode_ci',
];

$CFG->wwwroot   = getenv('MOODLE_HOST') ?: 'https://hu-techhub.org';
$CFG->dataroot  = '/var/moodledata';
$CFG->admin     = 'admin';

// Session management via Redis
if (getenv('REDIS_HOST')) {
    $CFG->session_handler_class = '\core\session\redis';
    $CFG->session_redis_host = getenv('REDIS_HOST');
    $CFG->session_redis_port = 6379;
    $CFG->session_redis_prefix = 'mdl_sess_';
    $CFG->session_redis_acquire_lock_timeout = 120;
    $CFG->session_redis_lock_expire = 7200;
}

// SSL & Reverse Proxy Handling (Required for Dokploy / Traefik / Nginx)
$CFG->sslproxy = 1;
$CFG->reverseproxy = 1;

$CFG->directorypermissions = 02777;

require_once(__DIR__ . '/lib/setup.php');
