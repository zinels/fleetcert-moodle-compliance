<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->dbtype = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost = 'db';
$CFG->dbname = getenv('MYSQL_DATABASE');
$CFG->dbuser = getenv('MYSQL_USER');
$CFG->dbpass = getenv('MYSQL_PASSWORD');
$CFG->prefix = 'mdl_';
$CFG->dboptions = ['dbcollation' => 'utf8mb4_unicode_ci'];
$CFG->wwwroot = getenv('MOODLE_URL');
$CFG->dataroot = '/var/www/moodledata';
$CFG->admin = 'admin';
$CFG->directorypermissions = 02770;
require_once(__DIR__ . '/lib/setup.php');
