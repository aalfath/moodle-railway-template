<?php
// Prints "installed" or "empty". Exit code 2 when the database can't be reached.
$url = getenv('MOODLE_DATABASE_URL') ?: getenv('DATABASE_URL');
$d = $url ? parse_url($url) : [];
$conn = @pg_connect(sprintf("host='%s' port='%s' dbname='%s' user='%s' password='%s' connect_timeout=5",
    addslashes($d['host'] ?? ''), $d['port'] ?? 5432, addslashes(ltrim($d['path'] ?? '', '/')),
    addslashes(urldecode($d['user'] ?? '')), addslashes(urldecode($d['pass'] ?? ''))));
if (!$conn) { fwrite(STDERR, "database unreachable\n"); exit(2); }
$r = pg_query($conn, "SELECT to_regclass('public.mdl_config') IS NOT NULL AS t");
$row = pg_fetch_assoc($r);
echo ($row['t'] === 't') ? "installed\n" : "empty\n";
