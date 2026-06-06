<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "PHP version: " . PHP_VERSION . "<br>\n";

// Check files exist
$files = ['db_connection.php', 'tbs_class.php', 'tbs_plugin_opentbs.php', 'chung_tu_chua_nhan_3.docx'];
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    echo "$f: " . (file_exists($path) ? "EXISTS (" . filesize($path) . " bytes)" : "MISSING") . "<br>\n";
}

// Check DB
require_once __DIR__ . '/db_connection.php';
echo "DB conn: " . (isset($conn) && $conn ? "OK" : "FAIL") . "<br>\n";

// Check TBS
echo "Loading tbs_class.php...<br>\n"; flush();
include_once __DIR__ . '/tbs_class.php';
echo "clsTinyButStrong exists: " . (class_exists('clsTinyButStrong') ? 'YES' : 'NO') . "<br>\n";

echo "Loading tbs_plugin_opentbs.php...<br>\n"; flush();
include_once __DIR__ . '/tbs_plugin_opentbs.php';
echo "OPENTBS_PLUGIN defined: " . (defined('OPENTBS_PLUGIN') ? 'YES' : 'NO') . "<br>\n";

// Try init TBS
echo "Init TBS...<br>\n"; flush();
$TBS = new clsTinyButStrong();
$TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);
echo "TBS OK<br>\n";

// Try load template
$templateFile = __DIR__ . '/chung_tu_chua_nhan_3.docx';
$TBS->LoadTemplate($templateFile, OPENTBS_ALREADY_UTF8);
echo "Template loaded OK<br>\n";

echo "All checks passed!<br>\n";
