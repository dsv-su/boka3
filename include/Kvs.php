<?php
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
