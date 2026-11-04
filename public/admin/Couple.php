<?php
require_once __DIR__ . '/Runtime.php';

class CoupleError extends RuntimeException
{
    public $status;
    public function __construct($message, $status = 400) { parent::__construct($message); $this->status = $status; }
}

function c_init()
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params(array('lifetime' => 0, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax'));
        session_start();
    }
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

function c_db()
{
    static $db;
    if ($db) return $db;
    require defined('LIKEGIRL_DB_CONFIG') ? LIKEGIRL_DB_CONFIG : __DIR__ . '/Config_DB.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli($db_address, $db_username, $db_password, $db_name, isset($db_port) ? (int) $db_port : 3306);
    $db->set_charset('utf8mb4');
    return $db;
}

function c_q($sql, $types = '', $values = array())
{
    $stmt = c_db()->prepare($sql);
    if (!$stmt) throw new RuntimeException('Database prepare failed');
    if ($types !== '') $stmt->bind_param($types, ...$values);
    if (!$stmt->execute()) throw new RuntimeException('Database operation failed');
    return $stmt;
}

function c_one($sql, $types = '', $values = array()) { return c_q($sql, $types, $values)->get_result()->fetch_assoc(); }
function c_all($sql, $types = '', $values = array()) { return c_q($sql, $types, $values)->get_result()->fetch_all(MYSQLI_ASSOC); }
function c_now() { return gmdate('Y-m-d H:i:s'); }
function c_h($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function c_iso($value) { return str_replace(' ', 'T', $value) . 'Z'; }
function c_user()
{
    $name = isset($_SESSION['loginadmin']) ? $_SESSION['loginadmin'] : '';
    if (!is_string($name) || $name === '') return null;
    $user = c_one('SELECT id,user,username,userQQ,pw FROM login WHERE user=? LIMIT 1', 's', array($name));
    if (!$user || (isset($_SESSION['couple_signature']) && !hash_equals($_SESSION['couple_signature'], hash('sha256', $user['pw'])))) return null;
    $user['id'] = (int) $user['id'];
    unset($user['pw']);
    return $user;
}
function c_member($user) { if (!$user) throw new CoupleError('请先登录你们的账号', 401); }
function c_csrf()
{
    if (empty($_SESSION['couple_csrf'])) $_SESSION['couple_csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['couple_csrf'];
}
function c_verify_csrf($token)
{
    if (!is_string($token) || !hash_equals(c_csrf(), $token)) throw new CoupleError('页面已过期，请刷新后重试', 403);
}
function c_identity($user)
{
    return $user ? array('id' => (int) $user['id'], 'name' => $user['username'] ?: '我们',
        'avatar' => !empty($user['userQQ']) ? 'https://q1.qlogo.cn/g?b=qq&nk=' . rawurlencode((string) $user['userQQ']) . '&s=100' : '/Style/img/avatar-' . ((int) $user['id'] % 2 ? 'a' : 'b') . '.svg') : null;
}
function c_limit($bucket, $limit, $window)
{
    $path = likegirl_runtime_dir() . '/couple-rate.json';
    $lock = @fopen(likegirl_runtime_dir() . '/couple-rate.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new CoupleError('操作较多，请稍后重试', 429);
    try {
        $state = is_file($path) ? json_decode(file_get_contents($path), true) : array();
        if (!is_array($state)) throw new CoupleError('暂时无法验证请求，请稍后重试', 503);
        $now = time();
        foreach ($state as $id => $entry) if ($entry['until'] <= $now) unset($state[$id]);
        $id = hash('sha256', $bucket);
        $entry = isset($state[$id]) ? $state[$id] : array('count' => 0, 'until' => $now + $window);
        if ($entry['count'] >= $limit) throw new CoupleError('操作较多，请稍后再试', 429);
        $entry['count']++;
        $state[$id] = $entry;
        $json = json_encode($state);
        if (@file_put_contents($path . '.tmp', $json) !== strlen($json) || !@rename($path . '.tmp', $path)) throw new CoupleError('暂时无法验证请求，请稍后重试', 503);
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}
function c_login($name, $password)
{
    c_limit('login:' . ($_SERVER['REMOTE_ADDR'] ?? ''), 10, 900);
    if (!is_string($name) || !is_string($password) || strlen($name) > 100 || strlen($password) > 512) throw new CoupleError('账号或密码错误', 401);
    $user = c_one('SELECT id,user,pw FROM login WHERE user=? LIMIT 1', 's', array(trim($name)));
    $valid = $user && (preg_match('/^[a-f0-9]{32}$/i', $user['pw']) ? hash_equals(strtolower($user['pw']), md5($password)) : password_verify($password, $user['pw']));
    if (!$valid) throw new CoupleError('账号或密码错误', 401);
    session_regenerate_id(true);
    $_SESSION = array('loginadmin' => $user['user'], 'couple_signature' => hash('sha256', $user['pw']));
    c_csrf();
    return c_user();
}
function c_logout()
{
    $_SESSION = array();
    session_regenerate_id(true);
    c_csrf();
}
function c_text($value, $max)
{
    if (!is_string($value) || !preg_match('//u', $value)) throw new CoupleError('文字格式不正确');
    $value = trim(str_replace("\r\n", "\n", $value));
    preg_match_all('/./us', $value, $characters);
    if (count($characters[0]) > $max) throw new CoupleError('文字不能超过 ' . $max . ' 字');
    return $value;
}
function c_key($value)
{
    if (!is_string($value) || !preg_match('/^[a-f0-9]{32}$/', $value)) throw new CoupleError('提交标识不正确，请刷新后重试');
    return $value;
}
function c_visibility($value)
{
    if (!in_array($value, array('private', 'public'), true)) throw new CoupleError('请选择可见范围');
    return $value;
}
function c_id($value)
{
    if (!is_scalar($value) || !preg_match('/^[1-9][0-9]{0,14}$/', (string) $value)) throw new CoupleError('记录不存在', 404);
    return (int) $value;
}
function c_post($id, $user, $allowDeleted = false)
{
    $post = c_one('SELECT m.*,l.username,l.user,l.userQQ FROM couple_moments m JOIN login l ON l.id=m.author_id WHERE m.id=?', 'i', array(c_id($id)));
    if (!$post || (!$allowDeleted && $post['deleted_at']) || (!$user && $post['visibility'] !== 'public') || ($post['deleted_at'] && (!$user || (int) $post['author_id'] !== $user['id']))) throw new CoupleError('记录不存在或不可访问', 404);
    return $post;
}
function c_owner($post, $user)
{
    c_member($user);
    if ((int) $post['author_id'] !== $user['id']) throw new CoupleError('只能修改自己的记录', 403);
}
function c_present($post, $user, $withReplies = false)
{
    $id = (int) $post['id'];
    $images = c_all('SELECT id,width,height FROM couple_images WHERE moment_id=? AND removed_at IS NULL ORDER BY sort_order,id', 'i', array($id));
    foreach ($images as &$image) {
        $image['id'] = (int) $image['id'];
        $image['url'] = '/moment-image.php?id=' . $image['id'];
        if ($user) $image['original'] = '/moment-image.php?id=' . $image['id'] . '&original=1';
    }
    unset($image);
    $result = array('id' => $id, 'body' => $post['body'], 'author' => c_identity(array('id' => $post['author_id'], 'user' => $post['user'], 'username' => $post['username'], 'userQQ' => $post['userQQ'])),
        'createdAt' => c_iso($post['created_at']), 'occurredOn' => $post['occurred_on'] ?: c_local_day($post['created_at']), 'revision' => (int) $post['revision'], 'backfilled' => ($post['occurred_on'] ?: c_local_day($post['created_at'])) < c_local_day($post['created_at']), 'visibility' => $post['visibility'], 'images' => $images,
        'mine' => $user && $user['id'] === (int) $post['author_id'], 'deleted' => (bool) $post['deleted_at']);
    if ($user) {
        $result['likes'] = (int) c_one('SELECT COUNT(*) n FROM couple_likes WHERE moment_id=?', 'i', array($id))['n'];
        $result['liked'] = (bool) c_one('SELECT 1 FROM couple_likes WHERE moment_id=? AND user_id=?', 'ii', array($id, $user['id']));
        $result['replyCount'] = (int) c_one('SELECT COUNT(*) n FROM couple_replies WHERE moment_id=?', 'i', array($id))['n'];
        if ($withReplies) {
            $result['replies'] = c_all('SELECT r.id,r.body,r.created_at,l.username,l.user FROM couple_replies r JOIN login l ON l.id=r.user_id WHERE r.moment_id=? ORDER BY r.id', 'i', array($id));
        }
    }
    return $result;
}
function c_feed_filters($data, $user)
{
    $scope = $data['scope'] ?? 'all';
    if (!in_array($scope, array('all', 'mine', 'partner'), true)) throw new CoupleError('请选择有效的日常筛选');
    if ($scope !== 'all') c_member($user);
    if (!empty($data['day'])) { c_member($user); c_occurred_on($data['day']); }
    return array('q' => c_text($data['q'] ?? '', 100), 'scope' => $scope, 'photos' => ($data['photos'] ?? '') === '1', 'day' => $data['day'] ?? '');
}
function c_feed($user, $before = 0, $limit = 12, $trash = false, $filters = array())
{
    if ($trash) c_member($user);
    $published = $trash || ($filters['order'] ?? '') === 'published' || (is_numeric($before) && $before);
    $limit = max(1, min(50, (int) $limit));
    $where = $trash ? 'm.author_id=? AND m.deleted_at IS NOT NULL AND m.deleted_at>=?' : 'm.deleted_at IS NULL';
    $types = $trash ? 'is' : ''; $values = $trash ? array($user['id'], gmdate('Y-m-d H:i:s', time() - 7 * 86400)) : array();
    if (!$user) $where .= " AND m.visibility='public'";
    if (($filters['q'] ?? '') !== '') { $where .= ' AND LOCATE(?,m.body)>0'; $types .= 's'; $values[] = $filters['q']; }
    if (($filters['scope'] ?? 'all') !== 'all') { c_member($user); $where .= $filters['scope'] === 'mine' ? ' AND m.author_id=?' : ' AND m.author_id<>?'; $types .= 'i'; $values[] = $user['id']; }
    if (!empty($filters['photos'])) $where .= ' AND EXISTS(SELECT 1 FROM couple_images i WHERE i.moment_id=m.id AND i.removed_at IS NULL)';
    if (!empty($filters['day'])) { c_member($user); $where .= ' AND m.occurred_on=?'; $types .= 's'; $values[] = c_occurred_on($filters['day']); }
    if (!empty($filters['memoryDay'])) { c_member($user); $date = c_occurred_on($filters['memoryDay']); $where .= ' AND MONTH(m.occurred_on)=? AND DAY(m.occurred_on)=? AND m.occurred_on<?'; $types .= 'iis'; $values[] = (int) substr($date, 5, 2); $values[] = (int) substr($date, 8, 2); $values[] = substr($date, 0, 4) . '-01-01'; }
    if ($before) {
        if (is_numeric($before)) { $where .= ' AND m.id<?'; $types .= 'i'; $values[] = c_id($before); }
        else {
            $cursor = c_read_cursor($before, $published);
            if (!$published) { $where .= ' AND (m.occurred_on<? OR (m.occurred_on=? AND (m.created_at<? OR (m.created_at=? AND m.id<?))))'; $types .= 'ssssi'; $values = array_merge($values, array($cursor[1], $cursor[1], $cursor[2], $cursor[2], $cursor[3])); }
            else { $where .= ' AND (m.created_at<? OR (m.created_at=? AND m.id<?))'; $types .= 'ssi'; $values = array_merge($values, array($cursor[2], $cursor[2], $cursor[3])); }
        }
    }
    $order = $published ? 'm.created_at DESC,m.id DESC' : 'm.occurred_on DESC,m.created_at DESC,m.id DESC';
    $rows = c_all('SELECT m.*,l.username,l.user,l.userQQ FROM couple_moments m JOIN login l ON l.id=m.author_id WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . ($limit + 1), $types, $values);
    $more = count($rows) > $limit; $rows = array_slice($rows, 0, $limit);
    return array('items' => array_map(function ($row) use ($user) { return c_present($row, $user); }, $rows), 'next' => $more ? c_feed_cursor(end($rows), $published) : null);
}

function c_publish($data, $user)
{
    c_member($user); $body = c_text($data['body'] ?? '', 2000); $key = c_key($data['requestKey'] ?? '');
    $visibility = c_visibility($data['visibility'] ?? 'private'); $date = c_occurred_on($data['occurredOn'] ?? c_today()); $ids = c_image_ids($data['images'] ?? array());
    if ($body === '' && !$ids) throw new CoupleError('请写点文字或上传照片');
    $db = c_db(); $db->begin_transaction();
    try {
        $old = c_one('SELECT * FROM couple_moments WHERE author_id=? AND request_key=? FOR UPDATE', 'is', array($user['id'], $key));
        if ($old) {
            if ($old['deleted_at'] || !c_payload_matches($old, $body, $visibility, $date, $ids)) throw new CoupleError('这次提交已保存，请先查看记录；当前输入已保留', 409);
            $db->commit(); return (int) $old['id'];
        }
        if (isset($data['draftRevision'])) c_draft_publish_lock($data, $user, 0);
        c_validate_images($ids, $user);
        $now = c_now();
        c_q('INSERT INTO couple_moments(author_id,body,visibility,request_key,created_at,updated_at,occurred_on) VALUES(?,?,?,?,?,?,?)', 'issssss', array($user['id'], $body, $visibility, $key, $now, $now, $date));
        $id = (int) $db->insert_id;
        foreach ($ids as $order => $image) c_q('UPDATE couple_images SET moment_id=?,sort_order=? WHERE id=?', 'iii', array($id, $order, $image));
        if (isset($data['draftRevision'])) c_q('DELETE FROM couple_drafts WHERE user_id=? AND context_id=0', 'i', array($user['id']));
        $db->commit(); return $id;
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}

function c_edit($data, $user)
{
    c_member($user); $id = c_id($data['id'] ?? 0); $key = c_key($data['requestKey'] ?? '');
    $base = $data['baseRevision'] ?? null;
    if (!is_int($base) || $base < 1 || !array_key_exists('images', $data) || !isset($data['occurredOn'])) throw new CoupleError('编辑页面已更新，请先复制文字，再刷新页面', 409);
    $body = c_text($data['body'] ?? '', 2000); $visibility = c_visibility($data['visibility'] ?? 'private');
    $date = c_occurred_on($data['occurredOn']); $ids = c_image_ids($data['images']);
    if ($body === '' && !$ids) throw new CoupleError('请保留文字或照片');
    $db = c_db(); $db->begin_transaction();
    try {
        $post = c_one('SELECT * FROM couple_moments WHERE id=? AND deleted_at IS NULL FOR UPDATE', 'i', array($id));
        if (!$post) throw new CoupleError('记录不存在或已被删除', 404);
        c_owner($post, $user);
        if ($post['last_edit_key'] === $key) {
            if (!c_payload_matches($post, $body, $visibility, $date, $ids)) throw new CoupleError('这次保存已完成，请重新打开记录后编辑', 409);
            $db->commit(); return $id;
        }
        if ((int) $post['revision'] !== $base) throw new CoupleError('另一设备已修改这条记录。当前输入已保留，请重新打开最新记录后合并', 409);
        if (isset($data['draftRevision'])) c_draft_publish_lock($data, $user, $id);
        c_validate_images($ids, $user, $id);
        foreach (c_all('SELECT id FROM couple_images WHERE moment_id=? AND removed_at IS NULL FOR UPDATE', 'i', array($id)) as $image) if (!in_array((int) $image['id'], $ids, true)) c_q('UPDATE couple_images SET removed_at=? WHERE id=?', 'si', array(c_now(), $image['id']));
        foreach ($ids as $order => $image) c_q('UPDATE couple_images SET moment_id=?,sort_order=? WHERE id=?', 'iii', array($id, $order, $image));
        c_q('UPDATE couple_moments SET body=?,visibility=?,occurred_on=?,updated_at=?,revision=revision+1,last_edit_key=? WHERE id=?', 'sssssi', array($body, $visibility, $date, c_now(), $key, $id));
        c_q('DELETE FROM couple_drafts WHERE user_id=? AND context_id=?', 'ii', array($user['id'], $id));
        $db->commit(); return $id;
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}


function c_delete($id, $user)
{
    $post = c_post($id, $user, true);
    c_owner($post, $user);
    if ($post['deleted_at']) return;
    c_db()->begin_transaction();
    try {
        c_q('UPDATE couple_moments SET deleted_at=? WHERE id=? AND author_id=?', 'sii', array(c_now(), $post['id'], $user['id']));
        c_q('DELETE FROM couple_notifications WHERE moment_id=?', 'i', array($post['id']));
        c_db()->commit();
    } catch (Throwable $error) { c_db()->rollback(); throw $error; }
}
function c_restore($id, $user)
{
    $post = c_post($id, $user, true);
    c_owner($post, $user);
    if (!$post['deleted_at']) return;
    if (strtotime($post['deleted_at'] . ' UTC') < time() - 7 * 86400) throw new CoupleError('该记录已超过七天恢复期限', 410);
    c_q('UPDATE couple_moments SET deleted_at=NULL WHERE id=? AND author_id=?', 'ii', array($post['id'], $user['id']));
}
function c_notify($user, $postId, $kind, $source)
{
    foreach (c_all('SELECT id FROM login WHERE id<>?', 'i', array($user['id'])) as $other) {
        c_q('INSERT IGNORE INTO couple_notifications(recipient_id,actor_id,moment_id,kind,source_id,created_at) VALUES(?,?,?,?,?,?)', 'iiisis', array($other['id'], $user['id'], $postId, $kind, $source, c_now()));
    }
}
function c_like($id, $liked, $user)
{
    c_member($user);
    $db = c_db();
    $db->begin_transaction();
    try {
        c_q('SELECT id FROM couple_moments WHERE id=? FOR UPDATE', 'i', array(c_id($id)));
        $post = c_post($id, $user);
        if ((int) $post['author_id'] === $user['id']) throw new CoupleError('可以给对方的记录点喜欢');
        if (!is_bool($liked)) throw new CoupleError('喜欢状态不正确');
        if ($liked) {
            $stmt = c_q('INSERT IGNORE INTO couple_likes(moment_id,user_id,created_at) VALUES(?,?,?)', 'iis', array($id, $user['id'], c_now()));
            if ($stmt->affected_rows) c_notify($user, $id, 'like', $id);
        } else {
            c_q('DELETE FROM couple_likes WHERE moment_id=? AND user_id=?', 'ii', array($id, $user['id']));
            c_q("DELETE FROM couple_notifications WHERE actor_id=? AND moment_id=? AND kind='like'", 'ii', array($user['id'], $id));
        }
        $db->commit();
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}
function c_reply($data, $user)
{
    c_member($user);
    $body = c_text($data['body'] ?? '', 500);
    if ($body === '') throw new CoupleError('回应不能为空');
    $key = c_key($data['requestKey'] ?? '');
    $id = c_id($data['id'] ?? 0);
    $db = c_db();
    $db->begin_transaction();
    try {
        c_q('SELECT id FROM couple_moments WHERE id=? FOR UPDATE', 'i', array($id));
        c_post($id, $user);
        $old = c_one('SELECT id,moment_id,body FROM couple_replies WHERE user_id=? AND request_key=?', 'is', array($user['id'], $key));
        if (!$old) {
            c_q('INSERT INTO couple_replies(moment_id,user_id,body,request_key,created_at) VALUES(?,?,?,?,?)', 'iisss', array($id, $user['id'], $body, $key, c_now()));
            c_notify($user, $id, 'reply', $db->insert_id);
        } elseif ((int) $old['moment_id'] !== $id || $old['body'] !== $body) throw new CoupleError('这次回应已保存过。请先复制当前文字，再查看已保存的回应。', 409);
        $db->commit();
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}
function c_unread($user)
{
    if (!$user) return 0;
    return (int) c_one('SELECT COUNT(*) n FROM couple_notifications n JOIN couple_moments m ON m.id=n.moment_id WHERE n.recipient_id=? AND n.read_at IS NULL AND m.deleted_at IS NULL', 'i', array($user['id']))['n'];
}
function c_notifications($user, $before = 0)
{
    c_member($user);
    $sql = 'SELECT n.id,n.moment_id,n.kind,n.created_at,n.read_at,l.username,l.user FROM couple_notifications n JOIN login l ON l.id=n.actor_id JOIN couple_moments m ON m.id=n.moment_id WHERE n.recipient_id=? AND m.deleted_at IS NULL';
    $values = array($user['id']); $types = 'i';
    if ($before) { $sql .= ' AND n.id<?'; $values[] = c_id($before); $types .= 'i'; }
    $items = c_all($sql . ' ORDER BY n.id DESC LIMIT 31', $types, $values);
    $more = count($items) > 30; $items = array_slice($items, 0, 30);
    return array('items' => $items, 'next' => $more ? (int) end($items)['id'] : null);
}
function c_image_record($id, $user, $original)
{
    $image = c_one('SELECT * FROM couple_images WHERE id=?', 'i', array(c_id($id)));
    if (!$image) throw new CoupleError('照片不存在', 404);
    if ($image['removed_at'] !== null) {
        if (!$user || $user['id'] !== (int) $image['owner_id'] || strtotime($image['removed_at'] . ' UTC') <= time() - 7 * 86400) throw new CoupleError('照片不存在', 404);
        c_post($image['moment_id'], $user);
    } elseif ($image['moment_id'] !== null) c_post($image['moment_id'], $user);
    elseif (!$user || $user['id'] !== (int) $image['owner_id']) throw new CoupleError('照片不存在', 404);
    if ($original && !$user) throw new CoupleError('照片不存在', 404);
    return $image;
}
function c_media_dir() { return likegirl_runtime_dir() . '/couple-media'; }
function c_calendar_date($value)
{
    if (!is_string($value) || !preg_match('/^(19[0-9]{2}|20[0-9]{2}|2100)-([0-9]{2})-([0-9]{2})$/', $value, $parts)
        || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) throw new CoupleError('请选择有效的公历日期（1900—2100 年）');
    return new DateTimeImmutable($value, new DateTimeZone('Asia/Shanghai'));
}
function c_date_countdown($date, $kind, $today = null)
{
    $base = c_calendar_date($date);
    $zone = new DateTimeZone('Asia/Shanghai');
    $today = $today ? $today->setTimezone($zone)->setTime(0, 0) : new DateTimeImmutable('today', $zone);
    $year = max((int) $today->format('Y'), $kind === 'anniversary' ? (int) $base->format('Y') : 0);
    do {
        $month = (int) $base->format('m'); $day = (int) $base->format('d');
        // February 29 is observed on February 28 in non-leap years.
        if (!checkdate($month, $day, $year)) $day = 28;
        $next = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), $zone);
        $year++;
    } while ($next < $today);
    return array('nextDate' => $next->format('Y-m-d'), 'days' => (int) $today->diff($next)->format('%a'),
        'years' => $kind === 'anniversary' ? (int) $next->format('Y') - (int) $base->format('Y') : null);
}
function c_dates($user)
{
    $items = c_all('SELECT id,title,kind,event_date,visibility FROM couple_dates' . ($user ? '' : " WHERE visibility='public'"));
    $start = c_one('SELECT startTime FROM text LIMIT 1')['startTime'] ?? '';
    if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})(?:[ T]|$)/', $start, $parts)) {
        $date = sprintf('%04d-%02d-%02d', $parts[1], $parts[2], $parts[3]);
        try { c_calendar_date($date); $items[] = array('id' => 'together', 'title' => '相恋纪念日', 'kind' => 'anniversary', 'event_date' => $date, 'visibility' => 'public'); }
        catch (CoupleError $error) { /* Invalid legacy dates do not prevent the homepage opening. */ }
    }
    foreach ($items as &$item) $item += c_date_countdown($item['event_date'], $item['kind']);
    unset($item);
    usort($items, function ($a, $b) { return ($a['days'] <=> $b['days']) ?: strcmp((string) $a['id'], (string) $b['id']); });
    return $items;
}
function c_save_date($data, $user)
{
    c_member($user);
    $title = c_text($data['title'] ?? '', 40);
    if ($title === '') throw new CoupleError('给这个日子起一个名字吧');
    $kind = $data['kind'] ?? '';
    if (!in_array($kind, array('anniversary', 'birthday'), true)) throw new CoupleError('请选择纪念日或生日');
    if ($kind === 'birthday') {
        foreach (array('month', 'day') as $part) if (!is_scalar($data[$part] ?? null) || !preg_match('/^[0-9]{1,2}$/', (string) $data[$part])) throw new CoupleError('请填写生日的月份和日期');
        if (!checkdate((int) $data['month'], (int) $data['day'], 2000)) throw new CoupleError('这个月份没有这一天，请检查生日的月和日');
        // Store only the birthday month/day; 2000 is a leap-year placeholder, never an age.
        $date = sprintf('2000-%02d-%02d', $data['month'], $data['day']);
    } else $date = $data['date'] ?? '';
    c_calendar_date($date);
    $visibility = c_visibility($data['visibility'] ?? 'private');
    $id = empty($data['id']) ? 0 : c_id($data['id']);
    if ($id) {
        if (!c_one('SELECT id FROM couple_dates WHERE id=?', 'i', array($id))) throw new CoupleError('这个日期不存在', 404);
        c_q('UPDATE couple_dates SET title=?,kind=?,event_date=?,visibility=?,updated_at=? WHERE id=?', 'sssssi', array($title, $kind, $date, $visibility, c_now(), $id));
        return $id;
    }
    $key = c_key($data['requestKey'] ?? '');
    $db = c_db(); $db->begin_transaction();
    try {
        c_q('SELECT id FROM login ORDER BY id LIMIT 1 FOR UPDATE');
        $old = c_one('SELECT * FROM couple_dates WHERE creator_id=? AND request_key=?', 'is', array($user['id'], $key));
        if ($old) {
            if ($old['title'] !== $title || $old['kind'] !== $kind || $old['event_date'] !== $date || $old['visibility'] !== $visibility) throw new CoupleError('这次日期已保存过，请先查看已保存的内容', 409);
            $id = (int) $old['id'];
        } else {
            if ((int) c_one('SELECT COUNT(*) n FROM couple_dates')['n'] >= 20) throw new CoupleError('最多保存 20 个日期，请先整理已有日期');
            c_q('INSERT INTO couple_dates(title,kind,event_date,visibility,creator_id,request_key,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)', 'ssssisss', array($title, $kind, $date, $visibility, $user['id'], $key, c_now(), c_now()));
            $id = (int) $db->insert_id;
        }
        $db->commit(); return $id;
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}
function c_upload($file, $user, $requestKey)
{
    c_member($user);
    $requestKey = c_key($requestKey);
    $existing = c_one('SELECT id FROM couple_images WHERE owner_id=? AND request_key=?', 'is', array($user['id'], $requestKey));
    if ($existing) return array('id' => (int) $existing['id'], 'url' => '/moment-image.php?id=' . $existing['id']);
    if (!is_array($file) || ($file['error'] ?? -1) !== UPLOAD_ERR_OK) throw new CoupleError('照片上传失败，请检查网络或照片大小');
    if ($file['size'] <= 0 || $file['size'] > 10 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) throw new CoupleError('单张原图不能超过 10MB');
    $free = @disk_free_space(c_media_dir());
    if ($free === false || $free < max(64 * 1024 * 1024, $file['size'] * 3)) throw new CoupleError('照片存储空间暂时不足，请联系维护者', 503);
    if (!preg_match('/\.(jpe?g|png|webp)$/i', $file['name'])) throw new CoupleError('支持 JPEG、PNG、WebP；HEIC 请先转换格式');
    $info = @getimagesize($file['tmp_name']);
    $formats = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
    if (!$info || !isset($formats[$info['mime']]) || $info[0] * $info[1] > 20000000) throw new CoupleError('图片格式或像素不支持，请缩小后重试');
    if (!function_exists('imagecreatefromstring')) throw new CoupleError('图片处理服务暂不可用', 503);
    $source = @imagecreatefromstring(file_get_contents($file['tmp_name']));
    if (!$source) throw new CoupleError('无法读取这张照片，请转换格式后重试');
    if ($info['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $orientation = isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;
        if (in_array($orientation, array(2, 4, 5, 7), true)) imageflip($source, IMG_FLIP_HORIZONTAL);
        $rotation = array(3 => 180, 4 => 180, 5 => -90, 6 => -90, 7 => 90, 8 => 90);
        if (isset($rotation[$orientation])) { $rotated = imagerotate($source, $rotation[$orientation], 0); imagedestroy($source); $source = $rotated; }
    }
    $ratio = min(1, 1600 / max(imagesx($source), imagesy($source)));
    $width = max(1, (int) round(imagesx($source) * $ratio)); $height = max(1, (int) round(imagesy($source) * $ratio));
    $display = imagecreatetruecolor($width, $height);
    imagefill($display, 0, 0, imagecolorallocate($display, 255, 255, 255));
    imagecopyresampled($display, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
    $token = bin2hex(random_bytes(20));
    $original = $token . '.' . $formats[$info['mime']]; $preview = $token . '.jpg';
    $originalPath = c_media_dir() . '/originals/' . $original; $displayPath = c_media_dir() . '/displays/' . $preview;
    try {
        if (!imagejpeg($display, $displayPath, 85) || !move_uploaded_file($file['tmp_name'], $originalPath)) throw new CoupleError('照片保存失败，请稍后重试', 503);
        chmod($displayPath, 0600); chmod($originalPath, 0600);
        $name = preg_replace('/[\r\n\x00-\x1f]/u', '', basename($file['name']));
        while (strlen($name) > 180) $name = preg_replace('/.$/us', '', $name);
        c_q('INSERT INTO couple_images(owner_id,request_key,original_file,display_file,original_mime,original_name,width,height,created_at) VALUES(?,?,?,?,?,?,?,?,?)', 'isssssiis', array($user['id'], $requestKey, $original, $preview, $info['mime'], $name, $width, $height, c_now()));
        return array('id' => (int) c_db()->insert_id, 'url' => '/moment-image.php?id=' . c_db()->insert_id);
    } catch (Throwable $error) {
        if (is_file($originalPath)) unlink($originalPath);
        if (is_file($displayPath)) unlink($displayPath);
        throw $error;
    } finally { imagedestroy($source); imagedestroy($display); }
}

require_once __DIR__ . '/CoupleMemory.php';
