<?php
require_once('./include/sql.php');

class Product {
    private $id = 0;
    private $name = '';
    private $invoice = '';
    private $location = '';
    private $info = array();

    public function getId() {
        return $this->id;
    }

    public function getName() {
        return $this->name;
    }

    public function setName($newname) {
        $this->name = $newname;
        $update = prepare('update `product` set `name` = ? where `id` = ?');
        bind($update, 'si', $this->name, $this->id);
        return execute($update);
    }
    
}

?>
