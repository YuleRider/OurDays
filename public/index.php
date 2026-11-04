<?php
include_once 'head.php';
$coupleUser = c_user();
require_once __DIR__ . '/admin/CoupleView.php';
?>

<head>
    <meta charset="utf-8" />
    <title><?php echo $text['title'] ?></title>
    <link rel="stylesheet" href="Style/css/index.css?LikeGirl=<?php echo $version ?>">
</head>

<body class="couple-home" data-couple-user="<?= $coupleUser ? (int) $coupleUser['id'] : '' ?>">
    <div id="pjax-container">
        <!-- 时间区域 -->
        <div class="time">
            <span id="span_dt_dt">这是我们一起走过的</span>
            <b id="tian">00天</b>
            <b id="shi">00时</b>
            <b id="fen">00分</b>
            <b id="miao">00秒</b>
        </div>
        <div class="couple-home-entry central central-800">
            <a data-no-pjax class="couple-home-link" href="/moments.php">我们的日常 <span aria-hidden="true">♡</span></a>
            <?php if ($coupleUser): ?><a data-no-pjax class="couple-home-link" href="/moments.php?view=write">写一条日常 ↗</a><?php else: ?><a data-no-pjax class="couple-home-link" href="/moments.php?view=me">进入我们的小世界</a><?php endif; ?>
        </div>
        <!-- 卡片区域 -->
        <?php c_dates_block($coupleUser); ?>
        <div class="card-wrap">
            <div class="row central">
                <div
                    class="card col-lg-4 col-sm-12 col-sm-x-12 flex-h <?php if ($text['Animation'] == "1") {
                        echo 'animated fadeInUp';
                    } ?>">
                    <img src="Style/img/card/likegirl_card_1.svg">
                    <div class="text">
                        <span><a target="_self" href="little.php"><?php echo $text['card1'] ?></a></span>
                        <p><?php echo $text['deci1'] ?></p>
                    </div>
                </div>
                
                <div
                    class="card col-lg-4 col-sm-12 col-sm-x-12 flex-h <?php if ($text['Animation'] == "1") {
                        echo 'animated fadeInUp';
                    } ?>">
                    <img src="Style/img/card/likegirl_card_2.svg" alt="">
                    <div class="text">
                        <span><a target="_self" href="leaving.php"><?php echo $text['card2'] ?></a></span>
                        <p><?php echo $text['deci2'] ?></p>
                    </div>
                </div>
                
                <div
                    class="card col-lg-4 col-sm-12 col-sm-x-12 flex-h <?php if ($text['Animation'] == "1") {
                        echo 'animated fadeInUp';
                    } ?>">
                    <img src="Style/img/card/likegirl_card_3.svg" alt="">
                    <div class="text">
                        <span><a target="_self" href="about.php"><?php echo $text['card3'] ?></a></span>
                        <p><?php echo $text['deci3'] ?></p>
                    </div>
                </div>
                
                <div
                    class="card col-lg-4 col-sm-12 col-sm-x-12 flex-h <?php if ($text['Animation'] == "1") {
                        echo 'animated fadeInUp';
                    } ?>">
                    <img src="Style/img/card/likegirl_card_4.svg" alt="">
                    <div class="text">
                        <span><a target="_self" href="loveImg.php">恋爱相册</a></span>
                        <p>记录最美瞬间</p>
                    </div>
                </div>
                
                <div
                    class="card col-lg-4 col-sm-12 col-sm-x-12 flex-h <?php if ($text['Animation'] == "1") {
                        echo 'animated fadeInUp';
                    } ?>">
                    <img src="Style/img/card/likegirl_card_5.svg" alt="">
                    <div class="text">
                        <span><a target="_self" href="list.php">恋爱列表</a></span>
                        <p>你我之间的约定</p>
                    </div>
                </div>
                
                <div
                    class="card col-lg-4 col-sm-12 col-sm-x-12 flex-h <?php if ($text['Animation'] == "1") {
                        echo 'animated fadeInUp';
                    } ?>">
                    <img src="Style/img/card/likegirl_card_6.svg" alt="">
                    <div class="text">
                        <span><a target="_self" href="gifts.php">礼物墙</a></span>
                        <p>从玩偶到戒指</p>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($coupleUser && !is_file(likegirl_runtime_dir() . '/stories-disabled')): ?><nav class="story-home-links central central-800" aria-label="我们的回忆"><a data-no-pjax href="/moments.php?view=stories">我们的故事</a><span>·</span><a data-no-pjax href="/moments.php?view=recap">本月回顾</a></nav><?php endif; ?>
        <?php c_memory_home($coupleUser); ?>
        <section class="couple-feature central central-800">
            <header class="section-heading"><div><h2>最近的日常</h2><p>一点一滴，慢慢记录。</p></div><a data-no-pjax class="text-link" href="/moments.php">全部日常 →</a></header>
            <?php $recent = c_feed($coupleUser, 0, 3, false, array('order' => 'published')); foreach ($recent['items'] as $post) c_card($post, $coupleUser); ?>
            <?php if (!$recent['items']): ?><div class="empty-state"><h3>把今天的一点小事留下来</h3><p>想说的话、想留住的照片，都可以慢慢写。</p><a data-no-pjax class="text-link" href="/moments.php<?= $coupleUser ? '?view=write' : '' ?>"><?= $coupleUser ? '写第一条日常' : '看看我们的日常' ?> →</a></div><?php endif; ?>
        </section>
    </div>

    <?php include_once 'footer.php';?>

</body>

</html>
