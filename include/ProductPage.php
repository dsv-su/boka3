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
                $products = $this->build_product_table(get_items('product'));
                print(replace(array('product_table' => $products),
                              $this->fragments['product_page']));
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
        $history = $this->build_history_table($this->product->get_history());
        $attachments = $this->build_attachment_list(
            $this->product->get_attachments());
        $fields = array('id'          => $this->product->get_id(),
                        'brand'       => $this->product->get_brand(),
                        'name'        => $this->product->get_name(),
                        'serial'      => $this->product->get_serial(),
                        'invoice'     => $this->product->get_invoice(),
                        'tags'        => $tags,
                        'info'        => $info,
                        'label'       => '',
                        'hidden'      => 'hidden',
                        'service'     => 'Starta service',
                        'history'     => $history,
                        'attachments' => $attachments);
        if(class_exists('QRcode')) {
            $fields['label'] = replace($fields,
                                       $this->fragments['product_label']);
        }
        if(!$this->product->get_discardtime()) {
            $fields['hidden'] = '';
            if($this->product->get_status() == 'service') {
                $fields['service'] = 'Avsluta service';
            }
        }
        return replace($fields, $this->fragments['product_details']);
    }

    private function build_history_table($history) {
        if(!$history) {
            return 'Ingen historik att visa.';
        }
        $rows = '';
        foreach($history as $event) {
            $status = $event->get_status();
            $itemlink = 'Service';
            $start = $event->get_starttime();
            $end = $event->get_returntime();
            $note = '';
            if($event instanceof Loan) {
                $user = $event->get_user();
                $product = $event->get_product();
                $itemlink = replace(array('id' => $user->get_id(),
                                          'name' => $user->get_name(),
                                          'page' => 'users'),
                                    $this->fragments['item_link']);
                if(!$end) {
                    $end = $event->get_endtime();
                    $extend = format_date(default_loan_end(time()));
                    $note = replace(array('id' => $product->get_id(),
                                      'end_new' => $extend),
                                $this->fragments['loan_extend_form']);
                }
            }
            $rows .= replace(array('status' => $status,
                                   'item_link' => $itemlink,
                                   'start_date' => format_date($start),
                                   'end_date' => format_date($end),
                                   'note' => $note),
                             $this->fragments['history_row']);
        }
        return replace(array('rows' => $rows,
                             'item' => 'Låntagare'),
                       $this->fragments['history_table']);
    }


    private function build_attachment_list($attachments) {
        if(!$attachments) {
            return '<p>Inga bilagor.</p>';
        }
        $items = '';
        foreach($attachments as $attachment) {
            $date = format_date($attachment->get_uploadtime());
            $items .= replace(array('name' => $attachment->get_filename(),
                                    'id'   => $attachment->get_id(),
                                    'date' => $date),
                              $this->fragments['attachment']);
        }
        return replace(array('attachments' => $items),
                       $this->fragments['attachment_list']);
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
