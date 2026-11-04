<?php
require_once __DIR__ . '/LegacyPrivacy.php';
l_start(); header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new CoupleError('请使用 POST', 405);
    l_visibility_write($_POST);
    echo json_encode(array('ok' => true));
} catch (CoupleError $error) { http_response_code($error->status); echo json_encode(array('ok' => false, 'message' => $error->getMessage()), JSON_UNESCAPED_UNICODE); }
catch (Throwable $error) { http_response_code(503); echo json_encode(array('ok' => false, 'message' => '保存失败，请稍后重试'), JSON_UNESCAPED_UNICODE); }
