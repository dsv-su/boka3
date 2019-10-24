<?php
spl_autoload_register(function ($class) {
    include('./include/'.$class.'.php');
});
require('./config.php');
require('./include/functions.php');

header('Content-Type: text/html; charset=UTF-8');

$cron = new Cron($reminder_sender, $error_address);
$cron->run();

?>
