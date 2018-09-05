<?php
require_once('./include/view.php');

header('Content-Type: text/html; charset=UTF-8');

$page = null;
if(isset($_GET['page'])) {
    $page = $_GET['page'];
}

make_page($page)->render();

?>
