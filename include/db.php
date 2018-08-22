<?php
require_once('./include/sql.php');
require_once('./include/ldap.php');

function get_ids($type) {
    $only_active = false;
    switch($type) {
        case 'user':
        case 'product':
        case 'loan':
            break;
        case 'active_loan':
            $only_active = true;
            $type = 'loan';
            break;
        default:
            $err = "$type is not a valid argument. Valid arguments are user, product, loan.";
            throw new Exception($err);
            break;
    }
    $query = "select `id` from `$type`";
    if($only_active) {
        $query .= ' where `returntime` is null';
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
            $construct = function($id) {
                return new Product($id);
            };
            break;
        case 'loan':
            $construct = function($id) {
                return new Loan($id);
            };
            break;
        default:
            $err = "$type is not a valid argument. Valid arguments are user, product, loan.";
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

function get_tags() {
    $search = prepare('select distinct `tag` from `product_tag`');
    execute($search);
    $out = array();
    foreach(result_list($search) as $row) {
        $out[] = $row['tag'];
    }
    return $out;
}

function search_products($term) {
    $search = prepare("select * from `product` where `name` like ?");
    bind($search, 's', '%'.$term.'%');
    execute($search);
    $out = array();
    foreach(result_list($search) as $row) {
        $out[] = new Product($row['id']);
    }
    return $out;
}

function search_users($term) {
    $userlist = get_items('user');
    $resultlist = array();
    foreach($userlist as $user) {
        $uname = $user->get_name();
        $dname = strtolower($user->get_displayname());
        if(strpos($uname, $term) !== false || strpos($dname, $term) !== false) {
            $resultlist[] = $user;
        }
    }
    return $resultlist;
}

function search_loans($products) {
    $search = 'select * from `loan` where ';
    $iter = 0;
    $terms = array();
    $tc = '';
    foreach($products as $product) {
        if($iter != 0) {
            $search .= 'or ';
        }
        $search .= '`product` = ?';
        $terms[] = $product->get_id();
        $tc .= 'i';
        $iter++;
    }
    $out = array();
    if($tc) {
        $search = prepare($search);
        bind($search, $tc, ...$terms);
        execute($search);
        foreach(result_list($search) as $loan) {
            $out[] = new Loan($loan['id']);
        }
    }
    return $out;
}


class Product {
    private $id = 0;
    private $name = '';
    private $invoice = '';
    private $serial = '';
    private $info = array();
    private $tags = array();
    
    public static function create_product(
        $name = '',
        $invoice = '',
        $serial = '',
        $info = array(),
        $tags = array()
    ) {
        $now = time();
        $stmt = 'insert into `product`(`name`, `invoice`, `serial`, `createtime`) values (?, ?, ?, ?)';
        $ins_prod = prepare($stmt);
        bind($ins_prod, 'sssi', $name, $invoice, $serial, $now);
        execute($ins_prod);
        $prodid = $ins_prod->insert_id;
        $ins_info = prepare('insert into `product_info`(`product`, `field`, `data`) values (?, ?, ?)');
        bind($ins_info, 'iss', $prodid, $key, $value);
        foreach($ins_info as $key => $value) {
            execute($ins_info);
        }
        $ins_tag = prepare('insert into `product_tag`(`product`, `tag`) values (?, ?)');
        bind($ins_tag, 'is', $prodid, $tag);
        foreach($tags as $tag) {
            execute($ins_tag);
        }
        return new Product($prodid);
    }
    
    public function __construct($id) {
        $this->id = $id;
        $this->update_fields();
        $this->update_info();
        $this->update_tags();
    }
    
    private function update_fields() {
        $get = prepare('select * from `product` where `id`=?');
        bind($get, 'i', $this->id);
        execute($get);
        $product = result_single($get);
        $this->name = $product['name'];
        $this->invoice = $product['invoice'];
        $this->serial = $product['serial'];
        return true;
    }
    
    private function update_info() {
        $get = prepare('select * from `product_info` where `product`=?');
        bind($get, 'i', $this->id);
        execute($get);
        foreach(result_list($get) as $row) {
            $field = $row['field'];
            $data = $row['data'];
            $this->info[$field] = $data;
        }
        return true;
    }
    
    private function update_tags() {
        $get = prepare('select * from `product_tag` where `product`=?');
        bind($get, 'i', $this->id);
        execute($get);
        $newtags = array();
        foreach(result_list($get) as $row) {
            $newtags[] = $row['tag'];
        }
        $this->tags = $newtags;
        return true;
    }
    
    public function get_id() {
        return $this->id;
    }
    
    public function get_name() {
        return $this->name;
    }
    
    public function set_name($newname) {
        $update = prepare('update `product` set `name`=? where `id`=?');
        bind($update, 'si', $newname, $this->id);
        execute($update);
        $this->name = $newname;
        return true;
    }
    
    public function get_invoice() {
        return $this->invoice;
    }
    
    public function set_invoice($newinvoice) {
        $update = prepare('update `product` set `invoice`=? where `id`=?');
        bind($update, 'si', $newinvoice, $this->id);
        execute($update);
        $this->invoice = $newinvoice;
        return true;
    }
    
    public function get_serial() {
        return $this->serial;
    }
    
    public function set_serial($newserial) {
        $update = prepare('update `product` set `serial`=? where `id`=?');
        bind($update, 'si', $newserial, $this->id);
        execute($update);
        $this->serial = $newserial;
        return true;
    }
    
    public function get_info() {
        return $this->info;
    }
    
    public function set_info($field, $value) {
        $find = prepare('select * from `product_info` where `id`=? and `field`=?');
        bind($find, 'is', $this->id, $field);
        execute($find);
        if(result_single($find) === null) {
            $update = prepare('insert into `product_info`(`data`, `product`, `field`) values (?, ?, ?)');
        } else {
            $update = prepare('update `product_info` set `data`=? where `product`=? and `field`=?');
        }
        bind($update, 'sis', $value, $this->id, $field);
        execute($update);
        $this->update_info();
        return true;
    }
    
    public function remove_info($field) {
        $find = prepare('select * from `product_info` where `product`=? and `field`=?');
        bind($find, 'is', $this->id, $field);
        execute($find);
        if(result_single($find) === null) {
            return true;
        }
        $update = prepare('delete from `product_info` where `field`=? and `product`=?');
        bind($update, 'si', $field, $this->id);
        execute($update);
        $this->update_info();
        return true;
    }
    
    public function get_tags() {
        return $this->tags;
    }
    
    public function add_tag($tag) {
        $find = prepare('select * from `product_tag` where `product`=? and `tag`=?');
        bind($find, 'is', $this->id, $tag);
        execute($find);
        if(result_single($find) === null) {
            $update = prepare('insert into `product_tag`(`tag`, `product`) values (?, ?)');
        } else {
            $update = prepare('update `product_tag` set `tag`=? where `product`=?');
        }
        bind($update, 'si', $tag, $this->id);
        execute($update);
        $this->update_tags();
        return true;
    }
    
    public function remove_tag($tag) {
        $find = prepare('select * from `product_tag` where `product`=? and `tag`=?');
        bind($find, 'is', $this->id, $tag);
        execute($find);
        if(result_single($find) === null) {
            return true;
        }
        $update = prepare('delete from `product_tag` where `tag`=? and `product`=?');
        bind($update, 'si', $tag, $this->id);
        execute($update);
        $this->update_tags();
        return true;
    }

    public function get_active_loan() {
        $find = prepare('select `id` from `loan` where `returntime` is null and product=?');
        bind($find, 'i', $this->id);
        execute($find);
        $result = result_single($find);
        if($result === null) {
            return null;
        }
        return new Loan($result['id']);
    }

    public function get_loan_history() {
        $find = prepare('select `id` from `loan` where product=? order by `starttime` desc');
        bind($find, 'i', $this->id);
        execute($find);
        $loans = result_list($find);
        $out = array();
        foreach($loans as $loan) {
            $out[] = new Loan($loan['id']);
        }
        return $out;
    }
}

class User {
    private $id = 0;
    private $name = '';
    private $notes = '';
    
    public static function create_user($name) {
        $ins_user = prepare('insert into `user`(`name`) values (?)');
        bind($ins_user, 's', $name);
        execute($ins_user);
        return new User($ins_user->insert_id);
    }

    public function __construct($clue) {
        $id = $clue;
        if(preg_match('/[a-z]/', $clue)) {
            $find = prepare('select `id` from `user` where `name`=?');
            bind($find, 's', $clue);
            execute($find);
            $id = result_single($find)['id'];
            if($id === null) {
                throw new Exception("Invalid username '$clue'");
            }
        }
        $this->id = $id;
        $this->update_fields();
    }
    
    private function update_fields() {
        $get = prepare('select * from `user` where `id`=?');
        bind($get, 'i', $this->id);
        execute($get);
        $user = result_single($get);
        $this->name = $user['name'];
        $this->notes = $user['notes'];
        return true;
    }

    public function get_displayname() {
        global $ldap;
        return $ldap->get_user($this->name);
    }

    public function get_id() {
        return $this->id;
    }
    
    public function get_name() {
        return $this->name;
    }
    
    public function set_name($newname) {
        $update = prepare('update `user` set `name`=? where `id`=?');
        bind($update, 'si', $newname, $this->id);
        execute($update);
        $this->name = $newname;
        return true;
    }

    public function get_notes() {
        return $this->notes;
    }
    
    public function set_notes($newnotes) {
        $update = prepare('update `user` set `notes`=? where `id`=?');
        bind($update, 'si', $newnotes, $this->id);
        execute($update);
        $this->notes = $newnotes;
        return true;
    }

    public function get_loans($type = 'both') {
        $statement = 'select `id` from `loan` where `user`=?';
        switch($type) {
            case 'active':
                $statement .= ' and `returntime` is null';
                break;
            case 'inactive':
                $statement .= ' and `returntime` is not null';
                break;
            case 'both':
                break;
            default:
                $err = "$type is not a valid argument. Valid arguments are active, inactive, both.";
                throw new Exception($err);
                break;
        }
        $statement .= ' order by `starttime` desc';
        $get = prepare($statement);
        bind($get, 'i', $this->id);
        execute($get);
        $loans = array();
        foreach(result_list($get) as $row) {
            $loans[] = new Loan($row['id']);
        }
        return $loans;
    }
    
    public function create_loan($product, $endtime) {
        $find = prepare('select * from `loan` where `product`=? and `returntime` is null');
        $prod_id = $product->get_id();
        bind($find, 'i', $prod_id);
        execute($find);
        $loan = result_single($find);
        if($loan !== null) {
            $loan_id = $loan['id'];
            throw new Exception("Product $prod_id has an active loan (id $loan_id) already.");
        }
        $now = time();
        $insert = prepare('insert into `loan`(`user`, `product`, `starttime`, `endtime`) values (?, ?, ?, ?)');
        bind($insert, 'iiii', $this->id, $prod_id, $now, $endtime);
        execute($insert);
        $loan_id = $statement->insert_id;
        return new Loan($id);
    }
}

class Loan {
    private $id = 0;
    private $user = 0;
    private $product = 0;
    private $starttime = 0;
    private $endtime = 0;
    private $returntime = null;
    
    public function __construct($id) {
        $this->id = $id;
        $this->update_fields();
    }
    
    private function update_fields() {
        $get = prepare('select * from `loan` where `id`=?');
        bind($get, 'i', $this->id);
        execute($get);
        $loan = result_single($get);
        $this->user = $loan['user'];
        $this->product = $loan['product'];
        $this->starttime = $loan['starttime'];
        $this->endtime = $loan['endtime'];
        $this->returntime = $loan['returntime'];
    }

    public function get_id() {
        return $this->id;
    }

    public function get_user() {
        return new User($this->user);
    }

    public function get_product() {
        return new Product($this->product);
    }

    public function get_duration($format = true) {
        $style = function($time) {
            return $time;
        };
        if($format) {
            $style = function($time) {
                return gmdate('Y-m-d', $time);
            };
        }
        return array('start' => $style($this->starttime),
                     'end' => $style($this->endtime),
                     'end_renew' => $style($this->endtime + 604800), # +1 week
                     'return' => $style($this->returntime));
    }

    public function is_active() {
        if($this->returntime === null) {
            return true;
        }
        return false;
    }
    
    public function end_loan() {
        $now = time();
        $end = prepare('update `loan` set `returntime`=? where `id`=?');
        bind($end, 'ii', $now, $this->id);
        execute($end);
        $this->returntime = $now;
        return true;
    }

    public function is_overdue() {
        if($this->returntime !== null) {
            return false;
        }
        $now = time();
        if($now > $this->endtime) {
            return true;
        }
        return false;
    }
}

class Inventory {
    private $id = '';
    private $starttime = '';
    private $endtime = null;
    private $seen_products = array();
    private $active_loans = array();

    public static function begin() {
        if(Inventory::get_active_inventory() !== null) {
            throw new Exception('Inventory already in progress.');
        }
        $now = time();
        $start = prepare('insert into `inventory`(`starttime`) values (?)');
        bind($start, 'i', $now);
        execute($start);
        return new Inventory($start->insert_id);
    }

    public static function get_active() {
        $search = prepare('select * from `inventory` where `endtime` is null');
        execute($search);
        $result = result_single($search);
        if($result === null) {
            return null;
        }
        return new Inventory($result['id']);
    }
    
    public function __construct($id) {
        $this->id = $id;
        $this->update_fields();
    }

    private function update_fields() {
        $get = prepare('select * from `inventory` where `id`=?');
        bind($get, 'i', $this->id);
        execute($get);
        $result = result_single($get);
        $this->starttime = $result['starttime'];
        $this->endtime = $result['endtime'];
        $prodget = prepare('select * from `inventory_product` where `inventory`=?');
        bind($prodget, 'i', $this->id);
        execute($prodget);
        foreach(result_list($prodget) as $row) {
            $this->seen_products[] = $row['product'];
        }
        $this->active_loans = get_ids('active_loan');
    }

    public function end() {
        $now = time();
        $update = prepare('update `inventory` set `endtime`=? where `id`=? and `endtime` is null');
        bind($update, 'ii', $now, $this->id);
        execute($update);
        $this->endtime = $now;
        return true;
    }

    public function add_product($product) {
        $add = prepare('insert into `inventory_product`(`inventory`, `product`) values (?, ?)');
        bind($add, 'ii', $this->id, $product->get_id());
        execute($add);
        $this->products[] = $product->get_id();
        return true;
    }
    
    public function get_id() {
        return $this->id;
    }

    public function get_duration($format = true) {
        $style = function($time) {
            return $time;
        };
        if($format) {
            $style = function($time) {
                return gmdate('Y-m-d', $time);
            };
        }
        return array('start' => $style($this->starttime),
                     'end' => $style($this->endtime));
    }

    public function get_seen_products() {
        $out = array();
        foreach($this->seen_products as $prodid) {
            $out[] = new Product($prodid);
        }
        return $out;
    }

    public function get_products_on_loan() {
        $out = array();
        foreach($this->active_loans as $loanid) {
            $loan = new Loan($loanid);
            $out[] = $loan->get_product();
        }
        return $out;
    }
}

class Kvs {
    private $items = array();
    
    public function __construct() {
        $get = prepare('select * from `kvs`');
        execute($get);
        foreach(result_list($get) as $row) {
            $key = $row['key'];
            $value = $row['value'];
            $this->items[$key] = $value;
        }
    }
    
    public function get_keys() {
        return array_keys($this->items);
    }
    
    public function get_value($key) {
        return $this->items[$key];
    }
    
    public function set_key($key, $value) {
        $find = prepare('select * from `kvs` where `key`=?');
        bind($find, 's', $key);
        execute($find);
        if(result_single($find) === null) {
            $update = prepare('insert into `kvs`(`value`, `key`) values (?, ?)');
        } else {
            $update = prepare('update `kvs` set `value`=? where `key`=?');
        }
        bind($update, 'ss', $value, $key);
        execute($update);
        $this->items[$key] = $value;
        return true;
    }
    
    public function remove_key($key) {
        $find = prepare('select * from `kvs` where `key`=?');
        bind($find, 's', $key);
        execute($find);
        if(result_single($find) === null) {
            return true;
        }
        $update = prepare('delete from `kvs` where `key`=?');
        bind($update, 's', $key);
        execute($update);
        unset($this->items[$key]);
        return true;
    }
}

?>
