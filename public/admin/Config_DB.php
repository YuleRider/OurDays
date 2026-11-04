<?php
// Compatibility bridge only. Actual credentials live outside the document root.
require_once __DIR__ . '/Runtime.php';
$ourdaysConfig = ourdays_config();
$db_port = (int) $ourdaysConfig['database']['port'];
$db_address = $ourdaysConfig['database']['host'] . ':' . $db_port;
$db_name = $ourdaysConfig['database']['name'];
$db_username = $ourdaysConfig['database']['user'];
$db_password = $ourdaysConfig['database']['password'];
$Like_Code = $ourdaysConfig['security_code'];
