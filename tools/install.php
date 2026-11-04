<?php
if (PHP_SAPI !== 'cli') exit('CLI only');
umask(0077);
require_once __DIR__ . '/../public/admin/CoupleStories.php';
try {
    if (PHP_VERSION_ID < 70400) throw new RuntimeException('PHP 7.4 or newer is required');
    foreach (array('mysqli', 'gd', 'exif', 'curl') as $extension) if (!extension_loaded($extension)) throw new RuntimeException('Missing PHP extension: ' . $extension);
    $gd = gd_info();
    if (empty($gd['JPEG Support']) || empty($gd['PNG Support']) || empty($gd['WebP Support'])) throw new RuntimeException('GD must support JPEG, PNG and WebP');
    $db = c_db();
    if (c_all('SHOW TABLES')) throw new RuntimeException('Database is not empty. Installation stopped without changing it.');
    $runtime = likegirl_runtime_dir();
    foreach (array('', '/couple-media/originals', '/couple-media/displays', '/couple-media/legacy') as $suffix) {
        $directory = $runtime . $suffix;
        if (is_link($directory) || (!is_dir($directory) && !mkdir($directory, 0700, true))) throw new RuntimeException('Cannot create private runtime directory');
    }
    $lock = fopen($runtime . '/install.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Another installation is running');
    if (c_all('SHOW TABLES')) throw new RuntimeException('Database is no longer empty');
    foreach (explode(';', file_get_contents(__DIR__ . '/../database/schema.sql')) as $sql) if (trim($sql) !== '') c_q($sql);
    c_memory_migrate(); c_stories_migrate();
    $config = ourdays_config();
    if (empty($config['security_code'])) {
        $config['security_code'] = bin2hex(random_bytes(16));
        if (file_put_contents(__DIR__ . '/../config/app.php', "<?php\nreturn " . var_export($config, true) . ";\n") === false) throw new RuntimeException('Cannot save generated security code');
    }
    function seed_settings($table, $values)
    {
        $record = array();
        foreach (c_all('SHOW COLUMNS FROM `' . $table . '`') as $column) {
            if (strpos($column['Extra'], 'auto_increment') !== false) continue;
            $name = $column['Field'];
            $record[$name] = array_key_exists($name, $values) ? $values[$name] : (preg_match('/int|decimal|float|double/i', $column['Type']) ? 0 : '');
        }
        $columns = '`' . implode('`,`', array_keys($record)) . '`';
        c_q('INSERT INTO `' . $table . '` (' . $columns . ') VALUES(' . implode(',', array_fill(0, count($record), '?')) . ')', str_repeat('s', count($record)), array_values($record));
    }
    seed_settings('text', array('id'=>1,'boy'=>'甲','girl'=>'乙','title'=>'OurDays／我们的日常','logo'=>'OurDays','writing'=>'把普通的一天，写成我们的故事。','customavatar'=>1,'boyimg'=>'/Style/img/avatar-a.svg','girlimg'=>'/Style/img/avatar-b.svg','startTime'=>c_today().'T00:00','Copyright'=>'OurDays · YuleRider','card1'=>'点点滴滴','card2'=>'祝福留言','card3'=>'关于我们','deci1'=>'一起记住的片段','deci2'=>'在这里写下祝福','deci3'=>'我们的故事','bgimg'=>'/Style/img/ourdays-cover.svg','userName'=>'OurDays','Animation'=>0));
    seed_settings('diyset', array('id'=>1,'Blurkg'=>'2'));
    seed_settings('leavset', array('id'=>1,'jiequ'=>'15','lanjiezf'=>'\x00'));
    seed_settings('picset', array('id'=>1,'type'=>'0','localpath'=>'files'));
    seed_settings('about', array('id'=>1,'title'=>'OurDays','aboutimg'=>'/Style/img/ourdays-cover.svg','info1'=>'欢迎来到我们的日常','info2'=>'记录和回忆，都留在这里。','btn1'=>'你好','btn2'=>'回首页'));
    $credentials = array();
    foreach (array('couple_a'=>'甲','couple_b'=>'乙') as $account=>$name) {
        $password = bin2hex(random_bytes(12));
        c_q('INSERT INTO login(user,username,userQQ,pw) VALUES(?,?,?,?)','ssss',array($account,$name,'',password_hash($password,PASSWORD_DEFAULT)));
        $credentials[] = $account . ': ' . $password;
    }
    echo "INSTALLED OurDays. Keep these generated credentials private:\n" . implode("\n", $credentials) . "\nSecurity code: " . $config['security_code'] . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'STOPPED: ' . $error->getMessage() . "\n"); exit(1);
}
