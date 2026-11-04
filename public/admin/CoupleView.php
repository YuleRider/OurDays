<?php
require_once __DIR__ . '/CoupleStoryView.php';
function c_settings()
{
    static $settings;
    if ($settings === null) $settings = c_one('SELECT * FROM text LIMIT 1') ?: array();
    return $settings;
}
function c_url($url)
{
    return is_string($url) && (preg_match('~^https?://~i', $url) || (substr($url, 0, 1) === '/' && substr($url, 0, 2) !== '//')) ? $url : '';
}
function c_avatar($settings, $who)
{
    if ((string) ($settings['customavatar'] ?? '') === '1' && c_url($settings[$who . 'img'] ?? '')) return c_url($settings[$who . 'img']);
    $qq = $settings[$who . 'QQ'] ?? '';
    return preg_match('/^[0-9]+$/', $qq) ? 'https://q1.qlogo.cn/g?b=qq&nk=' . rawurlencode($qq) . '&s=100' : '';
}
function c_navigation($active = '', $legacy = false)
{
    $links = array('home' => array('/index.php', '首页', '⌂'), 'daily' => array('/moments.php', '日常', '♡'),
        'album' => array('/loveImg.php', '相册', '▧'), 'me' => array('/moments.php?view=me', '我的', '◉'));
    echo '<nav class="couple-navigation" aria-label="主要导航">';
    foreach ($links as $key => $link) {
        echo '<a data-no-pjax href="' . $link[0] . '"' . ($key === $active ? ' aria-current="page"' : '') . '><span aria-hidden="true">' . $link[2] . '</span><b>' . $link[1] . '</b>' . ($key === 'me' ? '<i class="unread-dot" hidden aria-label="未读消息"></i>' : '') . '</a>';
    }
    echo '</nav>';
}
function c_view_start($title, $active, $user)
{
    $settings = c_settings();
    if (is_file(__DIR__ . '/../ip.php')) include_once __DIR__ . '/../ip.php';
    $site = $settings['title'] ?? '我们的恋爱小站';
    $logo = preg_replace('/\{([^}]+)\}/', '<b>$1</b>', c_h($settings['logo'] ?? '我们的恋爱小站'));
    $diy = c_one('SELECT * FROM diyset LIMIT 1') ?: array();
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#ffffff"><title>' . c_h($title . ' · ' . $site) . '</title><link rel="icon" href="/Style/img/favicon.svg"><link rel="stylesheet" href="/Style/css/index.css"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;700&amp;display=swap" rel="stylesheet" media="print" onload="this.media=&#39;all&#39;"><link rel="stylesheet" href="/Style/css/couple.css?v=4-stories"></head>';
    echo '<body class="couple-page" data-couple-user="' . ($user ? (int) $user['id'] : '') . '" data-couple-page="' . c_h($active) . '"><div class="header-wrap"><div class="header"><div class="logo"><h1><a class="alogo" href="/index.php">' . $logo . '</a></h1></div><div class="word"><span class="wenan">' . c_h($settings['writing'] ?? '') . '</span></div></div></div>';
    $background = c_url($settings['bgimg'] ?? '');
    echo '<div class="bg-wrap"><div class="bg-img"' . ($background ? ' style="background-image:url(&quot;' . c_h($background) . '&quot;)"' : '') . '><div class="central central-800"><div class="middle' . (($diy['Blurkg'] ?? '') === '2' ? ' Blurkg' : '') . '">';
    foreach (array('boy', 'girl') as $position => $who) {
        if ($position) echo '<div class="love-icon"><img src="/Style/img/like.svg" alt="心意"></div>';
        echo '<div class="img-' . ($who === 'boy' ? 'male' : 'female') . '"><img src="' . c_h(c_avatar($settings, $who)) . '" alt="' . c_h($settings[$who] ?? '') . '"><span>' . c_h($settings[$who] ?? '') . '</span></div>';
    }
    echo '</div></div><svg class="waves" xmlns="http://www.w3.org/2000/svg" viewBox="0 24 150 28" preserveAspectRatio="none" aria-hidden="true"><defs><path id="couple-gentle-wave" d="M-160 44c30 0 58-18 88-18s58 18 88 18 58-18 88-18 58 18 88 18v44h-352z"/></defs><g class="parallax"><use href="#couple-gentle-wave" x="48" y="0" fill="rgba(255,255,255,.7)"/><use href="#couple-gentle-wave" x="48" y="3" fill="rgba(255,255,255,.5)"/><use href="#couple-gentle-wave" x="48" y="5" fill="rgba(255,255,255,.3)"/><use href="#couple-gentle-wave" x="48" y="7" fill="#fff"/></g></svg></div></div>';
    c_navigation($active);
    echo '<main class="couple-main">';
}
function c_view_end($user)
{
    $settings = c_settings();
    echo '</main><footer class="couple-footer"><p>把普通的日子，过成我们的故事。</p>';
    if (!empty($settings['icp'])) echo '<a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener">' . c_h($settings['icp']) . '</a>';
    echo '</footer>';
    echo '<p class="text-ce"><a href="https://github.com/YuleRider/OurDays">OurDays 项目源码</a> · <a href="/open-source.php">开源声明</a></p>';
    c_photo_dialog();
    echo '<div id="couple-toast" role="status" aria-live="polite"></div><script src="/Style/js/couple.js?v=4-stories" defer></script></body></html>';
}
function c_photo_dialog()
{
    echo '<dialog id="photo-viewer"><button type="button" id="viewer-close" aria-label="关闭照片">×</button><div class="viewer-stage"><button type="button" id="viewer-prev" aria-label="上一张照片">‹</button><img id="viewer-image" alt="日常照片"><button type="button" id="viewer-next" aria-label="下一张照片">›</button></div><p id="viewer-counter" aria-live="polite"></p><p id="viewer-error" role="status"></p><a id="viewer-original" class="secondary-button" hidden>下载原图（仅你们可见）</a></dialog>';
}
function c_feed_form($user, $filters)
{
    ?>
    <form class="feed-filters panel" method="get" action="/moments.php"><label class="search-label">找一条日常<input type="search" name="q" maxlength="200" value="<?= c_h($filters['q']) ?>" placeholder="输入关键词，比如晚霞、周末"></label><div class="filter-options"><label>谁写的<select name="scope"><option value="all">全部日常</option><?php if ($user): ?><option value="mine" <?= $filters['scope'] === 'mine' ? 'selected' : '' ?>>我写的</option><option value="partner" <?= $filters['scope'] === 'partner' ? 'selected' : '' ?>>对方写的</option><?php endif; ?></select></label><label class="photos-filter"><input type="checkbox" name="photos" value="1" <?= $filters['photos'] ? 'checked' : '' ?>>只看有照片的</label><button class="primary-button" type="submit">查找</button><a class="text-link" href="/moments.php">清除筛选</a></div></form>
    <?php
}
function c_date_caption($item)
{
    return $item['kind'] === 'birthday' ? '每年生日' : ($item['years'] > 0 ? '第 ' . $item['years'] . ' 周年' : '第一次纪念');
}
function c_dates_block($user)
{
    $items = array_slice(c_dates($user), 0, 3);
    if (!$items && !$user) return;
    echo '<section class="couple-dates central central-800" data-calendar-now="' . (time() * 1000) . '" data-calendar-today="' . (new DateTimeImmutable('today', new DateTimeZone('Asia/Shanghai')))->format('Y-m-d') . '"><header class="section-heading"><h2>值得期待的日子</h2>';
    if ($user) echo '<a data-no-pjax class="text-link" href="/moments.php?view=dates">管理日期 →</a>';
    echo '</header><div class="date-cards">';
    foreach ($items as $item) {
        echo '<article class="date-card' . (!$item['days'] ? ' is-today' : '') . '"><h3><span aria-hidden="true">' . ($item['kind'] === 'birthday' ? '◇' : '♡') . '</span> ' . c_h($item['title']) . '</h3><p class="date-count">' . ($item['days'] ? '还有 <strong>' . $item['days'] . '</strong> 天' : '<strong>就是今天</strong> ♡') . '</p><p class="date-caption">' . c_h(substr($item['nextDate'], 5, 2) . '月' . substr($item['nextDate'], 8, 2) . '日 · ' . c_date_caption($item)) . '</p>';
        if ($user && $item['visibility'] === 'private') echo '<small>仅我们可见</small>';
        echo '</article>';
    }
    if (!$items) echo '<p class="muted">添上一个生日或纪念日，让平常的日子多一点期待。</p>';
    echo '</div></section>';
}
function c_dates_settings($user, $edit)
{
    $kind = $edit['kind'] ?? 'birthday';
    $birthday = $kind === 'birthday';
    $items = c_dates($user);
    ?>
    <header class="section-heading"><div><h1>纪念日与生日</h1><p>把重要的日子记在一起，每年都有一点期待。</p></div><a class="text-link" href="/moments.php?view=me">返回我的 →</a></header>
    <section class="panel date-settings"><h2><?= $edit ? '编辑这个日子' : '添加一个日子' ?></h2>
    <form id="date-editor" method="post" action="/couple-api.php" data-edit-id="<?= $edit ? (int) $edit['id'] : '' ?>">
    <label>名称<input name="title" maxlength="80" required value="<?= c_h($edit['title'] ?? '') ?>" placeholder="例如：小白的生日（最多 40 字）"></label>
    <label>类型<select name="kind" id="date-kind"><option value="birthday" <?= $birthday ? 'selected' : '' ?>>生日 · 每年</option><option value="anniversary" <?= !$birthday ? 'selected' : '' ?>>纪念日 · 每年</option></select></label>
    <div id="anniversary-fields" <?= $birthday ? 'hidden' : '' ?>><label>最初的日期（公历）<input name="date" type="date" min="1900-01-01" max="2100-12-31" value="<?= !$birthday ? c_h($edit['event_date'] ?? '') : '' ?>" <?= $birthday ? 'disabled' : 'required' ?>></label></div>
    <div id="birthday-fields" <?= !$birthday ? 'hidden' : '' ?>><div class="birthday-inputs"><label>生日月份（公历）<input name="month" type="number" inputmode="numeric" min="1" max="12" placeholder="月" value="<?= $birthday && $edit ? (int) substr($edit['event_date'], 5, 2) : '' ?>" <?= $birthday ? 'required' : 'disabled' ?>></label><label>日期<input name="day" type="number" inputmode="numeric" min="1" max="31" placeholder="日" value="<?= $birthday && $edit ? (int) substr($edit['event_date'], 8, 2) : '' ?>" <?= $birthday ? 'required' : 'disabled' ?>></label></div><p class="field-hint">只记录月和日，不需要填写出生年份。</p></div>
    <fieldset class="visibility-picker"><legend>谁可以看到？</legend><label><input type="radio" name="visibility" value="private" <?= !$edit || $edit['visibility'] === 'private' ? 'checked' : '' ?>><span><b>仅我们可见</b><small>首页也只在你们登录后显示</small></span></label><label><input type="radio" name="visibility" value="public" <?= $edit && $edit['visibility'] === 'public' ? 'checked' : '' ?>><span><b>公开分享</b><small>访客也能看到这个日期的倒计时</small></span></label></fieldset>
    <p id="date-error" role="alert"></p><div class="date-form-actions"><button class="primary-button" type="submit"><?= $edit ? '保存修改' : '记下这个日子' ?></button><?php if ($edit): ?><a class="text-link" href="/moments.php?view=dates">取消编辑</a><?php endif; ?></div></form>
    <p class="field-hint">按北京时间计算；当天显示“就是今天”，次日开始期待下一年。2 月 29 日在非闰年按 2 月 28 日提醒。</p></section>
    <section class="panel date-settings"><h2>已经记下的日子</h2><p class="field-hint">你们两个账号都可以维护这些日期；首页显示最近的三个。</p>
    <?php foreach ($items as $item): ?><article class="date-row"><div><h3><?= c_h($item['title']) ?></h3><p><?= c_h(substr($item['nextDate'], 5, 2) . '月' . substr($item['nextDate'], 8, 2) . '日 · ' . c_date_caption($item)) ?> · <?= $item['days'] ? '还有 ' . $item['days'] . ' 天' : '就是今天 ♡' ?></p><small><?= $item['id'] === 'together' ? '沿用首页相伴起始日 · 在原有内容管理中修改' : ($item['visibility'] === 'private' ? '仅我们可见' : '公开') ?></small></div><?php if ($item['id'] !== 'together'): ?><div class="date-row-actions"><a class="text-link" href="/moments.php?view=dates&amp;dateId=<?= (int) $item['id'] ?>">编辑</a><button type="button" class="text-link danger" data-date-delete="<?= (int) $item['id'] ?>">删除</button></div><?php endif; ?></article><?php endforeach; ?>
    <?php if (!$items): ?><p class="muted">还没有设置日期，先记下一个吧。</p><?php endif; ?></section>
    <?php
}
function c_card($post, $user, $detail = false)
{
    $id = (int) $post['id'];
    echo '<article class="daily-card" data-moment-id="' . $id . '"><header class="daily-byline"><img src="' . c_h($post['author']['avatar']) . '" alt="" loading="lazy"><div><b>' . c_h($post['author']['name']) . '</b><span class="moment-day">' . c_h(str_replace('-', '.', $post['occurredOn'])) . '</span>' . ($post['backfilled'] ? '<span class="backfill-tag">补记</span>' : '') . '</div>';
    if ($user) echo '<span class="visibility-tag">' . ($post['visibility'] === 'private' ? '仅我们可见' : '公开') . '</span>';
    echo '</header>';
    if ($detail) echo '<p class="published-at">发布于 <time datetime="' . c_h($post['createdAt']) . '"></time></p>';
    if ($post['body'] !== '') echo '<p class="daily-body">' . c_h($post['body']) . '</p>';
    if ($post['images'] && !$post['deleted']) {
        echo '<div class="daily-grid count-' . count($post['images']) . '">';
        foreach ($post['images'] as $image) echo '<button type="button" class="image-button" data-display="' . c_h($image['url']) . '"' . ($user ? ' data-original="' . c_h($image['original']) . '"' : '') . ' aria-label="查看照片"><img src="' . c_h($image['url']) . '" alt="日常照片" width="' . (int) $image['width'] . '" height="' . (int) $image['height'] . '" loading="lazy"></button>';
        echo '</div>';
    }
    echo '<footer class="daily-actions">';
    if (!$detail && !$post['deleted']) echo '<a href="/moments.php?id=' . $id . '" class="text-link">看看这一天 <span aria-hidden="true">↗</span></a>';
    if ($user && !$post['deleted']) {
        if (!$post['mine']) echo '<button type="button" class="like-button' . ($post['liked'] ? ' is-liked' : '') . '" data-like="' . $id . '" data-liked="' . ($post['liked'] ? '1' : '0') . '" aria-pressed="' . ($post['liked'] ? 'true' : 'false') . '">♡ 喜欢 <span>' . $post['likes'] . '</span></button>';
        else echo '<span class="muted">♡ ' . $post['likes'] . ' 个喜欢</span>';
        if (!$detail) echo '<a href="/moments.php?id=' . $id . '#replies" class="text-link">回应 ' . $post['replyCount'] . '</a>';
        if ($post['mine']) echo '<a class="text-link" href="/moments.php?view=write&id=' . $id . '">编辑</a><button class="text-link danger" type="button" data-delete="' . $id . '">移入回收站</button>';
    }
    if ($post['deleted']) echo '<button class="pill-button" type="button" data-restore="' . $id . '">恢复这条记录</button><small class="muted">保留原来的可见范围</small>';
    echo '</footer></article>';
}

