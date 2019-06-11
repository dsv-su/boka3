<?php

set_include_path(get_include_path().PATH_SEPARATOR.'include/');
spl_autoload_register(function ($class) {
    if($class == 'qrcode') {
        include('./phpqrcode/qrlib.php');
    }
});
require('./config.php');
require('functions.php');

header('Content-Type: text/html; charset=UTF-8');

$page = null;
if(isset($_GET['page'])) {
    $page = $_GET['page'];
}

make_page($page)->render();

?>
