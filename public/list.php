<?php
require_once __DIR__ . '/admin/CoupleView.php';
c_init(); $user = c_user(); $error = null; $task = null;
try {
    c_member($user);
    if (isset($_GET['edit']) && $_GET['edit'] !== 'new') $task = c_task($_GET['edit'], $user);
    $tasks = c_tasks($user);
} catch (CoupleError $exception) { http_response_code($exception->status); $error = $exception->getMessage(); }
c_view_start('我们的共同清单', 'me', $user);
?>
<?php if ($error): ?><section class="empty-state"><h1><?= c_h($error) ?></h1><p>共同清单仅你们登录后可见。</p><a class="primary-button" href="/moments.php?view=me">进入我的</a></section>
<?php else: ?>
<header class="section-heading"><div><h1>一起想做的事</h1><p>只属于我们的约定，慢慢把它们变成回忆。</p></div><a class="primary-button" href="/list.php?edit=new">＋ 加一件事</a></header>
<?php if (isset($_GET['edit'])): ?>
<section class="panel task-settings"><h2><?= $task ? '再补上一点计划' : '写下一个小小的约定' ?></h2><form method="post" action="/couple-api.php" id="task-editor" data-key="<?= c_h($task['key'] ?? '') ?>" data-revision="<?= (int) ($task['revision'] ?? 0) ?>">
<label>想一起做什么？<input name="title" maxlength="200" required value="<?= c_h($task['title'] ?? '') ?>" placeholder="比如：一起去看海（最多 100 字）"></label>
<label>希望哪天完成？（可不填）<input name="dueDate" type="date" min="1900-01-01" max="2100-12-31" value="<?= c_h($task['due_date'] ?? '') ?>"></label>
<label class="photos-filter"><input type="checkbox" name="completed" <?= !empty($task['completed']) ? 'checked' : '' ?>>已经一起完成了</label>
<label id="task-done-field" <?= empty($task['completed']) ? 'hidden' : '' ?>>完成日期（可补填）<input name="completedDate" type="date" min="1900-01-01" max="<?= (new DateTimeImmutable('today', new DateTimeZone('Asia/Shanghai')))->format('Y-m-d') ?>" value="<?= c_h($task['completed_at'] ?? '') ?>"></label>
<label>计划，或完成后的心情<textarea name="note" maxlength="2000" rows="4" placeholder="想去哪里、为什么想做，或者那一天的小感受……（最多 1000 字）"><?= c_h($task['note'] ?? '') ?></textarea></label>
<label id="task-memory">关联一条日常作为回忆<select name="momentId"><option value="">暂不关联</option><?php
$choices = c_all('SELECT id,body FROM couple_moments WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 500');
if (!empty($task['moment_id']) && !in_array($task['moment_id'], array_column($choices, 'id'))) {
    try { $older = c_post($task['moment_id'], $user); $choices[] = array('id' => $older['id'], 'body' => $older['body']); } catch (CoupleError $ignored) {}
}
foreach ($choices as $choice): $characters = preg_split('//u', $choice['body'], -1, PREG_SPLIT_NO_EMPTY); ?><option value="<?= (int) $choice['id'] ?>" <?= ($task['moment_id'] ?? null) == $choice['id'] ? 'selected' : '' ?>><?= c_h(implode('', array_slice($characters, 0, 38)) ?: '照片日常 #' . $choice['id']) ?></option><?php endforeach; ?></select></label><p class="field-hint">可以关联已有的文字和照片日常。先保存清单，再去写日常，也可以回来补上。</p><p id="task-error" role="alert"></p><div class="date-form-actions"><button type="submit" class="primary-button">保存这件事</button><a class="text-link" href="/list.php">取消</a></div></form></section>
<?php endif; ?>
<?php $doneCount = count(array_filter($tasks, function ($item) { return $item['completed']; })); ?>
<p class="task-summary">一起记下 <?= count($tasks) ?> 件事，已经完成 <?= $doneCount ?> 件 ♡</p>
<?php foreach ($tasks as $item): ?><article class="panel task-card <?= $item['completed'] ? 'task-completed' : '' ?>"><header><h2><?= c_h($item['title']) ?></h2><span class="visibility-tag"><?= $item['completed'] ? '已完成' : '想一起做' ?></span></header>
<p class="field-hint"><?= $item['due_date'] ? '计划日期：' . c_h($item['due_date']) . '　' : '' ?><?= $item['completed'] ? ($item['completed_at'] ? '完成于 ' . c_h($item['completed_at']) : '已完成，日期待补充') : '慢慢来，一起就好。' ?></p>
<?php if ($item['note'] !== ''): ?><p class="daily-body"><?= c_h($item['note']) ?></p><?php endif; ?>
<?php if (!empty($item['legacyImage'])): ?><div class="daily-grid count-1"><button type="button" class="image-button" data-display="<?= c_h($item['legacyImage']) ?>" aria-label="查看旧清单配图"><img loading="lazy" src="<?= c_h($item['legacyImage']) ?>" alt="<?= c_h($item['title']) ?>"></button></div><p class="field-hint">旧配图沿用原链接，链接本身可能仍然公开。</p><?php endif; ?>
<?php if ($item['moment_id']): try { $memory = c_post($item['moment_id'], $user); ?><a class="text-link" href="/moments.php?id=<?= (int) $memory['id'] ?>">看看这一天的回忆 →</a><?php } catch (CoupleError $ignored) { ?><p class="muted">关联的日常暂不可见，可以重新选择。</p><?php } endif; ?>
<footer class="daily-actions"><button type="button" class="text-link" data-task-complete="<?= c_h($item['key']) ?>" data-revision="<?= (int) $item['revision'] ?>" data-completed="<?= $item['completed'] ? '1' : '0' ?>"><?= $item['completed'] ? '重新放回计划' : '♡ 今天一起完成了' ?></button><a class="text-link" href="/list.php?edit=<?= c_h($item['key']) ?>">编辑与关联回忆</a></footer></article><?php endforeach; ?>
<?php if (!$tasks): ?><section class="empty-state"><h2>从一件想一起做的小事开始</h2><p>散步、看电影、旅行，都可以慢慢记下来。</p></section><?php endif; ?>
<?php endif; c_view_end($user); ?>
