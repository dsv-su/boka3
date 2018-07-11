<?php
require_once('./include/functions.php');

header('Content-Type: text/html; charset=UTF-8');

$action = 'start';
if(isset($_GET['action'])) {
    $action = $_GET['action'];
}

switch($action) {
    case 'start':
    default:
        print format_page('TESTING TITLE', "FOO BAR");
        break;
    case 'do':
        print "hej";
        break;
}

?>
