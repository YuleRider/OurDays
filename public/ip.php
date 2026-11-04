<?php

include_once __DIR__ . '/admin/Function.php';
require_once __DIR__ . '/admin/Runtime.php';

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
if (filter_var($ip, FILTER_VALIDATE_IP)) {
    $time = gmdate('Y-m-d/H:i:s', time() + 8 * 3600);
    $gsd = get_ip_city_New($ip);
    $record = json_encode(array('ip' => $ip, 'city' => $gsd, 'time' => $time), JSON_UNESCAPED_UNICODE);
    if (@file_put_contents(likegirl_runtime_dir() . '/visitors.log', $record . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
        error_log('LikeGirl visitor log is not writable');
    }
}