function c_memory_nav($active)
{
    echo '<nav class="memory-tabs" aria-label="日常浏览方式"><a href="/moments.php"' . ($active === 'timeline' ? ' aria-current="page"' : '') . '>时间轴</a><a href="/moments.php?view=calendar"' . ($active === 'calendar' ? ' aria-current="page"' : '') . '>回忆月历</a>' . (is_file(likegirl_runtime_dir() . '/stories-disabled') ? '' : '<a href="/moments.php?view=stories"' . ($active === 'stories' ? ' aria-current="page"' : '') . '>我们的故事</a><a href="/moments.php?view=recap"' . ($active === 'recap' ? ' aria-current="page"' : '') . '>月份回顾</a>') . '</nav>';
}
function c_calendar_view($user, $month, $day)
{
    $calendar = c_calendar($user, $month); $month = $calendar['month']; $today = $calendar['today'];
    if ($day !== '' && (substr(c_occurred_on($day), 0, 7) !== $month)) throw new CoupleError('请选择当前月份内的日期');
    $start = new DateTimeImmutable($month . '-01', new DateTimeZone('Asia/Shanghai'));
    $counts = array_column($calendar['days'], 'count', 'date');
    echo '<header class="section-heading"><div><h1>回忆月历</h1><p>挑一个日子，再看看那天的我们。</p></div><a class="primary-button" href="/moments.php?view=write">＋ 写日常</a></header>';
    c_memory_nav('calendar');
    if (!is_file(likegirl_runtime_dir() . '/stories-disabled')) echo '<a class="text-link" href="/moments.php?view=recap&amp;month=' . c_h($month) . '">查看这个月的回顾 →</a>';
    echo '<section class="panel memory-calendar"><form class="month-picker" method="get"><input type="hidden" name="view" value="calendar"><label for="memory-month">想看哪个月</label><input id="memory-month" type="month" name="month" min="1900-01" max="' . c_h(substr($today, 0, 7)) . '" value="' . c_h($month) . '" required><button class="secondary-button" type="submit">查看</button></form><div class="month-heading">';
    if ($month > '1900-01') echo '<a class="text-link" aria-label="上个月" href="/moments.php?view=calendar&amp;month=' . $start->modify('-1 month')->format('Y-m') . '">‹ 上个月</a>'; else echo '<span></span>';
    echo '<h2>' . $start->format('Y年n月') . '</h2>';
    if ($month < substr($today, 0, 7)) echo '<a class="text-link" aria-label="下个月" href="/moments.php?view=calendar&amp;month=' . $start->modify('+1 month')->format('Y-m') . '">下个月 ›</a>'; else echo '<span></span>';
    echo '</div><div class="calendar-grid">';
    foreach (array('一','二','三','四','五','六','日') as $label) echo '<span class="weekday">' . $label . '</span>';
    for ($empty = 1; $empty < (int) $start->format('N'); $empty++) echo '<span aria-hidden="true"></span>';
    for ($number = 1; $number <= (int) $start->format('t'); $number++) {
        $date = $month . '-' . sprintf('%02d', $number); $count = $counts[$date] ?? 0;
        $class = 'calendar-day' . ($count ? ' has-memory' : '') . ($date === $day ? ' selected' : '') . ($date === $today ? ' today' : '');
        if ($date > $today) echo '<span class="' . $class . ' future">' . $number . '</span>';
        else echo '<a class="' . $class . '" href="/moments.php?view=calendar&amp;month=' . $month . '&amp;day=' . $date . '" aria-label="' . $date . '，' . $count . '条日常"' . ($date === $day ? ' aria-current="date"' : '') . '><b>' . $number . '</b><small>' . ($count ? $count . '条' : '') . '</small></a>';
    }
    echo '</div></section>';
    if ($day === '') { echo '<p class="calendar-hint">点选日期，翻一翻那天留下的文字和照片。</p>'; return; }
    echo '<header class="section-heading"><h2>' . c_h($day) . ' 的日常</h2></header>';
    $feed = c_feed($user, '', 12, false, array('day' => $day));
    echo '<div id="moment-feed" data-day="' . c_h($day) . '">'; foreach ($feed['items'] as $item) c_card($item, $user); echo '</div>';
    if (!$feed['items']) echo '<section class="empty-state"><p>这一天还没有记录，想起来时也可以慢慢补上。</p></section>';
    c_memory_more($feed['next']);
}
function c_memory_more($next)
{
    echo '<button id="feed-more" class="secondary-button" data-next="' . c_h($next ?: '') . '"' . (!$next ? ' hidden' : '') . '>再看看之前的日子</button>';
}
function c_memory_home($user)
{
    if (!$user || is_file(likegirl_runtime_dir() . '/memory-disabled')) return;
    $feed = c_memories($user, '', 3); if (!$feed['items']) return;
    echo '<section class="couple-feature memory-home central central-800"><header class="section-heading"><div><h2>往年今日</h2><p>原来，我们已经收藏了这么多普通日子。</p></div><a data-no-pjax class="text-link" href="/moments.php?view=memories">看看这些年的今天 →</a></header><div class="memory-recaps">';
    foreach ($feed['items'] as $item) {
        echo '<a data-no-pjax class="memory-recap" href="/moments.php?id=' . $item['id'] . '">';
        if ($item['images']) echo '<img loading="lazy" src="' . c_h($item['images'][0]['url']) . '" alt="那一天的照片">';
        $text = preg_replace('/\s+/u', ' ', $item['body']); preg_match('/^.{0,90}/us', $text, $snippet);
        echo '<div><b>' . c_h($item['occurredOn']) . '</b><p>' . c_h(($snippet[0] ?? '') ?: '一起留下的一张照片。') . '</p></div></a>';
    }
    echo '</div></section>';
}
function c_removed_card($image)
{
    echo '<article class="panel removed-photo-card" data-removed-image="' . $image['id'] . '">';
    if (!$image['parentDeleted']) echo '<img src="' . c_h($image['url']) . '" alt="' . c_h($image['name']) . '" loading="lazy">';
    echo '<div><p>' . c_h($image['name']) . '</p><small>恢复期限：<time datetime="' . c_h($image['expiresAt']) . '"></time></small>';
    if ($image['parentDeleted']) echo '<p class="muted">请先在记录回收站恢复所属日常。</p><a class="text-link" href="/moments.php?view=trash">去恢复日常</a>';
    else echo '<button class="secondary-button" type="button" data-photo-restore="' . $image['id'] . '">恢复到这条日常</button><a class="text-link" href="/moments.php?id=' . $image['momentId'] . '">查看所属日常</a>';
    echo '</div></article>';
}
