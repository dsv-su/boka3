<?php
class NewPage extends Page {
    private $template = null;
    
    public function __construct() {
        parent::__construct();
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
    }
    
    protected function render_body() {
        print($this->build_new_page());
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
                              'brand' => '',
                              'serial' => '',
                              'invoice' => '',
                              'tags' => $tags,
                              'info' => $fields,
                              'label' => '',
                              'hidden' => 'hidden'),
                        $this->fragments['product_details']);
        return $out;
    }
}
?>
