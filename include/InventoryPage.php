<?php
class InventoryPage extends Page {
    private $inventory = null;
    
    public function __construct() {
        parent::__construct();
        $this->inventory = Inventory::get_active();
    }

    protected function render_body() {
        if($this->inventory === null) {
            print($this->fragments['inventory_start']);
            return;
        }
        print($this->build_inventory_details($this->inventory));
    }
}
?>
