<?php
require_once __DIR__ . '/LegacyPrivacy.php'; l_start();
require_once __DIR__ . '/Function.php';
header('Content-Type: text/plain; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new CoupleError('POST required', 405);
    if (!empty($_COOKIE['KiCookie'])) { echo '8'; exit; }
    $name = c_text($_POST['name'] ?? '', 100); $text = c_text($_POST['text'] ?? '', 5000); $qq = c_text($_POST['qq'] ?? '', 20);
    if (!preg_match('/^[1-9][0-9]{5,9}$/', $qq)) { echo '3'; exit; }
    if ($name === '' || $text === '') { echo '5'; exit; }
    if (!filter_var($Filter_IP, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) { echo '4'; exit; }
    $user = c_user(); $visibility = 'public';
    if ($user) { c_verify_csrf($_POST['csrf'] ?? ''); $visibility = c_visibility($_POST['visibility'] ?? 'private'); }
    $city = get_ip_city_New($Filter_IP);
    $db = c_db(); $db->begin_transaction();
    try {
        c_q('INSERT INTO leaving(name,QQ,text,time,ip,city) VALUES(?,?,?,?,?,?)', 'ssssss', array(replaceSpecialChar($name), $qq, replaceSpecialChar($text), (string) time(), $Filter_IP, $city));
        l_set_visibility('message', $db->insert_id, $visibility);
        $db->commit();
    } catch (Throwable $error) { $db->rollback(); throw $error; }
    setcookie('KiCookie', $Filter_IP, time() + 86400); echo '1';
} catch (CoupleError $error) { http_response_code($error->status); echo '0'; }
catch (Throwable $error) { http_response_code(503); echo '0'; }
