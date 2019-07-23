<?php
class Loan extends Event {
    private $user = 0;
    private $endtime = 0;

    public static function create_loan($user, $product, $endtime) {
        begin_trans();
        $event = parent::create_event($product);
        $event_id = $event->get_id();
        $insert = prepare('insert into `loan`(`user`, `endtime`) values (?, ?)');
        bind($insert, 'ii', $user->get_id(), strtotime($endtime . ' 13:00'));
        execute($insert);
        commit_trans();
        return new Loan($event_id);
    }
    
    public function __construct($id) {
        parent::__construct($id);
        $search = prepare('select * from `loan` where `event`=?');
        bind($search, 'i', $id);
        execute($search);
        $result = result_single($search);
        if($result === null) {
            throw new Exception('Loan does not exist.');
        }
        $this->update_fields();
    }
    
    protected function update_fields() {
        parent::update_fields();
        $get = prepare('select * from `loan` where `event`=?');
        bind($get, 'i', $this->id);
        execute($get);
        $loan = result_single($get);
        $this->user = $loan['user'];
        $this->endtime = $loan['endtime'];
    }

    public function get_user() {
        return new User($this->user);
    }

    public function get_endtime() {
        return $this->endtime;
    }

    public function extend($time) {
        $ts = strtotime($time . ' 13:00');
        $query = prepare('update `loan` set `endtime`=? where `event`=?');
        bind($query, 'ii', $ts, $this->id);
        execute($query);
        $this->endtime = $ts;
        return true;
    }
    
    public function end() {
        $now = time();
        $query = prepare('update `event` set `returntime`=? where `id`=?');
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

    public function get_status() {
        if($this->is_overdue()) {
            return 'overdue_loan';
        }
        if($this->is_active()) {
            return 'active_loan';
        }
        return 'inactive_loan';
    }
}
?>
