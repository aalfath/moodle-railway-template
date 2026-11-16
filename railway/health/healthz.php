<?php
// Railway health check: 200 when PHP answers and Moodle's database is reachable.
header('Content-Type: text/plain');
header('Cache-Control: no-store');
$url = getenv('MOODLE_DATABASE_URL') ?: getenv('DATABASE_URL');
$d = $url ? parse_url($url) : [];
$conn = @pg_connect(sprintf("host='%s' port='%s' dbname='%s' user='%s' password='%s' connect_timeout=5",
    addslashes($d['host'] ?? ''), $d['port'] ?? 5432, addslashes(ltrim($d['path'] ?? '', '/')),
    addslashes(urldecode($d['user'] ?? '')), addslashes(urldecode($d['pass'] ?? ''))));
if (!$conn) {
    http_response_code(503);
    echo "database unreachable\n";
    exit;
}
pg_close($conn);
echo "ok\n";
