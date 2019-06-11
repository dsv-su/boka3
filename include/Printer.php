<?php
class Printer extends QR {
    public function __construct() {
        parent::__construct();
    }
    
    public function render() {
        $label = replace(array('id' => $this->product->get_id(),
                               'name' => $this->product->get_name(),
                               'serial' => $this->product->get_serial()),
                         $this->fragments['product_label']);
        $title = 'Etikett för artikel '.$this->product->get_serial();
        print(replace(array('title' => $title,
                            'label' => $label),
                      $this->fragments['label_page']));
    }
}
?>
