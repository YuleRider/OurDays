<?php
require_once __DIR__ . '/LegacyPrivacy.php'; l_start();
header('Content-Type: text/plain; charset=utf-8');
try { l_save('album', false); }
catch (CoupleError $error) { http_response_code($error->status); echo '0'; }
catch (Throwable $error) { http_response_code(503); echo '0'; }
