<?php
class Product {
    private $id = 0;
    private $name = '';
    private $invoice = '';
    private $serial = '';
    private $createtime = null;
    private $discardtime = null;
    private $info = array();
    private $tags = array();
    
    public static function create_product($name = '',
                                          $invoice = '',
                                          $serial = '',
                                          $info = array(),
                                          $tags = array()) {
        $now = time();
        begin_trans();
        try {
            $stmt = 'insert into
                         `product`(`name`, `invoice`, `serial`, `createtime`)
                         values (?, ?, ?, ?)';
            $ins_prod = prepare($stmt);
            bind($ins_prod, 'sssi', $name, $invoice, $serial, $now);
            execute($ins_prod);
            $product = new Product($serial, 'serial');
            foreach($info as $field => $value) {
                $product->set_info($field, $value);
            }
            foreach($tags as $tag) {
                $product->add_tag($tag);
            }
            commit_trans();
            return $product;
        } catch(Exception $e) {
            revert_trans();
            throw $e;
        }
    }
    
    public function __construct($clue, $type = 'id') {
        $search = null;
        switch($type) {
            case 'id':
                $search = prepare('select `id` from `product`
                                   where `id`=?');
                bind($search, 'i', $clue);
                break;
            case 'serial':
                $search = prepare('select `id` from `product`
                                   where `serial`=?');
                bind($search, 's', $clue);
                break;
            default:
                throw new Exception('Invalid type.');
        }
        execute($search);
        $result = result_single($search);
        if($result === null) {
            throw new Exception('Product does not exist.');
        }
        $this->id = $result['id'];
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
        $this->createtime = $product['createtime'];
        $this->discardtime = $product['discardtime'];
        return true;
    }
    
    private function update_info() {
        $get = prepare('select * from `product_info`
                        where `product`=? order by `field`');
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
        $get = prepare('select * from `product_tag`
                        where `product`=? order by `tag`');
        bind($get, 'i', $this->id);
        execute($get);
        $newtags = array();
        foreach(result_list($get) as $row) {
            $newtags[] = $row['tag'];
        }
        $this->tags = $newtags;
        return true;
    }

    public function matches($terms) {
        foreach($terms as $field => $values) {
            $matchvalues = array();
            if(property_exists($this, $field)) {
                $matchvalues[] = $this->$field;
            } else if(array_key_exists($field, $this->get_info())) {
                $matchvalues[] = $this->get_info()[$field];
            } else {
                switch($field) {
                    case 'tag':
                        $matchvalues = $this->get_tags();
                    case 'status':
                        $matchvalues[] = $this->get_status();
                    case 'fritext':
                        $matchvalues[] = $this->name;
                        $matchvalues[] = $this->serial;
                        $matchvalues[] = $this->invoice;
                        $matchvalues = array_merge($matchvalues,
                                                   $this->get_tags(),
                                                   array_values(
                                                       $this->get_info()));
                }
            }
            if(!match($values, $matchvalues)) {
                return false;
            }
        }
        return true;
    }

    public function get_id() {
        return $this->id;
    }

    public function get_createtime() {
        return $this->createtime;
    }

    public function get_discardtime($format = true) {
        if($this->discardtime && $format) {
            return gmdate('Y-m-d', $this->discardtime);
        }
        return $this->discardtime;
    }

    public function discard() {
        if($this->get_status() != 'available') {
            return false;
        }
        $now = time();
        $update = prepare('update `product` set `discardtime`=? where `id`=?');
        bind($update, 'ii', $now, $this->id);
        execute($update);
        $this->discardtime = $now;
        return true;
    }
    
    public function toggle_service() {
        $status = $this->get_status();
        $now = time();
        $update = '';
        if($status == 'service') {
            return $this->get_active_service()->end();
        } else if($status == 'available') {
            Service::create_service($this);
            return true;
        }
        $id = $this->get_id();
        throw new Exception("The state ($status) of this product (id $id) "
                           ."does not allow servicing.");
    }

    public function get_active_service() {
        $find = prepare('select `id` from `service`'
                       .'where `returntime` is null and product=?');
        bind($find, 'i', $this->id);
        execute($find);
        $result = result_single($find);
        if($result === null) {
            return null;
        }
        return new Service($result['id']);
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
        if(!$value) {
            return true;
        }
        $find = prepare('select * from `product_info`
                         where `product`=? and `field`=?');
        bind($find, 'is', $this->id, $field);
        execute($find);
        if(result_single($find) === null) {
            $update = prepare('insert into
                                   `product_info`(`data`, `product`, `field`)
                                   values (?, ?, ?)');
        } else {
            $update = prepare('update `product_info` set `data`=?
                               where `product`=? and `field`=?');
        }
        bind($update, 'sis', $value, $this->id, $field);
        execute($update);
        $this->update_info();
        return true;
    }
    
    public function remove_info($field) {
        $find = prepare('select * from `product_info`
                         where `product`=? and `field`=?');
        bind($find, 'is', $this->id, $field);
        execute($find);
        if(result_single($find) === null) {
            return true;
        }
        $update = prepare('delete from `product_info`
                           where `field`=? and `product`=?');
        bind($update, 'si', $field, $this->id);
        execute($update);
        $this->update_info();
        return true;
    }
    
    public function get_tags() {
        return $this->tags;
    }
    
    public function add_tag($tag) {
        if(!$tag) {
            return true;
        }
        $find = prepare('select * from `product_tag`
                         where `product`=? and `tag`=?');
        bind($find, 'is', $this->id, $tag);
        execute($find);
        if(result_single($find) === null) {
            $update = prepare('insert into `product_tag`(`tag`, `product`)
                                   values (?, ?)');
            bind($update, 'si', $tag, $this->id);
            execute($update);
            $this->update_tags();
        }
        return true;
    }
    
    public function remove_tag($tag) {
        $find = prepare('select * from `product_tag`
                         where `product`=? and `tag`=?');
        bind($find, 'is', $this->id, $tag);
        execute($find);
        if(result_single($find) === null) {
            return true;
        }
        $update = prepare('delete from `product_tag`
                           where `tag`=? and `product`=?');
        bind($update, 'si', $tag, $this->id);
        execute($update);
        $this->update_tags();
        return true;
    }

    public function get_status() {
        if($this->get_discardtime(false)) {
            return 'discarded';
        }
        if($this->get_active_service()) {
            return 'service';
        }
        $loan = $this->get_active_loan();
        if(!$loan) {
            return 'available';
        }
        if($loan->is_overdue()) {
            return 'overdue';
        }
        return 'on_loan';
    }

    public function get_active_loan() {
        $find = prepare('select `id` from `loan`
                         where `returntime` is null and product=?');
        bind($find, 'i', $this->id);
        execute($find);
        $result = result_single($find);
        if($result === null) {
            return null;
        }
        return new Loan($result['id']);
    }

    public function get_history() {
        $out = array();
        foreach(array('loan'    => function($id) { return new Loan($id);},
                      'service' => function($id) { return new Service($id);})
                          as $type => $func) {
            $find = prepare("select `id` from `$type`"
                           .'where `product`=? order by `starttime` desc');
            bind($find, 'i', $this->id);
            execute($find);
            $items = result_list($find);
            foreach($items as $item) {
                $out[] = $func($item['id']);
            }
        }
        usort($out, function($a, $b) {
            return $a->get_duration()['start'] < $b->get_duration()['start'];
        });
        return $out;
    }
}
?>
