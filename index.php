<?php
require_once('./include.php'); // provides $db, $translations

header('Content-Type: text/html; charset=UTF-8');

$lang = 'sv';
if(isset($_GET['lang'])) {
    $lang = $_GET['lang'];
}

$action = 'start';
if(isset($_GET['action'])) {
    $action = $_GET['action'];
}

switch($action) {
    case 'start':
    default:
        print format_page($lang, 'TEST', 'TESTING TITLE');
        break;
    case 'do':
        print "hej";
        break;
}

?>
