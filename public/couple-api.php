<?php
require_once __DIR__ . '/admin/CoupleStories.php';
c_init();
header('Content-Type: application/json; charset=utf-8');
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $data = array();
    if ($method === 'POST') {
        $data = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === 0 ? json_decode(file_get_contents('php://input'), true) : $_POST;
        if (!is_array($data)) throw new CoupleError('请求格式不正确');
        c_verify_csrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['csrf'] ?? ''));
    }
    $action = $method === 'GET' ? ($_GET['action'] ?? 'bootstrap') : ($data['action'] ?? '');
    $user = c_user();
    if (is_file(likegirl_runtime_dir() . '/memory-disabled') && in_array($action, array('calendar','memories','removedImages','restoreImage','publish','edit','saveDraft'), true)) throw new CoupleError('记录与回顾功能正在维护，请稍后再试', 503);
    $result = null;
    if ($action === 'bootstrap' && $method === 'GET') $result = array('user' => c_identity($user), 'csrf' => c_csrf(), 'unread' => c_unread($user), 'today' => c_today());
    elseif ($action === 'login' && $method === 'POST') { $user = c_login($data['name'] ?? '', $data['password'] ?? ''); $result = array('user' => c_identity($user), 'csrf' => c_csrf(), 'unread' => c_unread($user)); }
    elseif ($action === 'logout' && $method === 'POST') { c_logout(); $result = array('csrf' => c_csrf()); }
    else {
        if ($method === 'POST') { c_member($user); c_limit('write:' . $user['id'], 60, 60); }
        session_write_close();
        if ($action === 'stories' && $method === 'GET') $result = c_stories($user, $_GET['before'] ?? 0, empty($_GET['momentId']) ? 0 : c_id($_GET['momentId']));
        elseif ($action === 'story' && $method === 'GET') $result = c_story_summary(c_story($_GET['id'] ?? 0, $user));
        elseif ($action === 'storyFeed' && $method === 'GET') $result = c_story_feed($user, 'story', $_GET['id'] ?? 0, $_GET['cursor'] ?? '');
        elseif ($action === 'monthFeed' && $method === 'GET') $result = c_story_feed($user, 'month', $_GET['month'] ?? '', $_GET['cursor'] ?? '');
        elseif ($action === 'monthRecap' && $method === 'GET') $result = c_month_recap($user, $_GET['month'] ?? '');
        elseif ($action === 'saveStory' && $method === 'POST') $result = array('id' => c_save_story($data, $user));
        elseif ($action === 'deleteStory' && $method === 'POST') { c_delete_story($data, $user); $result = true; }
        elseif ($action === 'storyMembership' && $method === 'POST') { c_story_membership($data, $user); $result = true; }
        elseif ($action === 'feed' && $method === 'GET') $result = c_feed($user, $_GET['cursor'] ?? ($_GET['before'] ?? 0), 12, ($_GET['trash'] ?? '') === '1', c_feed_filters($_GET, $user));
        elseif ($action === 'detail' && $method === 'GET') $result = c_present(c_post($_GET['id'] ?? 0, $user), $user, true);
        elseif ($action === 'notifications' && $method === 'GET') $result = c_notifications($user, $_GET['before'] ?? 0);
        elseif ($action === 'unread' && $method === 'GET') { c_member($user); $result = array('count' => c_unread($user)); }
        elseif ($action === 'calendar' && $method === 'GET') $result = c_calendar($user, $_GET['month'] ?? '');
        elseif ($action === 'memories' && $method === 'GET') $result = c_memories($user, $_GET['cursor'] ?? '');
        elseif ($action === 'removedImages' && $method === 'GET') $result = c_removed_images($user, $_GET['before'] ?? 0);
        elseif ($action === 'restoreImage' && $method === 'POST') { c_restore_image($data['id'] ?? 0, $user); $result = true; }
        elseif ($action === 'dates' && $method === 'GET') $result = c_dates($user);
        elseif ($action === 'saveDate' && $method === 'POST') $result = array('id' => c_save_date($data, $user));
        elseif ($action === 'deleteDate' && $method === 'POST') { c_q('DELETE FROM couple_dates WHERE id=?', 'i', array(c_id($data['id'] ?? 0))); $result = true; }
        elseif ($action === 'tasks' && $method === 'GET') $result = c_tasks($user);
        elseif ($action === 'saveTask' && $method === 'POST') $result = array('key' => c_save_task($data, $user));
        elseif ($action === 'completeTask' && $method === 'POST') {
            $task = c_task($data['key'] ?? '', $user);
            $result = array('key' => c_save_task(array('key' => $task['key'], 'revision' => $data['revision'] ?? -1, 'title' => $task['title'], 'note' => $task['note'], 'dueDate' => $task['due_date'] ?? '', 'completed' => $data['completed'] ?? null, 'completedDate' => $task['completed_at'] ?? '', 'momentId' => $task['moment_id']), $user));
        }
        elseif ($action === 'draft' && $method === 'GET') $result = c_draft($_GET['context'] ?? 0, $user);
        elseif ($action === 'saveDraft' && $method === 'POST') $result = c_save_draft($data, $user);
        elseif ($action === 'discardDraft' && $method === 'POST') {
            $context = c_draft_context($data['context'] ?? 0, $user);
            c_db()->begin_transaction();
            try { c_draft_publish_lock(array('requestKey' => $data['requestKey'] ?? '', 'draftRevision' => $data['revision'] ?? -1), $user, $context); c_q('DELETE FROM couple_drafts WHERE user_id=? AND context_id=?', 'ii', array($user['id'], $context)); c_db()->commit(); }
            catch (Throwable $error) { c_db()->rollback(); throw $error; }
            $result = true;
        }
        elseif ($action === 'publish' && $method === 'POST') $result = array('id' => c_publish($data, $user));
        elseif ($action === 'edit' && $method === 'POST') $result = array('id' => c_edit($data, $user));
        elseif ($action === 'delete' && $method === 'POST') { c_delete($data['id'] ?? 0, $user); $result = true; }
        elseif ($action === 'restore' && $method === 'POST') { c_restore($data['id'] ?? 0, $user); $result = true; }
        elseif ($action === 'like' && $method === 'POST') { c_like(c_id($data['id'] ?? 0), $data['liked'] ?? null, $user); $result = true; }
        elseif ($action === 'reply' && $method === 'POST') { c_reply($data, $user); $result = true; }
        elseif ($action === 'read' && $method === 'POST') { c_q('UPDATE couple_notifications SET read_at=? WHERE id=? AND recipient_id=?', 'sii', array(c_now(), c_id($data['id'] ?? 0), $user['id'])); $result = true; }
        elseif ($action === 'upload' && $method === 'POST') $result = c_upload($_FILES['photo'] ?? null, $user, $data['requestKey'] ?? '');
        elseif ($action === 'discard' && $method === 'POST') {
            $key = c_key($data['requestKey'] ?? '');
            $image = c_one('SELECT * FROM couple_images WHERE request_key=? AND owner_id=? AND moment_id IS NULL', 'si', array($key, $user['id']));
            if ($image) c_q('DELETE FROM couple_images WHERE id=? AND owner_id=? AND moment_id IS NULL', 'ii', array($image['id'], $user['id']));
            // Physical orphan cleanup runs in the private media maintenance job.
            $result = true;
        } else throw new CoupleError('请求不存在', 404);
    }
    echo json_encode(array('ok' => true, 'data' => $result), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (CoupleError $error) {
    http_response_code($error->status);
    echo json_encode(array('ok' => false, 'message' => $error->getMessage()), JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('LikeGirl couple request failed: ' . get_class($error));
    http_response_code(503);
    echo json_encode(array('ok' => false, 'message' => '暂时无法完成，请稍后重试；输入内容会保留'), JSON_UNESCAPED_UNICODE);
}
