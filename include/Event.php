<?php
class Event {
    protected $id = 0;
    protected $product = 0;
    protected $starttime = 0;
    protected $returntime = null;

    protected static function create_event($product) {
        $status = $product->get_status();
        if($status != 'available') {
            $emsg = '';
            $prod_id = $product->get_id();
            switch($status) {
                case 'on_loan':
                case 'overdue':
                    $loan_id = $product->get_active_loan()->get_id();
                    $emsg = "Product $prod_id has an active "
                          . "loan (id $loan_id).";
                    break;
                case 'discarded':
                    $emsg = "Product $prod_id has been discarded.";
                    break;
                case 'service':
                    $service_id = $product->get_active_service()->get_id();
                    $emsg = "Product $prod_id is on service "
                          . "(id $service_id).";
                    break;
            }
            throw new Exception($emsg);
        }
        $now = time();
        $insert = prepare('insert into
                               `event`(`product`, `starttime`)
                               values (?, ?)');
        bind($insert, 'ii', $product->get_id(), $now);
        execute($insert);
        $event_id = $insert->insert_id;
        return new Event($event_id);
    }
    
    public function __construct($id) {
        $search = prepare('select `id` from `event`
                           where `id`=?');
        bind($search, 'i', $id);
        execute($search);
        $result = result_single($search);
        if($result === null) {
            throw new Exception('Event does not exist.');
        }
        $this->id = $result['id'];
        $this->update_fields();
    }
    
    protected function update_fields() {
        $get = prepare('select * from `event` where `id`=?');
        bind($get, 'i', $this->id);
        execute($get);
        $result = result_single($get);
        $this->product = $result['product'];
        $this->starttime = $result['starttime'];
        $this->returntime = $result['returntime'];
    }

    public function get_id() {
        return $this->id;
    }

    public function get_product() {
        return new Product($this->product);
    }

    public function get_starttime() {
        return $this->starttime;
    }

    public function get_returntime() {
        return $this->returntime;
    }
    
    public function is_active() {
        if($this->returntime === null) {
            return true;
        }
        return false;
    }

    public function get_status() {
        $class = strtolower(get_class($this));
        if($this->is_active()) {
            return 'active_' . $class;
        }
        return 'inactive_' . $class;
    }

    public function end() {
        $now = time();
        $query = prepare('update `event` set `returntime`=? where `id`=?');
        bind($query, 'ii', $now, $this->id);
        execute($query);
        $this->returntime = $now;
        return true;
    }
}
?>
