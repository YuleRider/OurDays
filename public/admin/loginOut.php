<?php
require_once __DIR__ . '/Couple.php';
c_init();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { c_verify_csrf($_POST['csrf'] ?? ''); c_logout(); header('Location: /admin/login.php'); }
    catch (CoupleError $error) { http_response_code($error->status); echo c_h($error->getMessage()); }
} else {
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><form method="post"><input type="hidden" name="csrf" value="' . c_h(c_csrf()) . '"><button type="submit">确认退出当前账号</button></form>';
}
