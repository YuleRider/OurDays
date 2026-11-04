<?php
require_once __DIR__ . '/CoupleStories.php';

function c_story_tile($item, $moment = 0)
{
    echo '<article class="story-tile panel"><a class="story-cover" href="/moments.php?view=story&amp;storyId=' . $item['id'] . '">';
    if ($item['cover']) echo '<img loading="lazy" src="' . c_h($item['cover']) . '" alt="故事里的照片">';
    else echo '<span aria-hidden="true">♡</span>';
    echo '</a><div><small class="story-number">故事 ' . $item['id'] . ' · ' . $item['count'] . ' 条日常</small><h2><a href="/moments.php?view=story&amp;storyId=' . $item['id'] . '">' . c_h($item['title']) . '</a></h2><p>' . c_h($item['intro']) . '</p>';
    if ($moment) echo '<button type="button" class="secondary-button" data-story-join="' . $item['id'] . '" data-moment="' . (int) $moment . '" data-joined="' . ($item['joined'] ? '1' : '0') . '" aria-pressed="' . ($item['joined'] ? 'true' : 'false') . '">' . ($item['joined'] ? '已加入 · 移出故事' : '加入这个故事') . '</button>';
    echo '</div></article>';
}
function c_story_form($story = null)
{
    ?><form id="story-editor" data-id="<?= $story ? $story['id'] : '' ?>" data-revision="<?= $story ? $story['revision'] : 0 ?>"><label>故事名称<input name="title" maxlength="80" required value="<?= c_h($story['title'] ?? '') ?>" placeholder="例如：武汉约会（最多 40 字）"></label><label>一点介绍<textarea name="intro" rows="2" maxlength="400" placeholder="想怎样记住这段时光？（选填，最多 200 字）"><?= c_h($story['intro'] ?? '') ?></textarea></label><p class="field-hint" id="story-error" role="alert"></p><button class="primary-button" type="submit"><?= $story ? '保存故事介绍' : '建立一个故事' ?></button></form><?php
}
function c_stories_view($user, $data, $moment = 0)
{
    echo '<header class="section-heading"><div><h1>' . ($moment ? '整理到故事' : '我们的故事') . '</h1><p>' . ($moment ? '双方都可以整理归属，日常内容与范围由作者维护。' : '把一次约会、一段旅行，慢慢串成完整的回忆。') . '</p></div></header>';
    c_memory_nav('stories');
    if ($moment) echo '<p><a class="text-link" href="/moments.php?id=' . (int) $moment . '">← 回到这条日常</a></p>';
    echo '<section class="panel"><details class="story-create"><summary>＋ 建立一个故事</summary>'; c_story_form(); echo '</details></section>';
    echo '<div class="story-list" id="story-list" data-moment="' . (int) $moment . '">';
    foreach ($data['items'] as $item) c_story_tile($item, $moment);
    echo '</div>';
    if (!$data['items']) echo '<section class="empty-state"><h2>故事还在慢慢发生</h2><p>先建一个主题，再从日常详情把相关记录放进来。</p></section>';
    echo '<button type="button" class="secondary-button" id="stories-more" data-next="' . ($data['next'] ?: '') . '"' . (!$data['next'] ? ' hidden' : '') . '>看看其他故事</button>';
}
function c_story_view($user, $story, $feed)
{
    c_memory_nav('stories');
    echo '<header class="section-heading story-heading"><div><span class="eyebrow">故事 ' . $story['id'] . ' · 仅我们可见</span><h1>' . c_h($story['title']) . '</h1><p class="story-intro">' . c_h($story['intro']) . '</p></div><a class="text-link" href="/moments.php?view=stories">全部故事 →</a></header>';
    echo '<section class="panel"><details><summary>编辑故事介绍</summary>'; c_story_form($story);
    echo '<button type="button" class="text-link danger" id="story-delete" data-id="' . $story['id'] . '" data-revision="' . $story['revision'] . '">删除这个主题</button></details></section>';
    echo '<p class="field-hint">从最早的一天开始读。想增加记录？在日常详情选择“整理到故事”。</p><div id="moment-feed" data-story="' . $story['id'] . '">';
    foreach ($feed['items'] as $post) c_card($post, $user);
    echo '</div>';
    if (!$feed['items']) echo '<section class="empty-state"><h2>先放进一个值得记住的日子</h2><a class="text-link" href="/moments.php">去日常里挑一条 →</a></section>';
    c_memory_more($feed['next']);
}
function c_recap_view($user, $data, $feed)
{
    $month = $data['month']; $current = substr($data['today'], 0, 7); $start = new DateTimeImmutable($month . '-01');
    echo '<header class="section-heading"><div><h1>这个月的我们</h1><p>普通的日子，也值得翻一翻。</p></div></header>'; c_memory_nav('recap');
    echo '<section class="panel"><form class="month-picker" method="get"><input type="hidden" name="view" value="recap"><label for="recap-month">回看月份</label><input id="recap-month" type="month" name="month" min="1900-01" max="' . c_h($current) . '" value="' . c_h($month) . '" required><button class="secondary-button" type="submit">查看</button></form><div class="month-heading">';
    echo $month > '1900-01' ? '<a class="text-link" href="/moments.php?view=recap&amp;month=' . $start->modify('-1 month')->format('Y-m') . '">‹ 上个月</a>' : '<span></span>';
    echo '<h2>' . $start->format('Y年n月') . '</h2>';
    echo $month < $current ? '<a class="text-link" href="/moments.php?view=recap&amp;month=' . $start->modify('+1 month')->format('Y-m') . '">下个月 ›</a>' : '<span></span>';
    echo '</div><p class="recap-caption">' . ($month === $current ? '截至今天 · ' . c_h($data['today']) : '收藏在这个月的日常') . '</p><div class="recap-stats">';
    foreach (array('moments' => '条日常', 'days' => '天有记录', 'photos' => '张照片') as $key => $label) echo '<div><b>' . $data[$key] . '</b><span>' . $label . '</span></div>';
    echo '</div>';
    if ($data['images']) {
        echo '<div class="recap-puzzle">';
        foreach ($data['images'] as $image) echo '<a href="/moments.php?id=' . $image['momentId'] . '" aria-label="打开照片所属日常"><img loading="lazy" src="' . c_h($image['url']) . '" alt="这个月留下的照片"></a>';
        echo '</div>';
    }
    if ($data['stories']) {
        echo '<div class="recap-stories"><h3>这个月走进的故事</h3>';
        foreach ($data['stories'] as $story) echo '<a class="pill-button" href="/moments.php?view=story&amp;storyId=' . (int) $story['id'] . '">' . c_h($story['title']) . ' <small>· ' . (int) $story['id'] . '</small></a>';
        echo '</div>';
    }
    echo '</section>';
    if (!$data['moments']) echo '<section class="empty-state"><h2>这个月还没有留下日常</h2><p>想起来的时候，也可以慢慢补记。</p><a class="primary-button" href="/moments.php?view=write&amp;month=' . c_h($month) . '">补记这个月</a></section>';
    else echo '<header class="section-heading"><h2>按日子慢慢翻</h2><a class="text-link" href="/moments.php?view=calendar&amp;month=' . c_h($month) . '">回到月历 →</a></header>';
    echo '<div id="moment-feed" data-month="' . c_h($month) . '">'; foreach ($feed['items'] as $post) c_card($post, $user); echo '</div>'; c_memory_more($feed['next']);
}
function c_inspiration_view()
{
    $prompts = array('小确幸' => "今天的小确幸：\n哪一刻让你笑了？\n想和对方分享什么？", '一起吃饭' => "一起吃了什么：\n哪道菜最想再吃一次？\n那时聊了些什么？", '约会见面' => "这次见面的片段：\n一起做了什么？\n最想记住哪个瞬间？", '旅行片段' => "旅途里的小事：\n今天走到了哪里？\n有什么意料之外的收获？", '第一次' => "我们又多了一个第一次：\n第一次一起做了什么？\n当时是什么感觉？", '想对你说' => "今天想对你说：\n有什么感谢或想念？\n下次见面想一起做什么？");
    echo '<details class="inspiration-box"><summary>今天想记点什么</summary><p class="field-hint">挑一个方向看看，只在你点击加入时写入输入框。</p><div class="inspiration-options">';
    foreach ($prompts as $title => $prompt) echo '<button type="button" class="pill-button" data-inspiration="' . c_h($prompt) . '">' . $title . '</button>';
    echo '</div><div id="inspiration-preview" hidden><p id="inspiration-questions"></p><button type="button" class="secondary-button" id="inspiration-insert">加入输入框</button></div></details>';
}
