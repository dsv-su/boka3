<?php
require_once('./include/view.php');

header('Content-Type: text/html; charset=UTF-8');

$action = 'start';
if(isset($_GET['action'])) {
    $action = $_GET['action'];
}

$page = null;
switch($action) {
    case 'start':
    default:
        $page = new StartPage();
        $page->title = "Boka2";
        $page->content = "Här är det tomt just nu.";
        break;
    case 'do':
        print "hej";
        break;
}
if($page) {
    print($page->get_contents());
}
?>
