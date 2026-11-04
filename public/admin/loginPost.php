<?php
require_once __DIR__ . '/Couple.php';
c_init();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new CoupleError('请从登录页面进入', 405);
    c_verify_csrf($_POST['csrf'] ?? '');
    c_login($_POST['adminName'] ?? '', $_POST['pw'] ?? '');
    header('Location: /admin/index.php');
} catch (CoupleError $error) {
    http_response_code($error->status);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><p>' . c_h($error->getMessage()) . '</p><a href="/admin/login.php">返回登录</a>';
} catch (Throwable $error) {
    http_response_code(503);
    echo '<meta charset="utf-8"><p>暂时无法登录，请稍后重试。</p>';
}
