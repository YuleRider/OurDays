<?php
require_once __DIR__ . '/admin/LegacyPrivacy.php';
l_start();
try {
    $token = $_GET['asset'] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) throw new CoupleError('图片不存在', 404);
    $asset = c_one('SELECT * FROM legacy_media WHERE token=?', 's', array($token));
    if (!$asset || (!c_user() && !isset(l_public_assets()[$token]))) throw new CoupleError('图片不存在', 404);
    if (!preg_match('/^[a-f0-9]{64}\.(jpg|png|gif|webp|bmp)$/', $asset['filename'])) throw new CoupleError('图片不存在', 404);
    $file = l_media_dir() . '/' . $asset['filename'];
    if (!is_file($file) || is_link($file)) throw new CoupleError('图片不存在', 404);
    session_write_close();
    $mime = $asset['mime'];
    if (($_GET['preview'] ?? '') === '1') {
        require_once __DIR__ . '/admin/LegacyPreview.php';
        list($file, $mime) = l_preview_file($file, $asset);
    }
    header('Content-Type: ' . $mime); header('Content-Length: ' . filesize($file));
    header('Cross-Origin-Resource-Policy: same-origin');
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') readfile($file);
} catch (Throwable $error) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo '图片不存在或不可访问'; }
