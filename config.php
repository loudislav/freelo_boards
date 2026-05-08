<?php
// config.php

// Source - https://stackoverflow.com/a/75621780
// Posted by aetrnm
// Retrieved 2026-05-08, License - CC BY-SA 4.0

$env = parse_ini_file('.env');

return [
  'email' => $env["FREEL0_EMAIL"] ?: 'YOUR_EMAIL',
  'api_key' => $env["FREEL0_API_KEY"] ?: 'YOUR_API_KEY',
  'user_agent' => $env["FREEL0_UA"] ?: 'DeskaHanby/1.0 (YOUR_EMAIL)',
  'base_url' => 'https://api.freelo.io/v1',
];