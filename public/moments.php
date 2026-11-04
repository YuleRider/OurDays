<?php
require_once __DIR__ . '/admin/CoupleView.php';
c_init();
$user = c_user();
$view = is_string($_GET['view'] ?? '') ? ($_GET['view'] ?? '') : '';
if (is_file(likegirl_runtime_dir() . '/memory-disabled') && in_array($view, array('write','calendar','memories','removed'), true)) { http_response_code(503); exit('记录与回顾功能正在维护，请稍后再试。'); }
if (is_file(likegirl_runtime_dir() . '/stories-disabled') && in_array($view, array('stories','story','recap'), true)) { http_response_code(503); exit('故事与月份回顾正在维护，请稍后再试。'); }
$error = null; $post = null; $dateEdit = null; $writeDraft = array('exists' => false, 'revision' => 0, 'images' => array());
$filters = array('q' => '', 'scope' => 'all', 'photos' => false);
try {
    $filters = c_feed_filters($_GET, $user);
    if (isset($_GET['id']) && $view !== 'write') $post = c_present(c_post($_GET['id'], $user), $user, true);
    if ($view === 'write' && isset($_GET['id'])) { $raw = c_post($_GET['id'], $user); c_owner($raw, $user); $post = c_present($raw, $user); }
    if (in_array($view, array('write', 'messages', 'trash', 'dates', 'calendar', 'memories', 'removed', 'stories', 'story', 'recap'), true)) c_member($user);
    if ($view === 'calendar') { c_calendar($user, $_GET['month'] ?? ''); if (!empty($_GET['day']) && substr($_GET['day'],0,7) !== ($_GET['month'] ?? substr(c_today(),0,7))) throw new CoupleError('请选择当前月份内的日期'); }
    if ($view === 'write') $writeDraft = c_draft($post ? $post['id'] : 0, $user);
    if ($view === 'stories') $storyData = c_stories($user, 0, empty($_GET['momentId']) ? 0 : c_id($_GET['momentId']));
    if ($view === 'story') { $story = c_story($_GET['storyId'] ?? 0, $user); $storyFeed = c_story_feed($user, 'story', $story['id']); }
    if ($view === 'recap') { $recap = c_month_recap($user, $_GET['month'] ?? ''); $recapFeed = c_story_feed($user, 'month', $recap['month']); }
    if ($view === 'write' && !$post && !$writeDraft['exists'] && isset($_GET['month'])) { list($presetMonth, $presetDay) = c_recap_month($_GET['month']); $writeDraft['occurredOn'] = $presetMonth === substr(c_today(),0,7) ? c_today() : $presetDay; }
    if ($view === 'dates' && isset($_GET['dateId'])) {
        $dateEdit = c_one('SELECT id,title,kind,event_date,visibility FROM couple_dates WHERE id=?', 'i', array(c_id($_GET['dateId'])));
        if (!$dateEdit) throw new CoupleError('这个日期不存在', 404);
    }
} catch (CoupleError $exception) { http_response_code($exception->status); $error = $exception->getMessage(); }
c_view_start($view === 'dates' ? '纪念日与生日' : '我们的日常', in_array($view, array('me', 'messages', 'trash', 'dates'), true) ? 'me' : 'daily', $user);
?>
<?php if ($error): ?>
<section class="empty-state"><span>♡</span><h1><?= c_h($error) ?></h1><a class="primary-button" href="/moments.php?view=me">进入我的</a></section>
<?php elseif ($view === 'me' && !$user): ?>
<section class="login-panel panel"><span class="eyebrow">ONLY FOR US</span><h1>回来看看我们的日常</h1><p class="muted">使用你们现有的账号登录，私密记录和回应只留给彼此。</p><form id="couple-login" method="post" action="/couple-api.php"><input type="hidden" name="action" value="login"><input type="hidden" name="csrf" value="<?= c_h(c_csrf()) ?>"><label>账号<input name="name" autocomplete="username" required maxlength="100" placeholder="你的账号"></label><label>密码<input name="password" type="password" autocomplete="current-password" required maxlength="512" placeholder="你的密码"></label><button class="primary-button" type="submit">进入我们的小世界</button></form><a href="/index.php" class="text-link">先看看公开的故事</a></section>
<?php elseif ($view === 'me'): ?>
<section class="profile-panel panel"><img class="profile-avatar" src="<?= c_h(c_identity($user)['avatar']) ?>" alt=""><span class="eyebrow">OUR LITTLE CORNER</span><h1><?= c_h(c_identity($user)['name']) ?>，欢迎回来</h1><p class="muted">写下一点日常，留给另一个人慢慢看。</p><a class="primary-button" href="/moments.php?view=write">＋ 写一条日常</a><div class="profile-menu"><a href="/list.php">我们的共同清单<span>计划与完成回忆 →</span></a><a href="/moments.php?view=calendar">回忆月历<span>按日子翻看我们的故事 →</span></a><?php if (!is_file(likegirl_runtime_dir() . '/stories-disabled')): ?><a href="/moments.php?view=stories">我们的故事<span>把日常串成完整回忆 →</span></a><a href="/moments.php?view=recap">月份回顾<span>再翻一翻这个月 →</span></a><?php endif; ?><a href="/moments.php?view=dates">纪念日与生日<span>设置倒计时 →</span></a><a href="/moments.php?view=messages">对方的回应 <i class="unread-dot" hidden></i><span>查看消息 →</span></a><a href="/moments.php?view=trash">回收站<span>七天内可恢复 →</span></a><a href="/admin/index.php" target="_blank" rel="noopener">原有内容管理<span>小记 / 相册 / 清单 →</span></a><button type="button" id="couple-logout">退出当前账号</button></div></section>
<?php elseif ($view === 'stories'): c_stories_view($user, $storyData, empty($_GET['momentId']) ? 0 : c_id($_GET['momentId'])); ?>
<?php elseif ($view === 'story'): c_story_view($user, $story, $storyFeed); ?>
<?php elseif ($view === 'recap'): c_recap_view($user, $recap, $recapFeed); ?>
<?php elseif ($view === 'dates'): c_dates_settings($user, $dateEdit); ?>
<?php elseif ($view === 'calendar'): c_calendar_view($user, $_GET['month'] ?? '', $_GET['day'] ?? ''); ?>
<?php elseif ($view === 'memories'): $memoryFeed = c_memories($user); ?>
<header class="section-heading"><div><h1>往年今日</h1><p>翻到这些年的今天，再见一次那时的我们。</p></div></header><?php c_memory_nav(''); ?><div id="moment-feed" data-memories="1"><?php foreach($memoryFeed['items'] as $item) c_card($item,$user); ?></div><?php if(!$memoryFeed['items']): ?><section class="empty-state"><p>往年的今天还没有记录。现在留下的日常，会慢慢成为以后的小惊喜。</p></section><?php endif; ?><?php c_memory_more($memoryFeed['next']); ?>
<?php elseif ($view === 'removed'): $removed = c_removed_images($user); ?>
<header class="section-heading"><div><h1>最近移除的照片</h1><p>只有你能找回自己移除的照片，保留七天。</p></div></header><nav class="memory-tabs"><a href="/moments.php?view=trash">删除的日常</a><a href="/moments.php?view=removed" aria-current="page">移除的照片</a></nav><div id="removed-photos"><?php foreach($removed['items'] as $image) c_removed_card($image); ?></div><?php if(!$removed['items']): ?><section class="empty-state"><p>这里暂时没有可恢复的照片。</p></section><?php endif; ?><button id="removed-more" class="secondary-button" data-next="<?= c_h($removed['next'] ?: '') ?>" <?= !$removed['next'] ? 'hidden' : '' ?>>再看看之前移除的照片</button>
<?php elseif ($view === 'messages'): ?>
<header class="section-heading"><div><span class="eyebrow">A LITTLE RESPONSE</span><h1>对方的回应</h1><p>小小的喜欢和回应，也值得被看见。</p></div></header><div id="notifications-list"></div><button id="notifications-more" class="secondary-button" hidden>再看看之前的消息</button>
<?php elseif ($view === 'write'): $writeVisibility = $writeDraft['exists'] ? $writeDraft['visibility'] : ($post['visibility'] ?? 'private'); ?>
<section class="editor-panel panel"><a class="text-link" href="/moments.php">← 回到日常</a><span class="eyebrow">A PIECE OF TODAY</span><h1><?= $post ? '再补上一点回忆' : '今天，想留住什么？' ?></h1><?php c_inspiration_view(); ?><form method="post" action="/couple-api.php" id="moment-editor" data-edit-id="<?= $post ? $post['id'] : '' ?>"><label class="sr-only" for="moment-body">日常内容</label><textarea id="moment-body" name="body" maxlength="4000" rows="6" placeholder="一顿好吃的饭、一句突然想说的话，或者只是想你了。" data-limit="2000"><?= c_h($writeDraft['exists'] ? $writeDraft['body'] : ($post['body'] ?? '')) ?></textarea><div class="editor-counter"><span id="text-count">0</span> / 2000</div>
<div class="occurrence-field"><label for="occurred-on">这件事发生在哪一天？</label><input id="occurred-on" name="occurredOn" type="date" min="1900-01-01" max="<?= c_today() ?>" value="<?= c_h($writeDraft['occurredOn']) ?>" required><small>默认今天，也可以补记过去的日子。</small></div>
<label class="photo-picker">＋ 添加照片<input type="file" id="moment-photos" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" multiple></label><p class="field-hint">最多九张，每张原图 10MB 以内。可调整顺序；保存后移除的照片，七天内可在回收站找回。</p><div id="upload-list" class="upload-list"></div>
<fieldset class="visibility-picker"><legend>这条日常给谁看？</legend><label><input type="radio" name="visibility" value="private" <?= $writeVisibility === 'private' ? 'checked' : '' ?>><span><b>仅我们可见</b><small>登录你们的账号后才能看</small></span></label><label><input type="radio" name="visibility" value="public" <?= $writeVisibility === 'public' ? 'checked' : '' ?>><span><b>公开分享</b><small>访客可看文字和浏览图，互动仍只留给彼此</small></span></label></fieldset><p id="draft-status" class="field-hint" role="status"><?= $writeDraft['conflict'] ? '记录已在另一设备更新。请复制草稿文字，再清空旧草稿以打开最新记录。' : ($writeDraft['missingImages'] ? '草稿文字已保留，部分照片已失效，请重新选择。' : ($writeDraft['exists'] ? '已恢复这个账号的私密草稿。' : '文字、日期和照片顺序会自动保存为你的私密草稿。')) ?></p><button type="button" id="draft-save" class="text-link">立即保存草稿</button><button type="button" id="draft-discard" class="text-link">清空这份草稿</button><script type="application/json" id="draft-data"><?= json_encode($writeDraft, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script><p id="editor-error" role="alert"></p><button class="primary-button" type="submit"><?= $post ? '保存修改' : '把这一天留下来' ?></button></form></section>
<?php elseif ($post): ?>
<header class="section-heading"><a class="text-link" href="/moments.php">← 回到日常</a></header><?php c_card($post, $user, true); ?>
<?php if ($user && !is_file(likegirl_runtime_dir() . '/stories-disabled')): ?><a class="secondary-button story-organize" href="/moments.php?view=stories&amp;momentId=<?= $post['id'] ?>">整理到故事 →</a><?php endif; ?>
<?php if ($user): ?><section class="replies-panel panel" id="replies"><h2>留给彼此的话</h2><div id="reply-list"><?php foreach ($post['replies'] as $reply): ?><div class="reply"><b><?= c_h($reply['username'] ?: $reply['user']) ?></b><p><?= c_h($reply['body']) ?></p><time datetime="<?= c_h(c_iso($reply['created_at'])) ?>"></time></div><?php endforeach; ?></div><form method="post" action="/couple-api.php" id="reply-form" data-id="<?= $post['id'] ?>"><label class="sr-only" for="reply-text">写下回应</label><textarea id="reply-text" name="body" rows="3" maxlength="1000" placeholder="想对你说……（仅你们可见）" required></textarea><button class="primary-button" type="submit">把回应送给你</button></form></section><?php else: ?><p class="private-note">喜欢和回应，只留给彼此。</p><?php endif; ?>
<?php else: ?>
<header class="section-heading"><div><span class="eyebrow"><?= $view === 'trash' ? 'KEEP IT A LITTLE LONGER' : 'OUR EVERYDAY STORY' ?></span><h1><?= $view === 'trash' ? '暂时收起的回忆' : '我们的日常' ?></h1><p><?= $view === 'trash' ? '移入回收站后的七天内，可以恢复原来的记录和照片。' : ($user ? '普通的一天，因为分享给你而有了意义。' : '这里是我们愿意分享的小小日常。') ?></p></div><?php if ($user && $view !== 'trash'): ?><a class="primary-button" href="/moments.php?view=write">＋ 写日常</a><?php endif; ?></header>
<?php if ($view === 'trash'): ?><nav class="memory-tabs"><a href="/moments.php?view=trash" aria-current="page">删除的日常</a><a href="/moments.php?view=removed">移除的照片</a></nav><?php else: if ($user) c_memory_nav('timeline'); c_feed_form($user, $filters); endif; ?>
<div id="moment-feed" data-trash="<?= $view === 'trash' ? '1' : '0' ?>" data-query="<?= c_h($filters['q']) ?>" data-scope="<?= c_h($filters['scope']) ?>" data-photos="<?= $filters['photos'] ? '1' : '0' ?>"><?php $feed = c_feed($user, 0, 12, $view === 'trash', $filters); foreach ($feed['items'] as $item) c_card($item, $user); ?></div><?php if (!$feed['items']): ?><section class="empty-state"><span>♡</span><h2><?= $view === 'trash' ? '这里暂时没有记录' : ($filters['q'] !== '' || $filters['photos'] || $filters['scope'] !== 'all' ? '暂时没有符合条件的日常' : '故事，正在慢慢发生') ?></h2><p><?= $filters['q'] !== '' || $filters['photos'] || $filters['scope'] !== 'all' ? '换个关键词或清除筛选，再看看吧。' : ($user ? '把今天的一点小事留下来吧。' : '以后再来，或许就有新的故事。') ?></p></section><?php endif; ?><button id="feed-more" class="secondary-button" data-next="<?= $feed['next'] ?: '' ?>" <?= !$feed['next'] ? 'hidden' : '' ?>>再看看之前的日子</button>
<?php endif; ?>
<?php c_view_end($user); ?>
