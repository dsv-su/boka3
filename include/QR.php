<?php
class QR extends Responder {
    protected $product = '';

    public function __construct() {
        parent::__construct();
        if(isset($_GET['id'])) {
            $this->product = new Product($_GET['id']);
        }
    }

    public function render() {
        if(class_exists('QRcode')) {
            QRcode::svg((string)$this->product->get_serial());
        }
    }
}
?>
