<?php
class ProductPage extends Page {
    private $action = 'list';
    private $template = null;
    private $product = null;
    
    public function __construct() {
        parent::__construct();
        if(isset($_GET['action'])) {
            $this->action = $_GET['action'];
        }
        if(isset($_GET['template'])) {
            $template = $_GET['template'];
            if($template) {
                try {
                    $this->template = new Template($template, 'name');
                } catch(Exception $e) {
                    $this->template = null;
                    $this->error = 'Det finns ingen mall med det namnet.';
                }
            }
        }
        if(isset($_GET['id'])) {
            $id = $_GET['id'];
            if($id) {
                try {
                    $this->product = new Product($id);
                } catch(Exception $e) {
                    $this->action = 'list';
                    $this->product = null;
                    $this->error = 'Det finns ingen artikel med det ID-numret.';
                }
            }
        }
        switch($this->action) {
            case 'show':
                $this->subtitle = 'Artikeldetaljer';
                break;
            case 'new':
                $this->subtitle = 'Ny artikel';
                break;
            case 'list':
                $this->subtitle = 'Artikellista';
                break;
        }
    }
    
    protected function render_body() {
        switch($this->action) {
            case 'list':
                print($this->fragments['create_product']);
                print($this->build_product_table(get_items('product')));
                break;
            case 'show':
                print($this->build_product_details());
                break;
            case 'new':
                print($this->build_new_page());
                break;
        }
    }
    
    private function build_product_details() {
        $info = '';
        foreach($this->product->get_info() as $key => $value) {
            $info .= replace(array('name' => ucfirst($key),
                                   'key' => $key,
                                   'value' => $value),
                             $this->fragments['info_item']);
        }
        $tags = '';
        foreach($this->escape_tags($this->product->get_tags()) as $tag) {
            $tags .= replace(array('tag' => ucfirst($tag)),
                             $this->fragments['tag']);
        }
        $fields = array('id' => $this->product->get_id(),
                        'name' => $this->product->get_name(),
                        'serial' => $this->product->get_serial(),
                        'invoice' => $this->product->get_invoice(),
                        'tags' => $tags,
                        'info' => $info);
        $label = '';
        if(class_exists('QRcode', false)) {
            $label = replace($fields, $this->fragments['product_label']);
        }
        $fields['label'] = $label;
        $out = replace($fields, $this->fragments['product_details']);
        if(!$this->product->get_discardtime()) {
            $out .= replace(array('id' => $this->product->get_id()),
                            $this->fragments['discard_button']);
        }
        $out .= replace(array('title' => 'Lånehistorik'),
                        $this->fragments['subtitle']);
        $loan_table = 'Inga lån att visa.';
        $history = $this->product->get_loan_history();
        if($history) {
            $loan_table = $this->build_product_loan_table($history);
        }
        $out .= $loan_table;
        return $out;
    }

    private function build_new_page() {
        $template = '';
        $fields = '';
        $tags = '';
        if($this->template) {
            $template = $this->template->get_name();
            foreach($this->template->get_fields() as $field) {
                $fields .= replace(array('name' => ucfirst($field),
                                         'key' => $field,
                                         'value' => ''),
                                   $this->fragments['info_item']);
            }
            foreach($this->template->get_tags() as $tag) {
                $tags .= replace(array('tag' => ucfirst($tag)),
                                 $this->fragments['tag']);
            }
        }
        $out = replace(array('template' => $template),
                       $this->fragments['template_management']);
        $out .= replace(array('id' => '',
                              'name' => '',
                              'serial' => '',
                              'invoice' => '',
                              'tags' => $tags,
                              'info' => $fields,
                              'label' => ''),
                        $this->fragments['product_details']);
        return $out;
    }
}
?>
