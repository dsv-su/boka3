<?php
require_once('./include/sql.php');
require_once('./include/ldap.php');

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

function get_tags() {
    $search = prepare(
        '(select `tag` from `product_tag`)
         union
         (select `tag` from `template_tag`)
         order by `tag`'
    );
    execute($search);
    $out = array();
    foreach(result_list($search) as $row) {
        $out[] = $row['tag'];
    }
    return $out;
}

function get_fields() {
    $search = prepare(
        '(select `field` from `product_info`)
         union
         (select `field` from `template_info`)
         order by `field`'
    );
    execute($search);
    $out = array();
    foreach(result_list($search) as $row) {
        $out[] = $row['field'];
    }
    return $out;
}

function get_templates() {
    $search = prepare('select `name` from `template` order by `name`');
    execute($search);
    $out = array();
    foreach(result_list($search) as $row) {
        $out[] = $row['name'];
    }
    return $out;
}

class Product {
    private $id = 0;
    private $name = '';
    private $invoice = '';
    private $serial = '';
    private $createtime = null;
    private $discardtime = null;
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
        switch($type) {
            case 'id':
                $this->id = $clue;
                break;
            case 'serial':
                $search = prepare('select `id` from `product`
                                   where `serial`=?');
                bind($search, 's', $clue);
                execute($search);
                $result = result_single($search);
                if($result === null) {
                    throw new Exception('Invalid serial.');
                }
                $this->id = $result['id'];
                break;
            default:
                throw new Exception('Invalid type.');
        }
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
        foreach($terms as $fieldtype => $values) {
            $testfield = null;
            switch($fieldtype) {
                case 'tag':
                    foreach($values as $value) {
                        if(!in_array($value, $this->tags)) {
                            return false;
                        }
                    }
                    break;
                case 'status':
                    $loan = $this->get_active_loan();
                    foreach($values as $value) {
                        switch($value) {
                            case 'on_loan':
                                if(!$loan) {
                                    return false;
                                }
                                break;
                            case 'no_loan':
                                if($loan) {
                                    return false;
                                }
                                break;
                            case 'overdue':
                                if(!$loan || !$loan->is_overdue()) {
                                    return false;
                                }
                                break;
                            default:
                                return false;
                        }
                    }
                    break;
                case 'words':
                    $testfield = $this->name;
                    break;
                default:
                    if(property_exists($this, $fieldtype)) {
                        $testfield = $this->$fieldtype;
                    } elseif(array_key_exists($fieldtype, $this->info)) {
                        $tesfield = $this->info[$fieldtype];
                    } else {
                        return false;
                    }
                    break;
            }
            if($testfield !== null) {
                foreach($values as $value) {
                    $test = strtolower($testfield);
                    if(strpos($test, $value) === false) {
                        return false;
                    }
                }
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
        $now = time();
        $update = prepare('update `product` set `discardtime`=? where `id`=?');
        bind($update, 'ii', $now, $this->id);
        execute($update);
        $this->discardtime = $now;
        return true;
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

    public function get_loan_history() {
        $find = prepare('select `id` from `loan`
                         where product=? order by `starttime` desc');
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

class Template {
    private $id = 0;
    private $name = '';
    private $fields = array();
    private $tags = array();
    
    public static function create_template(
        $name = '',
        $fields = array(),
        $tags = array()
    ) {
        begin_trans();
        try {
            $stmt = 'insert into `template`(`name`) values (?)';
            $ins_prod = prepare($stmt);
            bind($ins_prod, 's', $name);
            execute($ins_prod);
            $template = new Template($name, 'name');
            foreach($fields as $field) {
                $template->add_field($field);
            }
            foreach($tags as $tag) {
                $template->add_tag($tag);
            }
            commit_trans();
            return $template;
        } catch(Exception $e) {
            revert_trans();
            throw $e;
        }
    }
    
    public function __construct($clue, $type = 'id') {
        switch($type) {
            case 'id':
                $this->id = $clue;
                $search = prepare('select `name` from `template`
                                   where `id`=?');
                bind($search, 'i', $this->id);
                execute($search);
                $result = result_single($search);
                if($result === null) {
                    throw new Exception('Invalid id');
                }
                $this->name = $result['name'];
                break;
            case 'name':
                $this->name = $clue;
                $search = prepare('select `id` from `template`
                                   where `name`=?');
                bind($search, 's', $this->name);
                execute($search);
                $result = result_single($search);
                if($result === null) {
                    throw new Exception('Invalid name.');
                }
                $this->id = $result['id'];
                break;
            default:
                throw new Exception('Invalid type.');
        }
        $this->update_fields();
        $this->update_tags();
    }
    
    public function get_name() {
        return $this->name;
    }

    public function set_name($name) {
        $update = prepare('update `template` set `name`=? where `id`=?');
        bind($update, 'si', $name, $this->id);
        execute($update);
        $this->name = $name;
        return true;
    }
    
    private function update_fields() {
        $get = prepare('select `field` from `template_info`
                        where `template`=? order by `field`');
        bind($get, 'i', $this->id);
        execute($get);
        $fields = array();
        foreach(result_list($get) as $row) {
            $fields[] = $row['field'];
        }
        $this->fields = $fields;
        return true;
    }
    
    private function update_tags() {
        $get = prepare('select * from `template_tag`
                        where `template`=? order by `tag`');
        bind($get, 'i', $this->id);
        execute($get);
        $newtags = array();
        foreach(result_list($get) as $row) {
            $newtags[] = $row['tag'];
        }
        $this->tags = $newtags;
        return true;
    }

    public function get_fields() {
        return $this->fields;
    }

    public function add_field($field) {
        $find = prepare('select * from `template_info`
                         where `template`=? and `field`=?');
        bind($find, 'is', $this->id, $field);
        execute($find);
        if(result_single($find) === null) {
            $update = prepare('insert into `template_info`(`template`, `field`)
                                   values (?, ?)');
            bind($update, 'is', $this->id, $field);
            execute($update);
            $this->update_fields();
        }
        return true;
    }
    
    public function remove_field($field) {
        $find = prepare('select * from `template_info`
                         where `template`=? and `field`=?');
        bind($find, 'is', $this->id, $field);
        execute($find);
        if(result_single($find) === null) {
            return true;
        }
        $update = prepare('delete from `template_info`
                           where `field`=? and `template`=?');
        bind($update, 'si', $field, $this->id);
        execute($update);
        $this->update_fields();
        return true;
    }

    public function get_tags() {
        return $this->tags;
    }

    public function add_tag($tag) {
        if(!$tag) {
            return true;
        }
        $find = prepare('select * from `template_tag`
                         where `template`=? and `tag`=?');
        bind($find, 'is', $this->id, $tag);
        execute($find);
        if(result_single($find) === null) {
            $update = prepare('insert into `template_tag`(`tag`, `template`)
                                   values (?, ?)');
            bind($update, 'si', $tag, $this->id);
            execute($update);
            $this->update_tags();
        }
        return true;
    }
    
    public function remove_tag($tag) {
        $find = prepare('select * from `template_tag`
                         where `template`=? and `tag`=?');
        bind($find, 'is', $this->id, $tag);
        execute($find);
        if(result_single($find) === null) {
            return true;
        }
        $update = prepare('delete from `template_tag`
                           where `tag`=? and `template`=?');
        bind($update, 'si', $tag, $this->id);
        execute($update);
        $this->update_tags();
        return true;
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

    public function __construct($clue, $type = 'id') {
        $id = $clue;
        switch($type) {
            case 'id':
                break;
            case 'name':
                $find = prepare('select `id` from `user` where `name`=?');
                bind($find, 's', $clue);
                execute($find);
                $id = result_single($find)['id'];
                if($id === null) {
                    throw new Exception("Invalid username '$clue'");
                }
                break;
            default:
                throw new Exception('Invalid type');
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

    public function matches($terms) {
        foreach($terms as $fieldtype => $values) {
            switch($fieldtype) {
                case 'words':
                    foreach($values as $value) {
                        if(strpos($this->name, $value) !== false) {
                            continue;
                        }
                        $name = strtolower($this->get_displayname());
                        if(strpos($name, $value) !== false) {
                            continue;
                        }
                        return false;
                    }
                    break;
                default:
                    if(!property_exists($this, $fieldtype)) {
                        return false;
                    }
                    foreach($values as $value) {
                        if($this->$fieldtype != $value) {
                            return false;
                        }
                    }
                    break;
            }
        }
        return true;
    }
    
    public function get_displayname() {
        global $ldap;
        try {
            return $ldap->get_user($this->name);
        } catch(Exception $e) {
            return 'Ej i SUKAT';
        }
    }

    public function get_email() {
        global $ldap;
        try {
            return $ldap->get_user_email($this->name);
        } catch(Exception $e) {
            return 'Mailadress saknas';
        }
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

    public function get_overdue_loans() {
        $overdue = array();
        foreach($this->get_loans('active') as $loan) {
            if($loan->is_overdue()) {
                $overdue[] = $loan;
            }
        }
        return $overdue;
    }
    
    public function create_loan($product, $endtime) {
        $find = prepare('select * from `loan`
                         where `product`=? and `returntime` is null');
        $prod_id = $product->get_id();
        bind($find, 'i', $prod_id);
        execute($find);
        $loan = result_single($find);
        if($loan !== null) {
            $loan_id = $loan['id'];
            throw new Exception(
                "Product $prod_id has an active loan (id $loan_id) already.");
        }
        $now = time();
        $insert = prepare('insert into
                               `loan`(`user`, `product`, `starttime`, `endtime`)
                               values (?, ?, ?, ?)');
        bind($insert, 'iiii',
             $this->id, $prod_id,
             $now, strtotime($endtime . ' 13:00'));
        execute($insert);
        $loan_id = $insert->insert_id;
        return new Loan($loan_id);
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
                if($time) {
                    return gmdate('Y-m-d', $time);
                }
                return $time;
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

    public function extend($time) {
        $ts = strtotime($time . ' 13:00');
        $query = prepare('update `loan` set `endtime`=? where `id`=?');
        bind($query, 'ii', $ts, $this->id);
        execute($query);
        $this->endtime = $ts;
        return true;
    }
    
    public function end() {
        $now = time();
        $query = prepare('update `loan` set `returntime`=? where `id`=?');
        bind($query, 'ii', $now, $this->id);
        execute($query);
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
        $register = prepare('insert into
                                 `inventory_product`(`inventory`, `product`)
                                 values (?, ?)');
        foreach(get_items('loan_active') as $loan) {
            $prodid = $loan->get_product()->get_id();
            bind($register, 'ii', $invid, $prodid);
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
        $add = prepare('insert into `inventory_product`(`inventory`, `product`)
                            values (?, ?)');
        bind($add, 'ii', $this->id, $product->get_id());
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
        if(isset($this->items[$key])) {
            return $this->items[$key];
        }
        return null;
    }
    
    public function set_key($key, $value) {
        $find = prepare('select * from `kvs` where `key`=?');
        bind($find, 's', $key);
        execute($find);
        if(result_single($find) === null) {
            $update = prepare('insert into `kvs`(`value`, `key`)
                                   values (?, ?)');
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
