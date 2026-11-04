<?php
require_once __DIR__ . '/Couple.php';
// Legacy templates expect their database configuration in the including page's scope.
if (PHP_SAPI !== 'cli') require_once __DIR__ . '/connect.php';

function l_table($kind)
{
    $tables = array('album' => 'loveimg', 'article' => 'article', 'gift' => 'gifts', 'message' => 'leaving');
    if (!isset($tables[$kind])) throw new CoupleError('内容类型不正确');
    return $tables[$kind];
}
function l_start()
{
    c_init();
    if (is_file(likegirl_runtime_dir() . '/legacy-upgrading')) { http_response_code(503); exit('网站内容正在更新，请稍后再试。'); }
    try { c_one('SELECT kind FROM legacy_visibility LIMIT 1'); }
    catch (Throwable $error) { http_response_code(503); exit('可见范围功能正在更新，请稍后再试。'); }
}
function l_filter($kind, $alias = '')
{
    l_table($kind);
    if (c_user()) return '1=1';
    $column = $alias ? $alias . '.id' : l_table($kind) . '.id';
    return "NOT EXISTS (SELECT 1 FROM legacy_visibility v WHERE v.kind='" . $kind . "' AND v.record_id=" . $column . " AND v.visibility='private')";
}
function l_visibility($kind, $id)
{
    l_table($kind);
    $row = c_one('SELECT visibility FROM legacy_visibility WHERE kind=? AND record_id=?', 'si', array($kind, $id));
    return $row ? $row['visibility'] : 'public';
}
function l_set_visibility($kind, $id, $visibility)
{
    l_table($kind); $visibility = c_visibility($visibility);
    c_q('INSERT INTO legacy_visibility(kind,record_id,visibility) VALUES(?,?,?) ON DUPLICATE KEY UPDATE visibility=VALUES(visibility)', 'sis', array($kind, $id, $visibility));
}
function l_record($kind, $id)
{
    $row = c_one('SELECT * FROM ' . l_table($kind) . ' WHERE id=?', 'i', array(c_id($id)));
    if (!$row) throw new CoupleError('内容不存在', 404);
    return $row;
}
function l_field($kind, $id = 0)
{
    $visibility = $id ? l_visibility($kind, $id) : 'private';
    echo '<div class="form-group mb-3"><label for="legacy-visibility">可见范围</label><select id="legacy-visibility" style="min-height:44px" class="form-control" name="visibility"><option value="private"' . ($visibility === 'private' ? ' selected' : '') . '>仅我们可见</option><option value="public"' . ($visibility === 'public' ? ' selected' : '') . '>公开</option></select><small class="form-text text-muted" style="white-space:normal;overflow-wrap:anywhere;line-height:1.7">仅我们可见：双方登录后可看。外部图床原链接仍可能公开；同一图片若用于其他公开内容，也仍可从公开内容查看。</small></div><input type="hidden" name="legacy_csrf" value="' . c_h(c_csrf()) . '">';
}
function l_control($kind, $id)
{
    $visibility = l_visibility($kind, $id);
    echo '<div class="legacy-visibility-control" data-kind="' . c_h($kind) . '" data-id="' . (int) $id . '"><select class="form-control form-control-sm" style="min-height:44px;min-width:115px" aria-label="可见范围"><option value="private"' . ($visibility === 'private' ? ' selected' : '') . '>仅我们可见</option><option value="public"' . ($visibility === 'public' ? ' selected' : '') . '>公开</option></select><button type="button" class="btn btn-sm btn-outline-success mt-1" style="min-height:44px" data-legacy-save>保存范围</button><span role="status" class="d-block small"></span></div>';
}
function l_controls_script()
{
    echo '<script src="/admin/assets/js/legacy-privacy.js?v=1" defer data-csrf="' . c_h(c_csrf()) . '"></script>';
}
function l_local_url_path($url)
{
    $url = html_entity_decode(trim((string) $url), ENT_QUOTES, 'UTF-8');
    $parts = parse_url($url);
    if ($parts === false) return null;
    if (isset($parts['host']) && !in_array(strtolower($parts['host']), array('127.0.0.1', 'localhost', strtolower((string) parse_url(ourdays_config()['site_url'], PHP_URL_HOST))), true)) return null;
    $path = rawurldecode($parts['path'] ?? '');
    if ($path === '' || strpos($path, "\0") !== false || preg_match('~[\r\n]~', $path)) return null;
    $segments = array();
    foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') array_pop($segments); else $segments[] = $part;
    }
    $path = '/' . implode('/', $segments);
    return $path;
}
function l_local_path($url)
{
    $path = l_local_url_path($url);
    return $path && preg_match('~\.(?:jpe?g|png|gif|webp|bmp)$~i', $path) ? $path : null;
}
function l_urls($html)
{
    $html = html_entity_decode((string) $html, ENT_QUOTES, 'UTF-8');
    preg_match_all('~(?:src|poster)\s*=\s*["\']([^"\']+)["\']|!\[[^\]]*\]\(([^\s)]+)\)~i', $html, $matches, PREG_SET_ORDER);
    $urls = array();
    foreach ($matches as $match) $urls[] = !empty($match[1]) ? $match[1] : $match[2];
    preg_match_all('~url\(\s*["\']?([^"\')]+)["\']?\s*\)|(?:src|poster)\s*=\s*([^\s"\'>]+)~i', $html, $other, PREG_SET_ORDER);
    foreach ($other as $match) $urls[] = trim(!empty($match[1]) ? $match[1] : $match[2]);
    preg_match_all('~srcset\s*=\s*["\']([^"\']+)["\']~i', $html, $sets);
    foreach ($sets[1] as $set) foreach (explode(',', $set) as $candidate) $urls[] = preg_split('/\s+/', trim($candidate))[0];
    return array_unique($urls);
}
function l_media_dir()
{
    $directory = likegirl_runtime_dir() . '/couple-media/legacy';
    if (!is_dir($directory) || is_link($directory)) throw new CoupleError('图片保护目录尚未准备好', 503);
    return $directory;
}
function l_asset($url)
{
    $token = l_asset_token($url);
    return $token ? c_one('SELECT * FROM legacy_media WHERE token=?', 's', array($token)) : null;
}
function l_asset_token($url)
{
    $decoded = html_entity_decode((string) $url, ENT_QUOTES, 'UTF-8');
    if (preg_match('~^/legacy-image\.php\?asset=([a-f0-9]{64})$~', $decoded, $match)) return $match[1];
    $path = l_local_path($decoded);
    return $path ? hash('sha256', $path) : null;
}
function l_image_url($url)
{
    $asset = l_asset($url);
    return $asset ? '/legacy-image.php?asset=' . $asset['token'] : $url;
}
function l_image_html($html)
{
    foreach (l_urls($html) as $url) {
        $replacement = l_image_url($url);
        if ($replacement !== $url) $html = str_replace(array(c_h($url), $url), c_h($replacement), $html);
    }
    return $html;
}
function l_import_image($url)
{
    $lock = fopen(l_media_dir() . '/import.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Cannot lock legacy image import');
    try { l_import_image_locked($url); }
    finally { flock($lock, LOCK_UN); fclose($lock); }
}
function l_import_image_locked($url)
{
    $path = l_local_path($url);
    if (!$path) return;
    $token = hash('sha256', $path);
    $root = defined('LEGACY_SITE_ROOT') ? LEGACY_SITE_ROOT : dirname(__DIR__);
    $source = $root . $path;
    $asset = c_one('SELECT * FROM legacy_media WHERE token=?', 's', array($token));
    if ($asset) {
        // A replaced file at an old protected address must not silently become public.
        if (is_file($source)) throw new CoupleError('这个旧图片地址出现了新文件，请先通过上传按钮重新上传图片');
        return;
    }
    $real = realpath($source); $realRoot = realpath($root);
    if (!$real || !$realRoot || strpos(str_replace('\\', '/', $real), str_replace('\\', '/', $realRoot) . '/') !== 0 || is_link($source)) throw new CoupleError('本地图片不存在或路径不受支持，请重新上传图片');
    $info = @getimagesize($source);
    $extensions = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/bmp' => 'bmp');
    if (!$info || !isset($extensions[$info['mime']])) throw new CoupleError('本地文件不是受支持的图片');
    $filename = $token . '.' . $extensions[$info['mime']];
    $target = l_media_dir() . '/' . $filename;
    $hash = hash_file('sha256', $source);
    if (is_link($target) || (!is_file($target) && !copy($source, $target)) || !hash_equals($hash, hash_file('sha256', $target))) throw new RuntimeException('Cannot protect local image');
    chmod($target, 0600);
    c_q('INSERT INTO legacy_media(token,source_path,filename,mime,sha256) VALUES(?,?,?,?,?)', 'sssss', array($token, $path, $filename, $info['mime'], $hash));
    if (!unlink($source)) {
        c_q('DELETE FROM legacy_media WHERE token=?', 's', array($token));
        throw new CoupleError('无法保护本地原图片，请检查图片目录权限后重试', 503);
    }
}
function l_protect_record($kind, $row)
{
    $urls = $kind === 'article' ? l_urls($row['articletext']) : (isset($row['imgUrl']) ? array($row['imgUrl']) : array());
    foreach ($urls as $url) {
        $path = l_local_url_path($url);
        if ($path && !l_local_path($url) && !in_array($path, array('/legacy-image.php', '/moment-image.php'), true)) throw new CoupleError('本地图片格式不受支持，请通过上传按钮重新上传图片');
        l_import_image($url);
    }
}
function l_public_assets()
{
    $tokens = array();
    foreach (array('album', 'article', 'gift') as $kind) {
        $table = l_table($kind);
        $rows = c_all("SELECT t.* FROM " . $table . " t WHERE NOT EXISTS (SELECT 1 FROM legacy_visibility v WHERE v.kind=? AND v.record_id=t.id AND v.visibility='private')", 's', array($kind));
        foreach ($rows as $row) {
            $urls = $kind === 'article' ? l_urls($row['articletext']) : array($row['imgUrl']);
            foreach ($urls as $url) { $token = l_asset_token($url); if ($token) $tokens[$token] = true; }
        }
    }
    // A photo still intentionally used in the public homepage/about page remains public there.
    $publicFields = array();
    foreach (array('text', 'about') as $table) {
        foreach (c_all('SELECT * FROM ' . $table) as $row) foreach ($row as $value) if (is_string($value)) $publicFields[] = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    }
    foreach (c_all('SELECT token,source_path FROM legacy_media') as $asset) {
        $proxy = '/legacy-image.php?asset=' . $asset['token'];
        foreach ($publicFields as $field) if (($asset['source_path'] !== '' && strpos($field, $asset['source_path']) !== false) || strpos($field, $proxy) !== false) { $tokens[$asset['token']] = true; break; }
    }
    return $tokens;
}
function l_visibility_write($data)
{
    c_member(c_user()); c_verify_csrf($data['csrf'] ?? '');
    $kind = $data['kind'] ?? ''; $id = c_id($data['id'] ?? 0);
    $visibility = c_visibility($data['visibility'] ?? '');
    $row = l_record($kind, $id);
    if ($visibility === 'private') l_protect_record($kind, $row);
    l_set_visibility($kind, $id, $visibility);
}
function l_save($kind, $editing)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new CoupleError('请使用 POST', 405);
    c_member(c_user()); c_verify_csrf($_POST['csrf'] ?? ($_POST['legacy_csrf'] ?? ''));
    $visibility = c_visibility($_POST['visibility'] ?? 'private');
    $id = $editing ? c_id($_POST['id'] ?? 0) : 0;
    if ($editing) l_record($kind, $id);
    $definitions = array('album' => array('imgDatd', 'imgText', 'imgUrl'), 'article' => array('articletitle', 'articletext'), 'gift' => array('gift_name', 'gift_description', 'gift_from', 'gift_price', 'gift_time', 'imgUrl'));
    if (!isset($definitions[$kind])) throw new CoupleError('内容类型不正确');
    $values = array(); $fields = $definitions[$kind];
    foreach ($fields as $field) {
        $value = c_text($_POST[$field] ?? '', $field === 'articletext' ? 100000 : 2000);
        if ($value === '' && $field !== 'gift_price') throw new CoupleError('请填写完整内容');
        $values[$field] = $field === 'articletext' || $field === 'imgUrl' ? $value : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    if (isset($values['gift_price']) && (!is_numeric($values['gift_price']) || (float) $values['gift_price'] < 0)) throw new CoupleError('礼物价格不正确');
    l_protect_record($kind, $values);
    if (!$editing && $kind === 'article') { $fields[] = 'articletime'; $values['articletime'] = gmdate('Y-m-d', time() + 28800); $fields[] = 'articlename'; $values['articlename'] = c_h(c_text($_POST['articlename'] ?? '', 100)); }
    $db = c_db(); $db->begin_transaction();
    try {
        if ($editing) c_q('UPDATE ' . l_table($kind) . ' SET ' . implode(',', array_map(function ($field) { return $field . '=?'; }, $fields)) . ' WHERE id=?', str_repeat('s', count($values)) . 'i', array_merge(array_values($values), array($id)));
        else { c_q('INSERT INTO ' . l_table($kind) . '(' . implode(',', $fields) . ') VALUES(' . implode(',', array_fill(0, count($fields), '?')) . ')', str_repeat('s', count($values)), array_values($values)); $id = $db->insert_id; }
        l_set_visibility($kind, $id, $visibility);
        $db->commit();
    } catch (Throwable $error) { $db->rollback(); throw $error; }
    echo '1';
}
