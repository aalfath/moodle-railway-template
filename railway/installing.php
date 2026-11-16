<?php
// Served only while the first install or an upgrade runs, so Railway's health check passes
// and nobody can reach Moodle's web installer in the meantime.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path === '/healthz') { header('Content-Type: text/plain'); echo "setting up\n"; return true; }
http_response_code(503);
header('Retry-After: 30');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta http-equiv="refresh" content="30"><title>Moodle is starting</title>'
   . '<p style="font-family:sans-serif">Moodle is being installed or upgraded. This page reloads every 30 seconds.</p>';
return true;
