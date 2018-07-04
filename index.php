<?php
require_once('./include.php'); // provides $db, $translations

header('Content-Type: text/html; charset=UTF-8');

$lang = 'sv';
if(isset($_GET['lang'])) {
    $lang = $_GET['lang'];
}

print format_page($lang, 'TEST', 'TESTING TITLE');
?>
