<?php
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
?>
