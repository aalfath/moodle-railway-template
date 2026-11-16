<?php  // Moodle configuration for the Railway template. Values come from environment variables.
unset($CFG);
global $CFG;
$CFG = new stdClass();

$dburl = getenv('MOODLE_DATABASE_URL') ?: getenv('DATABASE_URL');
$db = $dburl ? parse_url($dburl) : [];

$CFG->dbtype    = 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost    = $db['host'] ?? 'localhost';
$CFG->dbname    = isset($db['path']) ? ltrim($db['path'], '/') : 'moodle';
$CFG->dbuser    = isset($db['user']) ? urldecode($db['user']) : 'moodle';
$CFG->dbpass    = isset($db['pass']) ? urldecode($db['pass']) : '';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = [
    'dbpersist' => 0,
    'dbport'    => $db['port'] ?? 5432,
    'dbsocket'  => '',
];

$CFG->wwwroot   = rtrim(getenv('MOODLE_URL') ?: 'http://localhost:8080', '/');
$CFG->dataroot  = getenv('MOODLE_DATAROOT') ?: '/var/www/moodledata';
$CFG->localcachedir = '/var/cache/moodle';
$CFG->admin     = 'admin';
$CFG->directorypermissions = 02777;

// Railway terminates HTTPS at its edge and forwards plain HTTP.
if (strpos($CFG->wwwroot, 'https://') === 0) {
    $CFG->sslproxy = true;
}
// Apache sends unknown paths to r.php (FallbackResource), so Moodle's router is configured.
$CFG->routerconfigured = true;
// The code lives in the image: plugins are added through the repo, not the web UI.
$CFG->disableupdateautodeploy = true;
$CFG->preventexecpath = true;

require_once(__DIR__ . '/lib/setup.php');
