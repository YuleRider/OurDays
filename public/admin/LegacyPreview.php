<?php
// Called only after the image endpoint has checked the record's visibility.
function l_preview_file($original, $asset)
{
    $fallback = array($original, $asset['mime']);
    if (!in_array($asset['mime'], array('image/jpeg', 'image/png', 'image/webp'), true)
        || !function_exists('imagecreatefromstring') || !preg_match('/^[a-f0-9]{64}$/', $asset['sha256'])) return $fallback;
    $directory = c_media_dir() . '/legacy-previews';
    if (is_link($directory)) return $fallback;
    $target = $directory . '/' . $asset['sha256'] . '-800.jpg';
    if (is_link($target)) return $fallback;
    if (is_file($target) && filesize($target) > 0) return array($target, 'image/jpeg');
    $info = @getimagesize($original);
    if (!$info || $info[0] * $info[1] > 20000000) return $fallback;
    $limit = trim(ini_get('memory_limit'));
    $units = array('k' => 1024, 'm' => 1048576, 'g' => 1073741824);
    $limitBytes = (int) $limit * ($units[strtolower(substr($limit, -1))] ?? 1);
    $needed = $info[0] * $info[1] * 8 + filesize($original) * 2 + 16 * 1048576;
    if ($limitBytes > 0 && memory_get_usage(true) + $needed > $limitBytes) return $fallback;
    if (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory)) return $fallback;
    $source = null; $display = null; $temporary = null;
    try {
        $source = @imagecreatefromstring(file_get_contents($original));
        if (!$source) return $fallback;
        if ($asset['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($original);
            $orientation = (int) ($exif['Orientation'] ?? 1);
            if (in_array($orientation, array(2, 4, 5, 7), true)) imageflip($source, IMG_FLIP_HORIZONTAL);
            $rotation = array(3 => 180, 4 => 180, 5 => -90, 6 => -90, 7 => 90, 8 => 90);
            if (isset($rotation[$orientation])) {
                $rotated = imagerotate($source, $rotation[$orientation], 0);
                if (!$rotated) return $fallback;
                imagedestroy($source); $source = $rotated;
            }
        }
        $ratio = min(1, 800 / max(imagesx($source), imagesy($source)));
        $width = max(1, (int) round(imagesx($source) * $ratio));
        $height = max(1, (int) round(imagesy($source) * $ratio));
        $display = imagecreatetruecolor($width, $height);
        imagefill($display, 0, 0, imagecolorallocate($display, 255, 255, 255));
        imagecopyresampled($display, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        imageinterlace($display, true);
        $temporary = tempnam($directory, '.preview-');
        if ($temporary === false || !@imagejpeg($display, $temporary, 80) || filesize($temporary) >= filesize($original)) return $fallback;
        if (!chmod($temporary, 0600) || !rename($temporary, $target)) return $fallback;
        return array($target, 'image/jpeg');
    } catch (Throwable $error) {
        return $fallback;
    } finally {
        if ($source) imagedestroy($source);
        if ($display) imagedestroy($display);
        if ($temporary && is_file($temporary)) unlink($temporary);
    }
}
