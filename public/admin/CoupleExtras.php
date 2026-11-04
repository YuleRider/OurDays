<?php
require_once __DIR__ . '/Couple.php';
function c_task($key, $user, $lock = false)
{
    c_member($user);
    if (!is_string($key) || !preg_match('/^(legacy|new)-([1-9][0-9]{0,14})$/', $key, $match)) throw new CoupleError('清单条目不存在', 404);
    $suffix = $lock ? ' FOR UPDATE' : '';
    if ($match[1] === 'legacy') {
        $old = c_one('SELECT * FROM lovelist WHERE id=?' . $suffix, 'i', array((int) $match[2]));
        if (!$old) throw new CoupleError('清单条目不存在', 404);
        $row = c_one('SELECT * FROM couple_tasks WHERE legacy_id=?' . $suffix, 'i', array((int) $match[2])) ?: array();
        $row['legacy_id'] = (int) $old['id'];
        $row['title'] = html_entity_decode($old['eventname'], ENT_QUOTES, 'UTF-8');
        $row['completed'] = (bool) $old['icon'];
        $row['hasLegacyImage'] = !empty($old['imgurl']) && $old['imgurl'] !== '0';
        $url = html_entity_decode((string) $old['imgurl'], ENT_QUOTES, 'UTF-8');
        $row['legacyImage'] = preg_match('~^(https?://|/(?!/))~i', $url) ? $url : '';
    } else {
        $row = c_one('SELECT * FROM couple_tasks WHERE id=? AND legacy_id IS NULL' . $suffix, 'i', array((int) $match[2]));
        if (!$row) throw new CoupleError('清单条目不存在', 404);
        $row['completed'] = $row['completed_at'] !== null;
    }
    $row += array('due_date' => null, 'completed_at' => null, 'note' => '', 'moment_id' => null, 'revision' => 0);
    $row['key'] = $key;
    if (!$row['completed']) $row['completed_at'] = null;
    return $row;
}
function c_tasks($user)
{
    c_member($user); $items = array();
    foreach (c_all('SELECT id FROM lovelist ORDER BY id DESC') as $row) $items[] = c_task('legacy-' . $row['id'], $user);
    foreach (c_all('SELECT id FROM couple_tasks WHERE legacy_id IS NULL ORDER BY id DESC') as $row) $items[] = c_task('new-' . $row['id'], $user);
    usort($items, function ($a, $b) {
        if ($a['completed'] !== $b['completed']) return $a['completed'] <=> $b['completed'];
        return $a['completed'] ? strcmp($b['completed_at'] ?? '', $a['completed_at'] ?? '') : strcmp($a['due_date'] ?? '9999-12-31', $b['due_date'] ?? '9999-12-31');
    });
    return $items;
}
function c_save_task($data, $user)
{
    c_member($user);
    $title = c_text($data['title'] ?? '', 100); $note = c_text($data['note'] ?? '', 1000);
    if ($title === '') throw new CoupleError('先写下想一起做的事吧');
    $due = $data['dueDate'] ?? ''; if ($due !== '') c_calendar_date($due);
    $completed = $data['completed'] ?? false;
    if (!is_bool($completed)) throw new CoupleError('请选择完成状态');
    $done = $data['completedDate'] ?? ''; if ($done !== '') c_calendar_date($done);
    $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Shanghai')))->format('Y-m-d');
    if ($done !== '' && $done > $today) throw new CoupleError('完成日期不能晚于今天');
    $moment = empty($data['momentId']) ? null : c_id($data['momentId']);
    if ($moment) c_post($moment, $user);
    $key = $data['key'] ?? ''; $revision = $data['revision'] ?? 0;
    if (!is_int($revision) || $revision < 0) throw new CoupleError('清单版本不正确');
    $db = c_db(); $db->begin_transaction();
    try {
        $old = $key !== '' ? c_task($key, $user, true) : null;
        if ($old && (int) $old['revision'] !== $revision) throw new CoupleError('对方刚刚更新了这件事，请保留当前文字并刷新后再修改', 409);
        $done = !$completed ? null : ($done !== '' ? $done : ($old && $old['completed'] ? $old['completed_at'] : $today));
        $due = $due === '' ? null : $due;
        if (!$old) {
            $request = c_key($data['requestKey'] ?? '');
            $saved = c_one('SELECT * FROM couple_tasks WHERE creator_id=? AND request_key=? FOR UPDATE', 'is', array($user['id'], $request));
            if ($saved) {
                if ($saved['title'] !== $title || $saved['note'] !== $note || $saved['due_date'] !== $due || $saved['completed_at'] !== $done || $saved['moment_id'] != $moment) throw new CoupleError('这次提交已保存，请先查看清单', 409);
                $key = 'new-' . $saved['id'];
            } else {
                c_q('INSERT INTO couple_tasks(title,due_date,completed_at,note,moment_id,creator_id,request_key,created_at,updated_at,revision) VALUES(?,?,?,?,?,?,?,?,?,1)', 'ssssiisss', array($title, $due, $done, $note, $moment, $user['id'], $request, c_now(), c_now()));
                $key = 'new-' . $db->insert_id;
            }
        } elseif (!empty($old['legacy_id'])) {
            c_q('UPDATE lovelist SET eventname=?,icon=? WHERE id=?', 'sii', array(htmlspecialchars($title, ENT_QUOTES, 'UTF-8'), (int) $completed, $old['legacy_id']));
            c_q('INSERT INTO couple_tasks(legacy_id,title,due_date,completed_at,note,moment_id,creator_id,request_key,created_at,updated_at,revision) VALUES(?,?,?,?,?,?,?,?,?,?,1) ON DUPLICATE KEY UPDATE due_date=VALUES(due_date),completed_at=VALUES(completed_at),note=VALUES(note),moment_id=VALUES(moment_id),updated_at=VALUES(updated_at),revision=revision+1', 'issssiisss', array($old['legacy_id'], '', $due, $done, $note, $moment, $user['id'], bin2hex(random_bytes(16)), c_now(), c_now()));
        } else c_q('UPDATE couple_tasks SET title=?,due_date=?,completed_at=?,note=?,moment_id=?,updated_at=?,revision=revision+1 WHERE id=?', 'ssssisi', array($title, $due, $done, $note, $moment, c_now(), $old['id']));
        $db->commit(); return $key;
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}
function c_draft_context($value, $user)
{
    c_member($user); $context = empty($value) ? 0 : c_id($value);
    if ($context) c_owner(c_post($context, $user), $user);
    return $context;
}
function c_draft($context, $user)
{
    $context = c_draft_context($context, $user); $post = $context ? c_post($context, $user) : null;
    $row = c_one('SELECT * FROM couple_drafts WHERE user_id=? AND context_id=?', 'ii', array($user['id'], $context));
    $exists = (bool) $row;
    if (!$row) {
        $ids = $context ? array_map(function ($image) { return (int) $image['id']; }, c_all('SELECT id FROM couple_images WHERE moment_id=? AND removed_at IS NULL ORDER BY sort_order,id', 'i', array($context))) : array();
        $row = array('revision' => 0, 'body' => $post['body'] ?? '', 'visibility' => $post['visibility'] ?? 'private', 'occurred_on' => $post['occurred_on'] ?? c_today(), 'base_moment_revision' => $post['revision'] ?? null, 'image_ids' => json_encode($ids), 'publish_key' => null, 'updated_at' => null);
    }
    $images = array(); $missing = false;
    foreach (json_decode($row['image_ids'], true) ?: array() as $id) {
        $image = c_one('SELECT * FROM couple_images WHERE id=? AND owner_id=? AND removed_at IS NULL', 'ii', array($id, $user['id']));
        if ($image && ($image['moment_id'] === null || ($context && (int) $image['moment_id'] === $context))) $images[] = array('id' => (int) $id, 'key' => $image['request_key'], 'name' => $image['original_name'], 'preview' => '/moment-image.php?id=' . $id, 'existing' => $image['moment_id'] !== null);
        else $missing = true;
    }
    return array('exists' => $exists, 'revision' => (int) $row['revision'], 'body' => $row['body'], 'visibility' => $row['visibility'], 'occurredOn' => $row['occurred_on'] ?: ($post['occurred_on'] ?? c_today()), 'baseRevision' => $context ? (int) $row['base_moment_revision'] : null, 'publishKey' => $row['publish_key'], 'images' => $images, 'updatedAt' => $row['updated_at'] ? c_iso($row['updated_at']) : null, 'conflict' => $context && (int) $row['base_moment_revision'] !== (int) $post['revision'], 'missingImages' => $missing);
}

function c_save_draft($data, $user)
{
    $context = c_draft_context($data['context'] ?? 0, $user);
    $body = c_text($data['body'] ?? '', 2000); $visibility = c_visibility($data['visibility'] ?? 'private');
    $key = c_key($data['requestKey'] ?? ''); $revision = $data['revision'] ?? null; $base = $data['baseRevision'] ?? null;
    if (!is_int($revision) || $revision < 0) throw new CoupleError('草稿版本不正确');
    if ($context && (!is_int($base) || $base < 1 || !isset($data['occurredOn']))) throw new CoupleError('编辑页面已更新，请先复制文字，再刷新页面', 409);
    $ids = c_image_ids($data['images'] ?? array()); $encoded = json_encode($ids); $db = c_db(); $db->begin_transaction();
    try {
        if ($context) {
            $post = c_one('SELECT * FROM couple_moments WHERE id=? AND deleted_at IS NULL FOR UPDATE', 'i', array($context));
            if (!$post) throw new CoupleError('日常已被删除，草稿输入已保留', 409);
            c_owner($post, $user);
            if ((int) $post['revision'] !== $base) throw new CoupleError('另一设备已修改日常，请保留当前输入后重新打开最新记录', 409);
        }
        $old = c_one('SELECT * FROM couple_drafts WHERE user_id=? AND context_id=? FOR UPDATE', 'ii', array($user['id'], $context));
        $date = c_occurred_on($data['occurredOn'] ?? (($old['occurred_on'] ?? '') ?: c_today()));
        if ($old && $old['publish_key'] === $key && $old['body'] === $body && $old['visibility'] === $visibility && $old['image_ids'] === $encoded && $old['occurred_on'] === $date && (!$context || (int) $old['base_moment_revision'] === $base)) { $db->commit(); return array('revision' => (int) $old['revision']); }
        if (($old ? (int) $old['revision'] : 0) !== $revision || ($old && $old['publish_key'] !== $key)) throw new CoupleError('另一页面已更新或发布草稿，当前输入已保留，请复制文字后刷新', 409);
        c_validate_images($ids, $user, $context);
        $next = $revision + 1;
        c_q('INSERT INTO couple_drafts(user_id,context_id,body,visibility,image_ids,publish_key,revision,updated_at,occurred_on,base_moment_revision) VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE body=VALUES(body),visibility=VALUES(visibility),image_ids=VALUES(image_ids),revision=VALUES(revision),updated_at=VALUES(updated_at),occurred_on=VALUES(occurred_on),base_moment_revision=VALUES(base_moment_revision)', 'iissssissi', array($user['id'], $context, $body, $visibility, $encoded, $key, $next, c_now(), $date, $context ? $base : null));
        $db->commit(); return array('revision' => $next);
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}


function c_draft_publish_lock($data, $user, $context)
{
    $draft = c_one('SELECT * FROM couple_drafts WHERE user_id=? AND context_id=? FOR UPDATE', 'ii', array($user['id'], $context));
    if (!$draft || $draft['publish_key'] !== ($data['requestKey'] ?? '') || (int) $draft['revision'] !== ($data['draftRevision'] ?? -1)) throw new CoupleError('草稿已经更新，请复制当前文字后刷新再发布', 409);
}
