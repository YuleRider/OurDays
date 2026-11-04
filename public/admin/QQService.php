<?php

function likegirl_qq_failure()
{
    return array('code' => 500, 'msg' => '自动获取暂不可用，请手动填写昵称');
}

function likegirl_write_qq_state($path, $state)
{
    $json = json_encode($state, JSON_UNESCAPED_UNICODE);
    $temporary = $path . '.tmp';
    if ($json === false || @file_put_contents($temporary, $json) !== strlen($json)) {
        return false;
    }
    @chmod($temporary, 0600);
    return @rename($temporary, $path);
}

function likegirl_fetch_qq($qq, $key)
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $url = 'https://tqapi.com/api/qqinfo/info?' . http_build_query(array('key' => $key, 'qq' => $qq));
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $status == 200 && is_string($body) ? json_decode($body, true) : null;
}

function likegirl_qq_lookup($qq, $ip, $key, $directory, $fetch = null, $now = null)
{
    $failure = likegirl_qq_failure();
    if (!is_string($qq) || !preg_match('/^[1-9][0-9]{5,9}$/', $qq)
        || !is_string($ip) || !filter_var($ip, FILTER_VALIDATE_IP)
        || !is_string($key) || $key === '' || !is_dir($directory)) {
        return $failure;
    }
    $statePath = $directory . '/qq-state.json';
    $file = @fopen($directory . '/qq-state.lock', 'c');
    if (!$file) {
        error_log('LikeGirl QQ state is not writable');
        return $failure;
    }
    // ponytail: one non-blocking lock serializes upstream calls; use per-QQ locks if traffic grows.
    if (!flock($file, LOCK_EX | LOCK_NB)) {
        fclose($file);
        return $failure;
    }
    try {
        $now = $now === null ? time() : $now;
        $day = gmdate('Y-m-d', $now + 8 * 3600);
        $minute = (int) floor($now / 60);
        $raw = is_file($statePath) ? @file_get_contents($statePath) : null;
        if ($raw === null) {
            $state = array('day' => $day, 'calls' => 0, 'ips' => array(), 'cache' => array());
        } else {
            $state = json_decode($raw, true);
            if (!is_array($state) || !isset($state['day'], $state['calls'], $state['ips'], $state['cache'])
                || !is_string($state['day']) || !is_int($state['calls']) || $state['calls'] < 0
                || !is_array($state['ips']) || !is_array($state['cache'])) {
                error_log('LikeGirl QQ state is invalid; upstream requests stopped');
                return $failure;
            }
        }
        if ($state['day'] !== $day) {
            $state['day'] = $day;
            $state['calls'] = 0;
        }
        foreach ($state['ips'] as $id => $entry) {
            if (!isset($entry['minute'], $entry['count']) || $entry['minute'] !== $minute) {
                unset($state['ips'][$id]);
            }
        }
        foreach ($state['cache'] as $id => $entry) {
            if (!isset($entry['expires'], $entry['name']) || !is_string($entry['name'])
                || $entry['expires'] <= $now) {
                unset($state['cache'][$id]);
            }
        }
        $ipId = hash('sha256', $ip);
        $count = isset($state['ips'][$ipId]) ? $state['ips'][$ipId]['count'] : 0;
        if ($count >= 10) {
            return $failure;
        }
        $state['ips'][$ipId] = array('minute' => $minute, 'count' => $count + 1);
        if (isset($state['cache'][$qq])) {
            $name = $state['cache'][$qq]['name'];
        } else {
            if ($state['calls'] >= 80) {
                likegirl_write_qq_state($statePath, $state);
                return $failure;
            }
            $state['calls']++;
            // Persist the reservation before requesting upstream, including failures and timeouts.
            if (!likegirl_write_qq_state($statePath, $state)) {
                return $failure;
            }
            $fetch = $fetch === null ? 'likegirl_fetch_qq' : $fetch;
            try {
                $result = call_user_func($fetch, $qq, $key);
            } catch (Throwable $error) {
                return $failure;
            }
            if (!is_array($result) || !isset($result['code'], $result['data']['name'], $result['data']['qq'])
                || $result['code'] != 200 || (string) $result['data']['qq'] !== $qq
                || !is_string($result['data']['name']) || trim($result['data']['name']) === '') {
                return $failure;
            }
            $name = $result['data']['name'];
            $state['cache'][$qq] = array('expires' => $now + 86400, 'name' => $name);
        }
        if (!likegirl_write_qq_state($statePath, $state)) {
            return $failure;
        }
        return array('code' => 200, 'data' => array(
            'name' => $name,
            'imgurl' => 'https://q1.qlogo.cn/g?b=qq&nk=' . rawurlencode($qq) . '&s=100'
        ));
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}
