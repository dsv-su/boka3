<?php
require_once('./include/view.php');

header('Content-Type: text/html; charset=UTF-8');

$action = null;
if(isset($_GET['action'])) {
    $action = $_GET['action'];
}

if($action === 'do') {
    print('ajax endpoint');
    exit(0);
}

$page = make_page($action);
$page->render();

?>
