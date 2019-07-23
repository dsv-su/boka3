<?php

/*
   Takes an html file containing named fragments.
   Returns an associative array on the format array[name]=>fragment.
   
   Fragments are delimited like this:
   
   ¤¤ name 1 ¤¤
   fragment 1
   ¤¤ name 2 ¤¤
   fragment 2
   ¤¤ name 3 ¤¤
   fragment 3

   The first delimiter and name ('¤¤ name 1 ¤¤' in the above example) can
   be omitted, in which case the first fragment will be assigned the
   name 'base'. All other fragments must be named.

   Throws an exception if:
   - any fragment except the first is missing a name
   - two (or more) fragments share a name
 */
function get_fragments($infile) {
    $out = array();
    $name = '';
    $current_fragment = '';

    $filecontents = file($infile);
    $iter = 0;
    foreach($filecontents as $line) {
        if(strpos(trim($line), '¤¤') === 0) {
            if($iter != 0) {
                $out = try_adding($name, $current_fragment, $out, $infile);
            }
            $name = trim($line, "\t\n\r ¤");
            $current_fragment = '';
        } else {
            $current_fragment .= $line;
        }
        $iter++;
    }
    return try_adding($name, $current_fragment, $out, $infile);
}

function try_adding($key, $value, $array, $filename) {
    if(array_key_exists($key, $array)) {
        throw new Exception('There is already a fragment with that name in '.$filename);
    } else if($key === '') {
        throw new Exception('There is an unnamed fragment in '.$filename);
    }
    
    $array[$key] = trim($value);

    return $array;
}

/*
   Takes an associative array and a string.
   Returns a string.

   Replaces each occurrence of each array key in the input string
   with the associated array value, and returns the result.
 */
function replace($assoc_arr, $subject) {
    $keys = array();
    $values = array();

    foreach($assoc_arr as $key => $value) {
        $keys[] = '¤'.$key.'¤';
        $values[] = $value;
    }

    return str_replace($keys, $values, $subject);
}

function make_page($page) {
    switch($page) {
        default:
        case 'checkout':
            return new CheckoutPage();
        case 'return':
            return new ReturnPage();
        case 'search':
            return new SearchPage();
        case 'products':
            return new ProductPage();
        case 'users':
            return new UserPage();
        case 'inventory':
            return new InventoryPage();
        case 'history':
            return new HistoryPage();
        case 'ajax':
            return new Ajax();
        case 'qr':
            return new QR();
        case 'print':
            return new Printer();
    }
}

function get_ids($type) {
    $append = '';
    switch($type) {
        case 'user':
            break;
        case 'product':
            $append = 'where `discardtime` is null';
            break;
        case 'loan':
            break;
        case 'inventory':
            break;
        case 'product_discarded':
            $append = 'where `discardtime` is not null';
            $type = 'product';
            break;
        case 'loan_active':
            $append = 'where `returntime` is null';
            $type = 'loan';
            break;
        case 'inventory_old':
            $append = 'where `endtime` is not null order by `id` desc';
            $type = 'inventory';
            break;
        default:
            $err = "$type is not a valid argument.";
            throw new Exception($err);
            break;
    }
    $query = "select `id` from `$type`";
    if($append) {
        $query .= " $append";
    }
    $get = prepare($query);
    execute($get);
    $ids = array();
    foreach(result_list($get) as $row) {
        $ids[] = $row['id'];
    }
    return $ids;
}

function get_items($type) {
    $construct = null;
    switch($type) {
        case 'user':
            $construct = function($id) {
                return new User($id);
            };
            break;
        case 'product':
        case 'product_discarded':
            $construct = function($id) {
                return new Product($id);
            };
            break;
        case 'loan':
        case 'loan_active':
            $construct = function($id) {
                return new Loan($id);
            };
            break;
        case 'inventory':
        case 'inventory_old':
            $construct = function($id) {
                return new Inventory($id);
            };
            break;
        default:
            $err = "$type is not a valid argument.";
            throw new Exception($err);
            break;
    }
    $ids = get_ids($type);
    $list = array();
    foreach($ids as $id) {
        $list[] = $construct($id);
    }
    return $list;
}

function suggest($type) {
    $search = '';
    $typename = 'name';
    switch($type) {
        case 'user':
            $search = prepare('select `name` from `user` order by `name`');
            break;
        case 'template':
            $search = prepare('select `name` from `template` order by `name`');
            break;
        case 'tag':
            $search = prepare(
                '(select `tag` from `product_tag`)
                 union
                 (select `tag` from `template_tag`)
                 order by `tag`');
            $typename = 'tag';
            break;
        case 'field':
            $search = prepare(
                '(select `field` from `product_info`)
                 union
                 (select `field` from `template_info`)
                 order by `field`');
            $typename = 'field';
            break;
        default:
            return array();
    }
    execute($search);
    $out = array();
    foreach(result_list($search) as $row) {
        $out[] = $row[$typename];
    }
    return $out;
}

function match($testvalues, $matchvalues) {
    # match only presence of field (if no value given)
    if(!$testvalues && $matchvalues) {
        return true;
    }
    if(!is_array($testvalues)) {
        $testvalues = array($testvalues);
    }
    foreach($testvalues as $value) {
        foreach($matchvalues as $candidate) {
            if(fnmatch('*'.$value.'*', $candidate, FNM_CASEFOLD)) {
                return true;
            }
        }
    }
    return false;
}

function format_date($date) {
    if($date) {
        return gmdate('Y-m-d', $date);
    }
    return $date;
}

function default_loan_end($start) {
    return $start + 604800; # 1 week later
}


### Database interaction functions ###

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
        $error  = 'Failed to execute statement.';
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
