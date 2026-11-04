<?php
// Loaded by Couple.php; all dates shown to the couple use Beijing time.
function c_today() { return (new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai')))->format('Y-m-d'); }
function c_local_day($utc) { return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Shanghai'))->format('Y-m-d'); }
function c_occurred_on($value)
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || $value < '1900-01-01' || $value > c_today()) throw new CoupleError('发生日期请选择 1900 年至今天之间的日期');
    $parts = explode('-', $value);
    if (!checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) throw new CoupleError('发生日期不正确');
    return $value;
}
function c_image_ids($value)
{
    if (!is_array($value) || count($value) > 9) throw new CoupleError('最多保留九张照片');
    $ids = array_map('c_id', $value);
    if (count(array_unique($ids)) !== count($ids)) throw new CoupleError('照片不能重复');
    return $ids;
}
function c_validate_images($ids, $user, $context = 0)
{
    foreach ($ids as $id) {
        $image = c_one('SELECT owner_id,moment_id,removed_at FROM couple_images WHERE id=? FOR UPDATE', 'i', array($id));
        if (!$image || (int) $image['owner_id'] !== $user['id'] || $image['removed_at'] !== null || ($image['moment_id'] !== null && (!$context || (int) $image['moment_id'] !== $context))) throw new CoupleError('照片已失效，或不属于这条记录', 403);
    }
}
function c_payload_matches($post, $body, $visibility, $date, $ids)
{
    $current = array_map(function ($image) { return (int) $image['id']; }, c_all('SELECT id FROM couple_images WHERE moment_id=? AND removed_at IS NULL ORDER BY sort_order,id', 'i', array($post['id'])));
    return $post['body'] === $body && $post['visibility'] === $visibility && $post['occurred_on'] === $date && $current === $ids;
}
function c_feed_cursor($row, $published)
{
    return rtrim(strtr(base64_encode(json_encode(array($published ? 'published' : 'occurred', $row['occurred_on'], $row['created_at'], (int) $row['id']))), '+/', '-_'), '=');
}
function c_read_cursor($value, $published)
{
    if (!is_string($value) || strlen($value) > 256 || !preg_match('/^[A-Za-z0-9_-]+$/', $value)) throw new CoupleError('分页位置已失效，请刷新');
    $row = json_decode(base64_decode(strtr($value, '-_', '+/'), true), true);
    if (!is_array($row) || count($row) !== 4 || $row[0] !== ($published ? 'published' : 'occurred') || !is_string($row[1]) || !is_string($row[2]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $row[1]) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row[2])) throw new CoupleError('分页位置已失效，请刷新');
    c_occurred_on($row[1]); c_id($row[3]);
    return $row;
}
function c_calendar($user, $month)
{
    c_member($user);
    $month = $month === '' ? substr(c_today(), 0, 7) : $month;
    if (!is_string($month) || !preg_match('/^\d{4}-\d{2}$/', $month)) throw new CoupleError('月份不正确');
    c_occurred_on($month . '-01');
    $start = new DateTimeImmutable($month . '-01', new DateTimeZone('Asia/Shanghai'));
    $end = $start->modify('+1 month')->format('Y-m-d');
    $rows = c_all('SELECT occurred_on,COUNT(*) total FROM couple_moments WHERE deleted_at IS NULL AND occurred_on>=? AND occurred_on<? GROUP BY occurred_on ORDER BY occurred_on', 'ss', array($month . '-01', $end));
    return array('month' => $month, 'today' => c_today(), 'days' => array_map(function ($row) { return array('date' => $row['occurred_on'], 'count' => (int) $row['total']); }, $rows));
}
function c_memories($user, $cursor = '', $limit = 12, $today = null)
{
    c_member($user);
    $today = $today ?: c_today(); c_occurred_on($today);
    return c_feed($user, $cursor, $limit, false, array('memoryDay' => $today));
}
function c_removed_images($user, $before = 0)
{
    c_member($user);
    $sql = 'SELECT i.*,m.body,m.deleted_at FROM couple_images i JOIN couple_moments m ON m.id=i.moment_id WHERE i.owner_id=? AND m.author_id=? AND i.removed_at>?';
    $types = 'iis'; $values = array($user['id'], $user['id'], gmdate('Y-m-d H:i:s', time() - 7 * 86400));
    if ($before) {
        $cursor = c_one('SELECT removed_at FROM couple_images WHERE id=? AND owner_id=?', 'ii', array(c_id($before), $user['id']));
        if (!$cursor || !$cursor['removed_at']) throw new CoupleError('照片列表已变化，请刷新后再看', 409);
        $sql .= ' AND (i.removed_at<? OR (i.removed_at=? AND i.id<?))'; $types .= 'ssi'; $values = array_merge($values, array($cursor['removed_at'], $cursor['removed_at'], c_id($before)));
    }
    $rows = c_all($sql . ' ORDER BY i.removed_at DESC,i.id DESC LIMIT 25', $types, $values);
    $more = count($rows) > 24; $rows = array_slice($rows, 0, 24); $items = array();
    foreach ($rows as $row) $items[] = array('id' => (int) $row['id'], 'momentId' => (int) $row['moment_id'], 'url' => '/moment-image.php?id=' . $row['id'], 'name' => $row['original_name'], 'removedAt' => c_iso($row['removed_at']), 'expiresAt' => c_iso(gmdate('Y-m-d H:i:s', strtotime($row['removed_at'] . ' UTC') + 7 * 86400)), 'parentDeleted' => (bool) $row['deleted_at']);
    return array('items' => $items, 'next' => $more ? (int) end($rows)['id'] : null);
}
function c_restore_image($id, $user)
{
    c_member($user); $image = c_one('SELECT * FROM couple_images WHERE id=?', 'i', array(c_id($id)));
    if (!$image || (int) $image['owner_id'] !== $user['id'] || $image['moment_id'] === null) throw new CoupleError('照片不存在', 404);
    $db = c_db(); $db->begin_transaction();
    try {
        $post = c_one('SELECT * FROM couple_moments WHERE id=? FOR UPDATE', 'i', array($image['moment_id']));
        if (!$post) throw new CoupleError('日常不存在', 404);
        c_owner($post, $user);
        if ($post['deleted_at']) throw new CoupleError('请先恢复所属日常，再恢复照片', 409);
        $image = c_one('SELECT * FROM couple_images WHERE id=? FOR UPDATE', 'i', array($id));
        if (!$image) throw new CoupleError('照片不存在', 404);
        if ($image['removed_at'] === null) { $db->commit(); return; }
        if (strtotime($image['removed_at'] . ' UTC') <= time() - 7 * 86400) throw new CoupleError('照片已超过七天恢复期限', 410);
        $active = c_one('SELECT COUNT(*) n,MAX(sort_order) last_order FROM couple_images WHERE moment_id=? AND removed_at IS NULL', 'i', array($post['id']));
        if ((int) $active['n'] >= 9) throw new CoupleError('这条日常已有九张照片，请先腾出一个位置', 409);
        c_q('UPDATE couple_images SET removed_at=NULL,sort_order=? WHERE id=?', 'ii', array((int) $active['last_order'] + 1, $id));
        c_q('UPDATE couple_moments SET revision=revision+1,last_edit_key=NULL,updated_at=? WHERE id=?', 'si', array(c_now(), $post['id']));
        $db->commit();
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}
function c_memory_migrate()
{
    $columns = array('couple_moments' => array('occurred_on' => 'DATE NULL', 'revision' => 'INT UNSIGNED NOT NULL DEFAULT 1', 'last_edit_key' => 'CHAR(32) NULL'), 'couple_images' => array('removed_at' => 'DATETIME NULL'), 'couple_drafts' => array('occurred_on' => 'DATE NULL', 'base_moment_revision' => 'INT UNSIGNED NULL'));
    foreach ($columns as $table => $fields) foreach ($fields as $field => $definition) if (!c_one('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', 'ss', array($table, $field))) c_q('ALTER TABLE ' . $table . ' ADD COLUMN ' . $field . ' ' . $definition);
    c_q('UPDATE couple_moments SET occurred_on=DATE(DATE_ADD(created_at,INTERVAL 8 HOUR)) WHERE occurred_on IS NULL');
    // An old text-only editing draft implicitly kept every existing photo.
    foreach (c_all('SELECT d.user_id,d.context_id,m.occurred_on,m.revision FROM couple_drafts d JOIN couple_moments m ON m.id=d.context_id AND m.author_id=d.user_id WHERE d.context_id>0 AND d.base_moment_revision IS NULL') as $row) {
        $ids = array_map(function ($image) { return (int) $image['id']; }, c_all('SELECT id FROM couple_images WHERE moment_id=? AND removed_at IS NULL ORDER BY sort_order,id', 'i', array($row['context_id'])));
        c_q('UPDATE couple_drafts SET occurred_on=?,base_moment_revision=?,image_ids=? WHERE user_id=? AND context_id=? AND base_moment_revision IS NULL', 'sisii', array($row['occurred_on'], $row['revision'], json_encode($ids), $row['user_id'], $row['context_id']));
    }
    if (!c_one("SHOW INDEX FROM couple_moments WHERE Key_name='memory_feed'")) c_q('ALTER TABLE couple_moments ADD INDEX memory_feed(deleted_at,occurred_on,created_at,id)');
}
function c_memory_cleanup()
{
    $cutoff = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
    c_q('DELETE d FROM couple_drafts d LEFT JOIN couple_moments m ON m.id=d.context_id WHERE d.context_id>0 AND (m.id IS NULL OR (m.deleted_at IS NOT NULL AND m.deleted_at<=?))', 's', array($cutoff));
    foreach (c_all('SELECT id FROM couple_images WHERE removed_at IS NOT NULL AND removed_at<=?', 's', array($cutoff)) as $candidate) {
        c_db()->begin_transaction();
        try {
        $image = c_one('SELECT id,original_file,display_file FROM couple_images WHERE id=? AND removed_at IS NOT NULL AND removed_at<=? FOR UPDATE', 'is', array($candidate['id'], $cutoff));
        if (!$image) { c_db()->commit(); continue; }
        foreach (array('originals' => 'original_file', 'displays' => 'display_file') as $folder => $column) {
            if (!preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/', $image[$column])) throw new RuntimeException('Invalid removed image filename');
            $file = c_media_dir() . '/' . $folder . '/' . $image[$column];
            if (is_link($file) || (is_file($file) && !unlink($file))) throw new RuntimeException('Cannot clean expired removed image');
        }
        c_q('DELETE FROM couple_images WHERE id=? AND removed_at<=?', 'is', array($image['id'], $cutoff));
        c_db()->commit();
        } catch (Throwable $error) { c_db()->rollback(); throw $error; }
    }
}
