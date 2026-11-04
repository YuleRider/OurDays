<?php
require_once __DIR__ . '/LegacyPrivacy.php';
l_start(); header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new CoupleError('请使用 POST', 405);
    c_member(c_user()); c_verify_csrf($_POST['csrf'] ?? '');
    $file = $_FILES['file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 10 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) throw new CoupleError('请选择 10MB 以内的图片');
    $info = @getimagesize($file['tmp_name']);
    $extensions = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/bmp' => 'bmp');
    if (!$info || !isset($extensions[$info['mime']])) throw new CoupleError('请上传 JPEG、PNG、GIF、WebP 或 BMP 图片');
    $token = bin2hex(random_bytes(32)); $filename = $token . '.' . $extensions[$info['mime']]; $target = l_media_dir() . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) throw new RuntimeException('Upload failed');
    chmod($target, 0600);
    $url = '/legacy-image.php?asset=' . $token;
    try {
        c_db()->begin_transaction();
        c_q('INSERT INTO legacy_media(token,source_path,filename,mime,sha256) VALUES(?,?,?,?,?)', 'sssss', array($token, '', $filename, $info['mime'], hash_file('sha256', $target)));
        c_q('INSERT INTO picture(name,url,date) VALUES(?,?,?)', 'sss', array(basename($file['name']), $url, gmdate('Y-m-d H:i:s', time() + 28800)));
        c_db()->commit();
    } catch (Throwable $error) { c_db()->rollback(); unlink($target); throw $error; }
    echo json_encode(array('status' => true, 'data' => array('links' => array('url' => $url))));
} catch (Throwable $error) { http_response_code($error instanceof CoupleError ? $error->status : 503); echo json_encode(array('status' => false, 'message' => $error instanceof CoupleError ? $error->getMessage() : '上传失败，请稍后重试'), JSON_UNESCAPED_UNICODE); }
