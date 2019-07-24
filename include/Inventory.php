<?php
class Inventory {
    private $id = '';
    private $starttime = '';
    private $endtime = null;
    private $seen_products = array();

    public static function begin() {
        if(Inventory::get_active() !== null) {
            throw new Exception('Inventory already in progress.');
        }
        $now = time();
        $start = prepare('insert into `inventory`(`starttime`) values (?)');
        bind($start, 'i', $now);
        execute($start);
        $invid = $start->insert_id;
        $prodid = '';
        $register = prepare('insert into `inventory_product`
                                 (`inventory`, `product`, `regtime`)
                                 values (?, ?, ?)');
        foreach(get_items('event_active') as $event) {
            $prodid = $event->get_product()->get_id();
            bind($register, 'iii', $invid, $prodid, $now);
            execute($register);
        }
        return new Inventory($invid);
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
        $search = prepare('select `id` from `inventory` where `id`=?');
        bind($search, 'i', $id);
        execute($search);
        $result = result_single($search);
        if($result === null) {
            throw new Exception('Invalid id');
        }
        $this->id = $result['id'];
        $this->update_fields();
    }

    private function update_fields() {
        $get = prepare('select * from `inventory` where `id`=?');
        bind($get, 'i', $this->id);
        execute($get);
        $result = result_single($get);
        $this->starttime = $result['starttime'];
        $this->endtime = $result['endtime'];
        $prodget = prepare('select * from `inventory_product`
                            where `inventory`=?');
        bind($prodget, 'i', $this->id);
        execute($prodget);
        foreach(result_list($prodget) as $row) {
            $this->seen_products[] = $row['product'];
        }
    }

    public function end() {
        $now = time();
        $update = prepare('update `inventory` set `endtime`=?
                           where `id`=? and `endtime` is null');
        bind($update, 'ii', $now, $this->id);
        execute($update);
        $this->endtime = $now;
        return true;
    }

    public function add_product($product) {
        $add = prepare('insert into `inventory_product`
                            (`inventory`, `product`, `regtime`)
                            values (?, ?, ?)');
        bind($add, 'iii', $this->id, $product->get_id(), time());
        try {
            execute($add);
        } catch(Exception $e) {
            return false;
        }
        $this->products[] = $product->get_id();
        return true;
    }
    
    public function get_id() {
        return $this->id;
    }

    public function get_starttime() {
        return $this->starttime;
    }

    public function get_endtime() {
        return $this->endtime;
    }
    
    public function get_seen_products() {
        $out = array();
        foreach($this->seen_products as $prodid) {
            $out[] = new Product($prodid);
        }
        return $out;
    }

    public function get_product_regtime($product) {
        $invid = $this->id;
        $prodid = $product->get_id();
        $search = prepare('select `regtime` from `inventory_product`
                           where `inventory` = ? and `product` = ?');
        bind($search, 'ii', $invid, $prodid);
        execute($search);
        $result = result_single($search);
        if(!$result) {
            $emsg = "Inventory $invid has no reference to product $prodid.";
                throw new Exception($emsg);
        }
        return $result['regtime'];
    }

    public function get_unseen_products() {
        $all = get_items('product');
        $out = array();
        $include = function($product) {
            if(!in_array($product->get_id(), $this->seen_products)) {
                return true;
            }
            return false;
        };
        if($this->endtime) {
            $include = function($product) {
                if($product->get_createtime() < $this->endtime
                    && !in_array($product->get_id(), $this->seen_products)) {
                    return true;
                }
                return false;
            };
        }
        foreach($all as $product) {
            if($include($product)) {
                $out[] = $product;
            }
        }
        return $out;
    }
}
?>
