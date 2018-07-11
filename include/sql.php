<?php
require_once("./config.php");

$db = new mysqli($db_host, $db_user, $db_pass, $db_name);
if($db->connect_errno) {
    $error = 'Failed to connect to db. The error was: '.$db->connect_error;
    throw new Exception($error);
}

function prepare($statement) {
    global $db;

    if(!($s = $db->prepare($statement))) {
        $error  = 'Failed to prepare the following statement: '.$statement;
        $error .= '\n';
        $error .= $db->error.' ('.$db->errno.')';
        throw new Exception($error);
    }

    return $s;
}

function bind($statement, $types, ...$values) {
    global $db;

    return $statement->bind_param($types, ...$values);
}

function execute($statement) {
    if(!$statement->execute()) {
        $error  = 'Failed to execute the following statement: '.$statement;
        $error .= '\n';
        $error .= $statement->error.' ('.$statement->errno.')';
        throw new Exception($error);
    }
    return true;
}

function result_list($statement) {
    return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
}

function result_single($statement) {
    $out = result_list($statement);
    switch(count($out)) {
        case 0:
            return null;
        case 1:
            foreach($out as $value) {
                return $value;
            }
        default:
            throw new Exception('More than one result available.');
    }
}

function begin_trans() {
    global $db;

    $db->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
}

function commit_trans() {
    global $db;

    $db->commit();
    return true;
}

function revert_trans() {
    global $db;

    $db->rollback();
    return false;
}

?>
