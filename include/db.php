<?php
require_once('./include/sql.php');

class Product {
    private $id = 0;
    private $name = '';
    private $invoice = '';
    private $location = '';
    private $info = array();
    private $tags = array();
    
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
        $this->location = $product['location'];
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
    
    public function getId() {
        return $this->id;
    }
    
    public function get_name() {
        return $this->name;
    }
    
    public function set_name($newname) {
        $update = prepare('update `product` set `name`=? where `id`=?');
        bind($update, 'si', $newname, $this->id);
        if(execute($update)) {
            $this->name = $newname;
            return true;
        }
        return false;
    }
    
    public function get_invoice() {
        return $this->invoice;
    }
    
    public function set_invoice($newinvoice) {
        $update = prepare('update `product` set `invoice`=? where `id`=?');
        bind($update, 'si', $newinvoice, $this->id);
        if(execute($update)) {
            $this->invoice = $newinvoice;
            return true;
        }
        return false;
    }
    
    public function get_location() {
        return $this->location;
    }
    
    public function set_location($newlocation) {
        $update = prepare('update `product` set `location`=? where `id`=?');
        bind($update, 'si', $newlocation, $this->id);
        if(execute($update)) {
            $this->location = $newlocation;
            return true;
        }
        return false;
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
        if(execute($update)) {
            $this->update_info();
            return true;
        }
        return false;
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
        if(execute($update)) {
            $this->update_info();
            return true;
        }
        return false;
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
        if(execute($update)) {
            $this->update_tags();
            return true;
        }
        return false;
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
        if(execute($update)) {
            $this->update_tags();
            return true;
        }
        return false;
    }
}

class User {
    private $id = 0;
    private $name = '';
    private $notes = '';
    
    public function __construct($id) {
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
    
    public function get_name() {
        return $this->name;
    }
    
    public function set_name($newname) {
        $update = prepare('update `user` set `name`=? where `id`=?');
        bind($update, 'si', $newname, $this->id);
        if(execute($update)) {
            $this->name = $newname;
            return true;
        }
        return false;
    }
    
    public function get_notes() {
        return $this->notes;
    }
    
    public function set_notes($newnotes) {
        $update = prepare('update `user` set `notes`=? where `id`=?');
        bind($update, 'si', $newnotes, $this->id);
        if(execute($update)) {
            $this->notes = $newnotes;
            return true;
        }
        return false;
    }
}

class Loan {
    private $id = 0;
    private $user = 0;
    private $product = 0;
    private $starttime = 0;
    private $endtime = 0;
    private $active = true;
    
    public function __construct($id) {
        $this->id = $id;
        $this->update_fields();
        $this->update_active();
    }
    
    private function update_fields() {
        $get = prepare('select * from `loan` where `id`=?');
        bind($get, 'i', $this->id);
        execute($get);
        $loan = result_single($get);
        $this->user = $get['user'];
        $this->product = $get['product'];
        $this->starttime = get['starttime'];
        $this->endtime = get['endtime'];
    }
    
    public function end_loan() {
        $end = prepare('update `loan` set `active`=0 where `id`=?');
        bind($end, 'i', $this->id);
        if(execute($end)) {
            $this->active = false;
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
        if(execute($update)) {
            $this->items[$key] = $value;
            return true;
        }
        return false;
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
        if(execute($update)) {
            unset($this->items[$key]);
            return true;
        }
        return false;
    }
}

?>
