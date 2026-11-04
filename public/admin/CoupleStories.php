<?php
require_once __DIR__ . '/CoupleExtras.php';

function c_stories_migrate()
{
    c_q("CREATE TABLE IF NOT EXISTS couple_stories (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,title VARCHAR(40) NOT NULL,intro VARCHAR(200) NOT NULL DEFAULT '',revision INT UNSIGNED NOT NULL DEFAULT 1,creator_id INT NOT NULL,request_key CHAR(32) NOT NULL,created_at DATETIME NOT NULL,UNIQUE KEY create_request(creator_id,request_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    c_q('CREATE TABLE IF NOT EXISTS couple_story_moments (story_id BIGINT UNSIGNED NOT NULL,moment_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(story_id,moment_id),KEY by_moment(moment_id,story_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
function c_stories_member($user)
{
    c_member($user);
    if (is_file(likegirl_runtime_dir() . '/stories-disabled')) throw new CoupleError('故事与月份回顾正在维护，请稍后再试', 503);
}
function c_story($id, $user, $lock = false)
{
    c_stories_member($user);
    $row = c_one('SELECT * FROM couple_stories WHERE id=?' . ($lock ? ' FOR UPDATE' : ''), 'i', array(c_id($id)));
    if (!$row) throw new CoupleError('这个故事已被删除，请刷新后再看', 404);
    return $row;
}
function c_story_summary($row)
{
    $id = (int) $row['id'];
    $count = c_one('SELECT COUNT(*) total FROM couple_story_moments s JOIN couple_moments m ON m.id=s.moment_id WHERE s.story_id=? AND m.deleted_at IS NULL', 'i', array($id));
    $first = c_one('SELECT m.id FROM couple_story_moments s JOIN couple_moments m ON m.id=s.moment_id WHERE s.story_id=? AND m.deleted_at IS NULL ORDER BY m.occurred_on,m.created_at,m.id LIMIT 1', 'i', array($id));
    $image = $first ? c_one('SELECT id FROM couple_images WHERE moment_id=? AND removed_at IS NULL ORDER BY sort_order,id LIMIT 1', 'i', array($first['id'])) : null;
    return array('id' => $id, 'title' => $row['title'], 'intro' => $row['intro'], 'revision' => (int) $row['revision'], 'count' => (int) $count['total'], 'cover' => $image ? '/moment-image.php?id=' . $image['id'] : null);
}
function c_stories($user, $before = 0, $moment = 0)
{
    c_stories_member($user);
    if ($moment) c_post($moment, $user);
    $rows = c_all('SELECT * FROM couple_stories' . ($before ? ' WHERE id<?' : '') . ' ORDER BY id DESC LIMIT 25', $before ? 'i' : '', $before ? array(c_id($before)) : array());
    $more = count($rows) > 24; $rows = array_slice($rows, 0, 24);
    $items = array();
    foreach ($rows as $row) {
        $item = c_story_summary($row);
        if ($moment) $item['joined'] = (bool) c_one('SELECT 1 FROM couple_story_moments WHERE story_id=? AND moment_id=?', 'ii', array($row['id'], $moment));
        $items[] = $item;
    }
    return array('items' => $items, 'next' => $more ? (int) end($rows)['id'] : null);
}
function c_save_story($data, $user)
{
    c_stories_member($user);
    $title = c_text($data['title'] ?? '', 40); $intro = c_text($data['intro'] ?? '', 200);
    if ($title === '') throw new CoupleError('给这个故事起个名字吧');
    $db = c_db(); $db->begin_transaction();
    try {
        if (empty($data['id'])) {
            $key = c_key($data['requestKey'] ?? '');
            $row = c_one('SELECT * FROM couple_stories WHERE creator_id=? AND request_key=? FOR UPDATE', 'is', array($user['id'], $key));
            if ($row && ($row['title'] !== $title || $row['intro'] !== $intro)) throw new CoupleError('这次创建已保存，请重新打开故事', 409);
            if (!$row) {
                c_q('INSERT INTO couple_stories(title,intro,creator_id,request_key,created_at) VALUES(?,?,?,?,?)', 'ssiss', array($title, $intro, $user['id'], $key, c_now()));
                $id = (int) $db->insert_id;
            } else $id = (int) $row['id'];
        } else {
            $row = c_story($data['id'], $user, true); $id = (int) $row['id'];
            if (!is_int($data['revision'] ?? null) || (int) $row['revision'] !== $data['revision']) throw new CoupleError('对方刚更新了这个故事，输入已保留，请刷新后再修改', 409);
            c_q('UPDATE couple_stories SET title=?,intro=?,revision=revision+1 WHERE id=?', 'ssi', array($title, $intro, $id));
        }
        $db->commit(); return $id;
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}
function c_delete_story($data, $user)
{
    c_stories_member($user); $id = c_id($data['id'] ?? 0); $db = c_db(); $db->begin_transaction();
    try {
        $row = c_one('SELECT revision FROM couple_stories WHERE id=? FOR UPDATE', 'i', array($id));
        if ($row && (!is_int($data['revision'] ?? null) || (int) $row['revision'] !== $data['revision'])) throw new CoupleError('故事已更新，请刷新后重新确认删除', 409);
        c_q('DELETE FROM couple_story_moments WHERE story_id=?', 'i', array($id));
        c_q('DELETE FROM couple_stories WHERE id=?', 'i', array($id));
        $db->commit();
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}
function c_story_membership($data, $user)
{
    c_stories_member($user);
    if (!is_bool($data['joined'] ?? null)) throw new CoupleError('请选择加入或移出');
    $db = c_db(); $db->begin_transaction();
    try {
        $story = c_story($data['id'] ?? 0, $user, true); $moment = c_id($data['momentId'] ?? 0);
        $post = c_one('SELECT id FROM couple_moments WHERE id=? AND deleted_at IS NULL FOR UPDATE', 'i', array($moment));
        if (!$post) throw new CoupleError('这条日常已被删除，请刷新后再整理', 404);
        if ($data['joined']) c_q('INSERT INTO couple_story_moments(story_id,moment_id) VALUES(?,?) ON DUPLICATE KEY UPDATE moment_id=VALUES(moment_id)', 'ii', array($story['id'], $moment));
        else c_q('DELETE FROM couple_story_moments WHERE story_id=? AND moment_id=?', 'ii', array($story['id'], $moment));
        $db->commit();
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}
function c_recap_month($month)
{
    $month = $month === '' ? substr(c_today(), 0, 7) : $month;
    if (!is_string($month) || !preg_match('/^\d{4}-\d{2}$/', $month)) throw new CoupleError('请选择有效月份');
    c_occurred_on($month . '-01');
    $start = new DateTimeImmutable($month . '-01', new DateTimeZone('Asia/Shanghai'));
    return array($month, $month . '-01', $start->modify('+1 month')->format('Y-m-d'));
}
function c_story_feed($user, $kind, $value, $cursor = '')
{
    c_stories_member($user);
    if ($kind === 'story') {
        $story = c_story($value, $user); $value = (string) $story['id'];
        $where = 'm.deleted_at IS NULL AND EXISTS(SELECT 1 FROM couple_story_moments s WHERE s.story_id=? AND s.moment_id=m.id)'; $types = 'i'; $args = array($story['id']);
    } else {
        list($value, $start, $end) = c_recap_month($value);
        $where = 'm.deleted_at IS NULL AND m.occurred_on>=? AND m.occurred_on<?'; $types = 'ss'; $args = array($start, $end);
    }
    if ($cursor !== '') {
        if (!is_string($cursor) || strlen($cursor) > 300 || !preg_match('/^[A-Za-z0-9_-]+$/', $cursor)) throw new CoupleError('分页位置无效，请刷新');
        $parts = json_decode(base64_decode(strtr($cursor, '-_', '+/'), true), true);
        if (!is_array($parts) || count($parts) !== 5 || $parts[0] !== $kind || $parts[1] !== $value || !is_string($parts[2]) || !is_string($parts[3]) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $parts[3])) throw new CoupleError('分页位置已变化，请刷新');
        c_occurred_on($parts[2]); c_id($parts[4]);
        $where .= ' AND (m.occurred_on>? OR (m.occurred_on=? AND (m.created_at>? OR (m.created_at=? AND m.id>?))))';
        $types .= 'ssssi'; $args = array_merge($args, array($parts[2], $parts[2], $parts[3], $parts[3], $parts[4]));
    }
    $rows = c_all('SELECT m.*,l.username,l.user,l.userQQ FROM couple_moments m JOIN login l ON l.id=m.author_id WHERE ' . $where . ' ORDER BY m.occurred_on,m.created_at,m.id LIMIT 13', $types, $args);
    $more = count($rows) > 12; $rows = array_slice($rows, 0, 12); $last = $rows ? end($rows) : null;
    $next = $more ? rtrim(strtr(base64_encode(json_encode(array($kind, $value, $last['occurred_on'], $last['created_at'], (int) $last['id']))), '+/', '-_'), '=') : null;
    return array('items' => array_map(function ($row) use ($user) { return c_present($row, $user); }, $rows), 'next' => $next);
}
function c_month_recap($user, $month = '')
{
    c_stories_member($user); list($month, $start, $end) = c_recap_month($month);
    $stats = c_one('SELECT COUNT(*) moments,COUNT(DISTINCT occurred_on) days FROM couple_moments WHERE deleted_at IS NULL AND occurred_on>=? AND occurred_on<?', 'ss', array($start, $end));
    $photos = c_one('SELECT COUNT(*) photos FROM couple_images i JOIN couple_moments m ON m.id=i.moment_id WHERE i.removed_at IS NULL AND m.deleted_at IS NULL AND m.occurred_on>=? AND m.occurred_on<?', 'ss', array($start, $end));
    $images = c_all('SELECT i.id,m.id moment_id FROM couple_images i JOIN couple_moments m ON m.id=i.moment_id WHERE i.removed_at IS NULL AND m.deleted_at IS NULL AND m.occurred_on>=? AND m.occurred_on<? ORDER BY m.occurred_on,m.created_at,m.id,i.sort_order,i.id LIMIT 9', 'ss', array($start, $end));
    $stories = c_all('SELECT t.id,t.title FROM couple_stories t WHERE EXISTS(SELECT 1 FROM couple_story_moments s JOIN couple_moments m ON m.id=s.moment_id WHERE s.story_id=t.id AND m.deleted_at IS NULL AND m.occurred_on>=? AND m.occurred_on<?) ORDER BY t.id DESC', 'ss', array($start, $end));
    return array('month' => $month, 'today' => c_today(), 'moments' => (int) $stats['moments'], 'days' => (int) $stats['days'], 'photos' => (int) $photos['photos'], 'images' => array_map(function ($image) { return array('url' => '/moment-image.php?id=' . $image['id'], 'momentId' => (int) $image['moment_id']); }, $images), 'stories' => $stories);
}
