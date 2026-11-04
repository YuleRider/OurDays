-- OurDays fresh installation structures. No personal data.
CREATE TABLE IF NOT EXISTS `about` (
  `id` int(11) NOT NULL,
  `title` varchar(30) NOT NULL COMMENT '标题',
  `aboutimg` varchar(100) NOT NULL COMMENT '背景图片',
  `info1` varchar(50) NOT NULL COMMENT '对话1',
  `info2` varchar(50) NOT NULL COMMENT '对话2',
  `info3` varchar(50) NOT NULL COMMENT '对话3',
  `btn1` varchar(30) NOT NULL COMMENT '按钮确定',
  `btn2` varchar(30) NOT NULL COMMENT '按钮取消',
  `infox1` varchar(30) NOT NULL COMMENT 'x2',
  `infox2` varchar(30) NOT NULL COMMENT 'x2',
  `infox3` varchar(30) NOT NULL COMMENT 'x2',
  `infox4` varchar(30) NOT NULL COMMENT 'x2',
  `infox5` varchar(30) NOT NULL COMMENT 'x2',
  `infox6` varchar(30) NOT NULL COMMENT 'x2',
  `btnx2` varchar(30) NOT NULL COMMENT 'btnx2',
  `infof1` varchar(30) NOT NULL COMMENT 'f3',
  `infof2` varchar(30) NOT NULL COMMENT 'f3',
  `infof3` varchar(30) NOT NULL COMMENT 'f3',
  `infof4` varchar(30) NOT NULL COMMENT 'f3',
  `btnf3` varchar(30) NOT NULL COMMENT 'btnf3',
  `infod1` varchar(30) NOT NULL COMMENT 'd4',
  `infod2` varchar(30) NOT NULL COMMENT 'd4',
  `infod3` varchar(30) NOT NULL COMMENT 'd4',
  `infod4` varchar(30) NOT NULL COMMENT 'd4',
  `infod5` varchar(30) NOT NULL COMMENT 'd4'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `article` (
  `id` int(11) NOT NULL,
  `articletext` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `articletime` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `articletitle` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `articlename` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `diyset` (
  `id` int(11) NOT NULL,
  `Blurkg` varchar(1) NOT NULL COMMENT '高斯模糊开关',
  `Pjaxkg` varchar(1) NOT NULL COMMENT 'pjax开关',
  `musickg` varchar(1) NOT NULL COMMENT '音乐播放器开关',
  `live2dkg` varchar(1) NOT NULL,
  `headCon` text NOT NULL,
  `cssCon` text NOT NULL,
  `footerCon` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `gifts` (
  `id` int(11) NOT NULL,
  `gift_name` varchar(255) NOT NULL COMMENT '礼物名称',
  `gift_description` text NOT NULL COMMENT '礼物描述',
  `gift_from` varchar(255) NOT NULL COMMENT '赠送人',
  `gift_price` decimal(10,2) NOT NULL COMMENT '礼物价格',
  `imgUrl` varchar(255) NOT NULL COMMENT '礼物图片URL',
  `gift_time` datetime DEFAULT NULL COMMENT '赠送时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='礼物墙';

CREATE TABLE IF NOT EXISTS `iperror` (
  `id` int(11) NOT NULL,
  `ipAdd` varchar(100) NOT NULL COMMENT 'ip归属地',
  `Time` varchar(200) NOT NULL COMMENT '时间',
  `State` text NOT NULL COMMENT '拉黑ip',
  `text` varchar(100) NOT NULL COMMENT '备注'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `leaving` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户名字',
  `QQ` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'QQ号码',
  `text` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '留言内容',
  `time` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ip记录',
  `city` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '省/城市'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leavset` (
  `id` int(11) NOT NULL,
  `jiequ` varchar(10) NOT NULL COMMENT '截取长度',
  `lanjie` varchar(500) NOT NULL COMMENT '违禁符号',
  `lanjiezf` varchar(500) NOT NULL COMMENT '违禁词'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `login` (
  `id` int(11) NOT NULL,
  `user` varchar(100) NOT NULL COMMENT '登录用户名',
  `username` varchar(100) NOT NULL,
  `userQQ` char(32) NOT NULL,
  `pw` varchar(255) NOT NULL COMMENT '登录密码'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `loveimg` (
  `id` int(11) NOT NULL,
  `imgDatd` varchar(100) NOT NULL COMMENT '日期',
  `imgText` varchar(200) NOT NULL COMMENT '描述',
  `imgUrl` varchar(200) NOT NULL COMMENT '外链'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `lovelist` (
  `id` int(11) NOT NULL,
  `icon` int(1) NOT NULL COMMENT '是否完成',
  `eventname` varchar(200) CHARACTER SET utf8mb4 NOT NULL COMMENT '事件内容',
  `imgurl` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '图片地址'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `picset` (
  `id` int(11) NOT NULL,
  `name` varchar(40) NOT NULL,
  `api` varchar(100) NOT NULL,
  `accessKey` varchar(150) NOT NULL,
  `secretKey` varchar(150) NOT NULL,
  `album_id` int(11) NOT NULL,
  `type` varchar(10) NOT NULL,
  `localpath` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `picture` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT '日期',
  `url` varchar(200) NOT NULL COMMENT '描述',
  `date` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `text` (
  `id` int(11) NOT NULL,
  `boy` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '男name',
  `girl` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '女name',
  `title` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '网站标题',
  `logo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '网站logo',
  `writing` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '网站文案',
  `boyQQ` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '男生QQ',
  `girlQQ` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '女生QQ',
  `customavatar` int(1) NOT NULL,
  `boyimg` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '男生QQ头像',
  `girlimg` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '女生QQ头像',
  `startTime` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '开始时间',
  `icp` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '网站备案号',
  `Copyright` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '网站版权',
  `card1` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `card2` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `card3` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deci1` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deci2` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deci3` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bgimg` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '首页背景图片地址',
  `userQQ` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '站长QQ',
  `userName` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'userName',
  `Animation` int(1) NOT NULL COMMENT '动画开关'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `warning` (
  `id` int(11) NOT NULL,
  `ip` varchar(50) NOT NULL COMMENT 'ip地址',
  `gsd` varchar(50) NOT NULL COMMENT '归属地',
  `time` varchar(80) NOT NULL COMMENT '时间',
  `file` varchar(100) NOT NULL COMMENT '路径'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `article`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `diyset`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `gifts`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `iperror`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `leaving`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `leavset`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `login`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `loveimg`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `lovelist`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `picset`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `picture`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `text`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `warning`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `article`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;

ALTER TABLE `diyset`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;

ALTER TABLE `gifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;

ALTER TABLE `iperror`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `leaving`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;

ALTER TABLE `leavset`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;

ALTER TABLE `login`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=3;

ALTER TABLE `loveimg`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;

ALTER TABLE `lovelist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;

ALTER TABLE `picset`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;

ALTER TABLE `picture`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `text`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;

ALTER TABLE `warning`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
CREATE TABLE IF NOT EXISTS couple_moments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 author_id INT NOT NULL, body TEXT NOT NULL, visibility ENUM('private','public') NOT NULL DEFAULT 'private',
 request_key CHAR(32) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME NULL,
 UNIQUE KEY author_request(author_id,request_key), KEY feed(deleted_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS couple_images (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, owner_id INT NOT NULL, moment_id BIGINT UNSIGNED NULL, request_key CHAR(32) NOT NULL,
 original_file VARCHAR(64) NOT NULL, display_file VARCHAR(64) NOT NULL, original_mime VARCHAR(40) NOT NULL,
 original_name VARCHAR(200) NOT NULL, width INT NOT NULL, height INT NOT NULL, sort_order INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL,
 UNIQUE KEY original_file(original_file), UNIQUE KEY upload_once(owner_id,request_key), KEY post_images(moment_id,sort_order), KEY staged(owner_id,moment_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS couple_likes (
 moment_id BIGINT UNSIGNED NOT NULL, user_id INT NOT NULL, created_at DATETIME NOT NULL,
 PRIMARY KEY(moment_id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS couple_replies (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, moment_id BIGINT UNSIGNED NOT NULL, user_id INT NOT NULL,
 body TEXT NOT NULL, request_key CHAR(32) NOT NULL, created_at DATETIME NOT NULL,
 UNIQUE KEY author_request(user_id,request_key), KEY thread(moment_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS couple_notifications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, recipient_id INT NOT NULL, actor_id INT NOT NULL,
 moment_id BIGINT UNSIGNED NOT NULL, kind ENUM('like','reply') NOT NULL, source_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL, read_at DATETIME NULL,
 UNIQUE KEY event_once(recipient_id,actor_id,kind,source_id), KEY inbox(recipient_id,read_at,id), KEY post_messages(moment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS couple_dates (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, title VARCHAR(160) NOT NULL,
 kind ENUM('anniversary','birthday') NOT NULL, event_date DATE NOT NULL,
 visibility ENUM('private','public') NOT NULL DEFAULT 'private',
 creator_id INT NOT NULL, request_key CHAR(32) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
 UNIQUE KEY create_once(creator_id,request_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS couple_tasks (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, legacy_id INT NULL,
 title VARCHAR(400) NOT NULL, revision INT UNSIGNED NOT NULL DEFAULT 1, due_date DATE NULL, completed_at DATE NULL, note TEXT NOT NULL,
 moment_id BIGINT UNSIGNED NULL, creator_id INT NOT NULL, request_key CHAR(32) NOT NULL,
 created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
 UNIQUE KEY legacy_item(legacy_id), UNIQUE KEY create_once(creator_id,request_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS couple_drafts (
 user_id INT NOT NULL, context_id BIGINT UNSIGNED NOT NULL DEFAULT 0, body TEXT NOT NULL,
 visibility ENUM('private','public') NOT NULL DEFAULT 'private', image_ids TEXT NOT NULL,
 publish_key CHAR(32) NOT NULL, revision INT UNSIGNED NOT NULL, updated_at DATETIME NOT NULL,
 PRIMARY KEY(user_id,context_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS legacy_visibility (
 kind VARCHAR(16) NOT NULL,
 record_id INT UNSIGNED NOT NULL,
 visibility VARCHAR(8) NOT NULL,
 PRIMARY KEY(kind,record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS legacy_media (
 token CHAR(64) NOT NULL PRIMARY KEY,
 source_path VARCHAR(1024) NOT NULL,
 filename VARCHAR(80) NOT NULL,
 mime VARCHAR(40) NOT NULL,
 sha256 CHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
