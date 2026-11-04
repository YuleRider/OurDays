<?php
if (PHP_SAPI !== 'cli') exit('CLI only');
umask(0077);
require_once __DIR__ . '/../public/admin/CoupleStories.php';
function tool_run($arguments, $destination = null, $input = null)
{
    if (!function_exists('exec')) throw new RuntimeException('CLI exec is disabled; use tools/run-maintenance.sh');
    $error = tempnam(likegirl_runtime_dir(), 'command-');
    try {
        $command = implode(' ', array_map('escapeshellarg', $arguments));
        if ($destination !== null) $command .= ' > ' . escapeshellarg($destination);
        if ($input !== null) $command .= ' < ' . escapeshellarg($input);
        $command .= ' 2> ' . escapeshellarg($error);
        exec($command, $lines, $status);
        if ($status !== 0 || trim(file_get_contents($error)) !== '') throw new RuntimeException('Command failed: ' . basename($arguments[0]) . '. Check installed tools and database permissions.');
        return implode("\n", $lines);
    } finally { if (is_file($error)) unlink($error); }
}
function backup_check($directory)
{
    if (!is_file($directory . '/checksums.json') || is_link($directory)) throw new RuntimeException('Backup manifest not found');
    $checksums = json_decode(file_get_contents($directory . '/checksums.json'), true);
    if (!is_array($checksums) || array_keys($checksums) !== array('database.sql', 'site.tar.gz', 'couple-media.tar.gz')) throw new RuntimeException('Invalid backup manifest');
    foreach ($checksums as $file=>$hash) if (!preg_match('/^[a-f0-9]{64}$/', $hash) || !is_file($directory . '/' . $file) || !hash_equals($hash, hash_file('sha256', $directory . '/' . $file))) throw new RuntimeException('Backup checksum mismatch');
}
try {
    $runtime = likegirl_runtime_dir(); $root = dirname(__DIR__); $backups = $root . '/backups';
    if (is_link($backups) || (!is_dir($backups) && !mkdir($backups, 0700))) throw new RuntimeException('Cannot create private backup directory');
    $lock = fopen($runtime . '/maintenance.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Another maintenance job is running');
    $config = ourdays_config(); $db = $config['database'];
    if (!preg_match('/^[A-Za-z0-9_]+$/', $db['name'])) throw new RuntimeException('Unsupported database name');
    $tools = $config['tools'] ?? array();
    $mysql = $tools['mysql'] ?? 'mysql'; $dump = $tools['mysqldump'] ?? 'mysqldump'; $tar = $tools['tar'] ?? 'tar';
    $action = $argv[1] ?? '';
    if ($action === 'backup') {
        $directory = $backups . '/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
        if (!mkdir($directory, 0700)) throw new RuntimeException('Cannot create backup');
        $client = $directory . '/client.cnf';
        $quote = function($value) { return '"' . addcslashes((string)$value,"\\\"\n\r\t") . '"'; };
        file_put_contents($client,"[client]\nhost=".$quote($db['host'])."\nport=".(int)$db['port']."\nuser=".$quote($db['user'])."\npassword=".$quote($db['password'])."\n");
        try {
            tool_run(array($dump,'--defaults-extra-file='.$client,'--default-character-set=utf8mb4','--no-tablespaces','--single-transaction','--quick','--skip-lock-tables',$db['name']),$directory.'/database.sql');
            tool_run(array($tar,'-czf',$directory.'/site.tar.gz','--exclude=./backups','--exclude=./var','--exclude=./.git','-C',$root,'.'));
            tool_run(array($tar,'-czf',$directory.'/couple-media.tar.gz','-C',$runtime.'/couple-media','.'));
        } finally { if (is_file($client)) unlink($client); }
        $checksums = array();
        foreach(array('database.sql','site.tar.gz','couple-media.tar.gz') as $file)$checksums[$file]=hash_file('sha256',$directory.'/'.$file);
        file_put_contents($directory.'/checksums.json',json_encode($checksums,JSON_PRETTY_PRINT));backup_check($directory);
        echo "BACKUP OK: $directory\nKeep this directory private: it contains configuration and private content.\n";
    } elseif ($action === 'restore-check') {
        $directory = realpath($argv[2] ?? ''); $admin = realpath($argv[3] ?? '');
        if (!$directory || dirname($directory) !== realpath($backups) || !preg_match('/^[0-9]{8}-[0-9]{6}-[a-f0-9]{6}$/', basename($directory))) throw new RuntimeException('Choose an existing OurDays backup directory');
        if (!$admin || !is_file($admin)) throw new RuntimeException('Provide a private MySQL administrator client.cnf path as the fourth argument');
        backup_check($directory);$temporary=$directory.'/restore-check-'.bin2hex(random_bytes(4));mkdir($temporary,0700);
        $database='ourdays_restore_'.bin2hex(random_bytes(6));$created=false;
        try {
            tool_run(array($mysql,'--defaults-extra-file='.$admin,'-e','CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4'));$created=true;
            tool_run(array($mysql,'--defaults-extra-file='.$admin,$database),null,$directory.'/database.sql');
            tool_run(array($tar,'-xzf',$directory.'/site.tar.gz','-C',$temporary));
            if(!is_file($temporary.'/public/moments.php')||!is_file($temporary.'/config/app.php'))throw new RuntimeException('Source/configuration recovery failed');
            mkdir($temporary.'/private-media',0700);tool_run(array($tar,'-xzf',$directory.'/couple-media.tar.gz','-C',$temporary.'/private-media'));
            $rows=trim(tool_run(array($mysql,'--defaults-extra-file='.$admin,'-N','-e','SELECT original_file,display_file FROM `'.$database.'`.couple_images')));
            $count=0;
            foreach($rows===''?array():explode("\n",$rows) as $row){$names=explode("\t",trim($row));if(count($names)!==2)throw new RuntimeException('Invalid recovered photo metadata');foreach(array('originals','displays') as $n=>$folder)if(!preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/',$names[$n])||!is_file($temporary.'/private-media/'.$folder.'/'.$names[$n]))throw new RuntimeException('Recovered image missing');$count++;}
            echo "RESTORE CHECK PASSED: source, configuration, database and $count photo pairs recovered; live database unchanged.\n";
        } finally {
            if($created)tool_run(array($mysql,'--defaults-extra-file='.$admin,'-e','DROP DATABASE `'.$database.'`'));
            $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
            foreach($files as $file){if($file->isDir()&&!$file->isLink())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($temporary);
        }
    } elseif ($action === 'cleanup') {
        c_memory_cleanup();
        c_q('DELETE s FROM couple_story_moments s LEFT JOIN couple_moments m ON m.id=s.moment_id WHERE m.id IS NULL');
        echo "CLEANUP OK\n";
    } else throw new RuntimeException('Usage: backup | restore-check BACKUP ADMIN_CNF | cleanup');
} catch (Throwable $error) { fwrite(STDERR,'STOPPED: '.$error->getMessage()."\n");exit(1); }
