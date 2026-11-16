<?php
// Database helper for start.sh (runs before Moodle's own code, straight over pg_*).
//   php dbstate.php            prints "empty", "partial" (an install that never finished) or "installed"
//   php dbstate.php reset      drops the mdl_ tables of a partial install (refuses on an installed site)
//   php dbstate.php mark       records that the first install finished
//   php dbstate.php unlock     clears a stale upgrade lock left by a stopped container
// Exit code 2 when the database can't be reached.
$url = getenv('MOODLE_DATABASE_URL') ?: getenv('DATABASE_URL');
$d = $url ? parse_url($url) : [];
$conn = @pg_connect(sprintf("host='%s' port='%s' dbname='%s' user='%s' password='%s' connect_timeout=5",
    addslashes($d['host'] ?? ''), $d['port'] ?? 5432, addslashes(ltrim($d['path'] ?? '', '/')),
    addslashes(urldecode($d['user'] ?? '')), addslashes(urldecode($d['pass'] ?? ''))));
if (!$conn) { fwrite(STDERR, "database unreachable\n"); exit(2); }

function one($conn, $sql) { $r = pg_fetch_row(pg_query($conn, $sql)); return $r[0]; }
function has($conn, $table) { return one($conn, "SELECT to_regclass('public.$table') IS NOT NULL") === 't'; }

// "installed": the template's own marker is set (start.sh writes it after the installer exits 0),
// or the database holds a real site (more than admin + guest, someone has logged in, or courses).
// "partial": Moodle tables exist but neither of the above, i.e. a first install that was stopped.
function state($conn) {
    if (!has($conn, 'mdl_config')) return 'empty';
    if ((int)one($conn, "SELECT count(*) FROM mdl_config WHERE name = 'railway_install_complete'") > 0) return 'installed';
    if (!has($conn, 'mdl_user') || !has($conn, 'mdl_course')) return 'partial';
    $users = (int)one($conn, "SELECT count(*) FROM mdl_user");
    $logins = (int)one($conn, "SELECT count(*) FROM mdl_user WHERE lastlogin > 0 OR currentlogin > 0");
    $courses = (int)one($conn, "SELECT count(*) FROM mdl_course");
    return ($users <= 2 && $logins === 0 && $courses <= 1) ? 'partial' : 'installed';
}

$cmd = $argv[1] ?? 'state';
$state = state($conn);
if ($cmd === 'state') { echo $state, "\n"; exit(0); }
if ($cmd === 'mark') {
    pg_query($conn, "INSERT INTO mdl_config (name, value) SELECT 'railway_install_complete', '1'
                     WHERE NOT EXISTS (SELECT 1 FROM mdl_config WHERE name = 'railway_install_complete')");
    exit(0);
}
if ($cmd === 'unlock') {
    if ($state === 'installed') pg_query($conn, "DELETE FROM mdl_config WHERE name = 'upgraderunning'");
    exit(0);
}
if ($cmd === 'reset') {
    if ($state !== 'partial') { fwrite(STDERR, "refusing to reset: database is $state\n"); exit(1); }
    $res = pg_query($conn, "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE 'mdl\\_%'");
    $n = 0;
    while ($row = pg_fetch_row($res)) {
        pg_query($conn, 'DROP TABLE IF EXISTS "' . str_replace('"', '""', $row[0]) . '" CASCADE');
        $n++;
    }
    echo "dropped $n tables\n";
    exit(0);
}
fwrite(STDERR, "unknown command\n"); exit(1);
