<?php

spl_autoload_register(function ($class) {
    if($class == 'QRcode') {
        include('./phpqrcode/qrlib.php');
        return;
    }
    include('./include/'.$class.'.php');
});
require('./config.php');
require('./include/functions.php');

header('Content-Type: text/html; charset=UTF-8');

$page = null;
if(isset($_GET['page'])) {
    $page = $_GET['page'];
}

make_page($page)->render();

?>
