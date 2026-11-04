<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/Runtime.php';
require_once __DIR__ . '/QQService.php';

$qq = isset($_GET['qq']) && is_string($_GET['qq']) ? trim($_GET['qq']) : '';
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$key = (string) (ourdays_config()['qq_api_key'] ?? '');
$result = likegirl_qq_lookup($qq, $ip, $key, likegirl_runtime_dir());
echo json_encode($result, JSON_UNESCAPED_UNICODE);
