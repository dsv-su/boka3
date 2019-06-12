<?php
class Service {
    private $id = 0;
    private $product = 0;
    private $starttime = 0;
    private $returntime = null;

    public static function create_service($product) {
        $status = $product->get_status();
        if($status != 'available') {
            $emsg = '';
            $prod_id = $product->get_id();
            switch($status) {
                case 'on_loan':
                case 'overdue':
                    $loan_id = $product->get_active_loan()->get_id();
                    $emsg = "Product $prod_id has an active ";
                    $emsg .= "loan (id $loan_id).";
                    break;
                case 'discarded':
                    $emsg = "Product $prod_id has been discarded.";
                    break;
                case 'service':
                    $service_id = $product->get_active_service()->get_id();
                    $emsg = "Product $prod_id is on service "
                           ."(id $service_id) already.";
                    break;
            }
            throw new Exception($emsg);
        }
        $now = time();
        $insert = prepare('insert into
                               `service`(`product`, `starttime`)
                               values (?, ?)');
        bind($insert, 'ii', $product->get_id(), $now);
        execute($insert);
        $service_id = $insert->insert_id;
        return new Loan($service_id);
    }
    
    public function __construct($id) {
        $search = prepare('select `id` from `service`
                           where `id`=?');
        bind($search, 'i', $id);
        execute($search);
        $result = result_single($search);
        if($result === null) {
            throw new Exception('Service does not exist.');
        }
        $this->id = $result['id'];
        $this->update_fields();
    }
    
    private function update_fields() {
        $get = prepare('select * from `service` where `id`=?');
        bind($get, 'i', $this->id);
        execute($get);
        $loan = result_single($get);
        $this->product = $loan['product'];
        $this->starttime = $loan['starttime'];
        $this->returntime = $loan['returntime'];
    }

    public function get_id() {
        return $this->id;
    }

    public function get_user() {
        return 'Service';
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
                     'end' => '',
                     'return' => $style($this->returntime));
    }

    public function is_active() {
        if($this->returntime === null) {
            return true;
        }
        return false;
    }

    public function end() {
        $now = time();
        $query = prepare('update `service` set `returntime`=? where `id`=?');
        bind($query, 'ii', $now, $this->id);
        execute($query);
        $this->returntime = $now;
        return true;
    }
}
?>
