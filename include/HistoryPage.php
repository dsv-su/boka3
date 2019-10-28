<?php
class HistoryPage extends Page {
    private $action = 'list';
    private $inventory = null;
    
    public function __construct() {
        parent::__construct();
        if(isset($_GET['action'])) {
            $this->action = $_GET['action'];
        }
        if(isset($_GET['id'])) {
            try {
                $this->inventory = new Inventory($_GET['id']);
            } catch(Exception $e) {
                $this->inventory = null;
                $this->action = 'list';
                $this->error = 'Det finns ingen inventering med det ID-numret.';
            }
        }
        switch($this->action) {
            case 'show':
                $this->subtitle = 'Inventeringsdetaljer';
                break;
            case 'list':
                $this->subtitle = 'Historik';
                break;
        }
    }

    protected function render_body() {
        switch($this->action) {
            case 'list':
                print(replace(array('title' => 'Genomförda inventeringar'),
                              $this->fragments['subtitle']));
                print($this->build_inventory_table());
                print(replace(array('title' => 'Skrotade artiklar'),
                              $this->fragments['subtitle']));
                $discards = get_items('product_discarded');
                if($discards) {
                    print($this->build_product_table($discards));
                } else {
                    print('Inga artiklar skrotade.');
                }
                break;
            case 'show':
                if($this->inventory &&
                    Inventory::get_active() !== $this->inventory) {
                    print($this->build_inventory_details($this->inventory,
                                                         false));
                }
                break;
        }
    }

    private function build_inventory_table() {
        $items = get_items('inventory_old');
        if(!$items) {
            return 'Inga inventeringar gjorda.';
        }
        $rows = '';
        foreach($items as $inventory) {
            $id = $inventory->get_id();
            $inventory_link = replace(array('id' => $id,
                                            'name' => $id,
                                            'page' => 'history'),
                                      $this->fragments['item_link']);
            $num_seen = count($inventory->get_seen_products());
            $num_unseen = count($inventory->get_unseen_products());
            $start = format_date($inventory->get_starttime());
            $end = format_date($inventory->get_endtime());
            $rows .= replace(array('item_link' => $inventory_link,
                                   'start_date' => $start,
                                   'end_date' => $end,
                                   'num_seen' => $num_seen,
                                   'num_unseen' => $num_unseen),
                             $this->fragments['inventory_row']);
        }
        return replace(array('item' => 'Tillfälle',
                             'rows' => $rows),
                       $this->fragments['inventory_table']);
    }
}
?>
