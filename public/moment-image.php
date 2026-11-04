<?php
require_once __DIR__ . '/admin/Couple.php';
c_init();
try {
    $user = c_user();
    session_write_close();
    $original = ($_GET['original'] ?? '') === '1';
    $image = c_image_record($_GET['id'] ?? 0, $user, $original);
    $filename = $original ? $image['original_file'] : $image['display_file'];
    if (!preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/', $filename)) throw new CoupleError('照片不存在', 404);
    $path = c_media_dir() . ($original ? '/originals/' : '/displays/') . $filename;
    if (!is_file($path)) throw new CoupleError('照片不存在', 404);
    header('Content-Type: ' . ($original ? $image['original_mime'] : 'image/jpeg'));
    header('Content-Length: ' . filesize($path));
    header('Cross-Origin-Resource-Policy: same-origin');
    if ($original) header("Content-Disposition: attachment; filename=photo; filename*=UTF-8''" . rawurlencode($image['original_name']));
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') readfile($path);
} catch (Throwable $error) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo '照片不存在或不可访问';
}
