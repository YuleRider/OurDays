<?php
function ourdays_config()
{
    static $config;
    if ($config !== null) return $config;
    $path = dirname(__DIR__, 2) . '/config/app.php';
    if (!is_file($path)) throw new RuntimeException('OurDays is not configured. Follow the CLI installation steps in README.');
    $config = require $path;
    if (!is_array($config) || !isset($config['database'], $config['site_url'])) throw new RuntimeException('Invalid OurDays configuration');
    return $config;
}
function likegirl_runtime_dir()
{
    if (defined('LIKEGIRL_RUNTIME_DIR')) return LIKEGIRL_RUNTIME_DIR;
    return dirname(__DIR__, 2) . '/var';
}
