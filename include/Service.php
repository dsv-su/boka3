<?php
class Service extends Event {
    public static function create_service($product) {
        begin_trans();
        $event = parent::create_event($product);
        $event_id = $event->get_id();
        $insert = prepare('insert into `service`(`event`) values (?)');
        bind($insert, 'i', $event_id);
        execute($insert);
        commit_trans();
        return new Service($event_id);
    }
    
    public function __construct($id) {
        parent::__construct($id);
        $search = prepare('select * from `service` where `event`=?');
        bind($search, 'i', $id);
        execute($search);
        $result = result_single($search);
        if($result === null) {
            throw new Exception('Service does not exist.');
        }
        $this->update_fields();
    }

    protected function update_fields() {
        parent::update_fields();
    }
}
?>
