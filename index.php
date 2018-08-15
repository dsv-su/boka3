<?php
require_once('./include/view.php');

header('Content-Type: text/html; charset=UTF-8');

$page = null;
if(isset($_GET['page'])) {
    $page = $_GET['page'];
}

if($page === 'do') {
    print('ajax endpoint');
    exit(0);
}

make_page($page)->render();

?>
