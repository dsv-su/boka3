<?php
require_once('./include/sql.php');
require_once('./include/ldap.php');

function get_ids($type) {
    switch($type) {
        case 'user':
        case 'product':
        case 'loan':
            break;
        default:
            $err = "$type is not a valid argument. Valid arguments are user, product, loan.";
            throw new Exception($err);
            break;
    }
    $get = prepare("select `id` from `$type`");
    execute($get);
    $ids = array();
    foreach(result_list($get) as $row) {
        $ids[] = $row['id'];
    }
    return $ids;
}

function search_products($term) {
    $search = prepare("select * from `product` where `name` like ?");
    bind($search, 's', $term.'%');
    execute($search);
    $out = array();
    foreach(result_list($search) as $row) {
        $out[] = new Product($row['id']);
    }
    return $out;
}

function search_users($term) {
    global $ldap;

    $result = array_merge($ldap->search_user($term),
                          $ldap->search_name($term));
    $out = array();
    foreach(array_keys($result) as $uname) {
        $user = null;
        try {
            $user = new User($uname);
        } catch (Exception $e) {
            continue;
        }
        $out[] = $user;
    }
    return $out;
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
        $ins_prod = prepare('insert into `product`(`name`, `invoice`, `serial`) values (?, ?, ?)');
        bind($ins_prod, 'sss', $name, $invoice, $serial);
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
        $find = prepare('select `id` from `loan` where `active`=1 and product=?');
        bind($find, 'i', $this->id);
        execute($find);
        $result = result_single($find);
        if($result === null) {
            return null;
        }
        return new Loan($result['id']);
    }
}

class User {
    private $id = 0;
    private $name = '';
    private $notes = '';
    
    public static function create_user($name = '') {
        $ins_user = prepare('insert into `user`(`name`) values (?)');
        bind($ins_user, 's', $name);
        execute($ins_user);
        return new User($ins_user->insert_id);
    }

    public function __construct($clue) {
        $id = $clue;
        if(is_string($clue)) {
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
                $statement .= ' and `active`=1';
                break;
            case 'inactive':
                $statement .= ' and `active`=0';
                break;
            case 'both':
                break;
            default:
                $err = "$type is not a valid argument. Valid arguments are active, inactive, both.";
                throw new Exception($err);
                break;
        }
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
        $find = prepare('select * from `loan` where `product`=? and `active`=1');
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
    private $active = 1;
    
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
        $this->active = $loan['active'];
    }

    public function get_id() {
        return $this->id;
    }

    public function get_user() {
        return $this->user;
    }

    public function get_product() {
        return $this->product;
    }

    public function get_duration() {
        return array('start' => $this->starttime,
                     'end' => $this->endtime);
    }

    public function is_active() {
        return $this->active;
    }
    
    public function end_loan() {
        $end = prepare('update `loan` set `active`=0 where `id`=?');
        bind($end, 'i', $this->id);
        execute($end);
        $this->active = false;
        return true;
    }

    public function is_overdue() {
        if($this->active === 0) {
            return false;
        }
        $now = time();
        if($now > $this->endtime) {
            return true;
        }
        return false;
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
