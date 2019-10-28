<?php
class SearchPage extends Page {
    private $terms = array();
    private $product_hits = array();
    private $user_hits = array();
    
    public function __construct() {
        parent::__construct();
        unset($_GET['page']);
        if(isset($_GET['q']) && !$_GET['q']) {
            unset($_GET['q']);
        }
        $this->terms = $this->translate_terms($_GET);
        if($this->terms) {
            $this->subtitle = 'Sökresultat';
            $hits = $this->do_search();
            if(isset($hits['product'])) {
                $this->product_hits = $hits['product'];
            }
            if(isset($hits['user'])) {
                $this->user_hits = $hits['user'];
            }
        }
    }
    
    private function do_search() {
        $out = array();
        if(!$this->terms) {
            return $out;
        }
        foreach(array('user', 'product') as $type) {
            $result = $this->search($type, $this->terms);
            if($result) {
                $out[$type] = $result;
            }
        }
        return $out;
    }

    private function translate_terms($terms) {
        $matches = array();
        if(isset($terms['q']) && preg_match('/([^:]+):(.*)/',
                                            $terms['q'],
                                            $matches)) {
            unset($terms['q']);
            $terms[$matches[1]] = $matches[2];
        }
        $translated = array();
        foreach($terms as $key => $value) {
            $newkey = $key;
            switch($key) {
                case 'q':
                    $newkey = 'fritext';
                    break;
                case 'tillverkare':
                case 'märke':
                    $newkey = 'brand';
                    break;
                case 'namn':
                    $newkey = 'name';
                    break;
                case 'faktura':
                case 'fakturanummer':
                    $newkey = 'invoice';
                    break;
                case 'serienummer':
                    $newkey = 'serial';
                    break;
                case 'tagg':
                    $newkey = 'tag';
                    break;
                case 'status':
                    $value = $this->translate_values($value);
                    break;
            }
            if(!array_key_exists($newkey, $translated)) {
                $translated[$newkey] = $value;
            } else {
                $temp = $translated[$newkey];
                $translated[$newkey] = array_merge((array)$temp, (array)$value);
            }
        }
        return $translated;
    }

    private function translate_values($value) {
        if(!is_array($value)) {
            $value = array($value);
        }
        $translated = array();
        foreach($value as $item) {
            $newitem = $item;
            switch($item) {
                case 'ute':
                case 'utlånad':
                case 'utlånat':
                case 'lånad':
                case 'lånat':
                    $newitem = 'on_loan';
                    break;
                case 'inne':
                case 'ledig':
                case 'ledigt':
                case 'tillgänglig':
                case 'tillgängligt':
                    $newitem = 'available';
                    break;
                case 'sen':
                case 'sent':
                case 'försenad':
                case 'försenat':
                case 'överdraget':
                    $newitem = 'overdue';
                    break;
                case 'skrotad':
                case 'skrotat':
                case 'slängd':
                case 'slängt':
                    $newitem = 'discarded';
                    break;
                case 'lagning':
                case 'reparation':
                    $newitem = 'service';
                    break;
            }
            $translated[] = $newitem;
        }
        return $translated;
    }

    private function search($type, $terms) {
        $items = get_items($type);
        $out = array();
        foreach($items as $item) {
            if($item->matches($terms)) {
                $out[] = $item;
            }
        }
        return $out;
    }
    
    protected function render_body() {
        $hidden = 'hidden';
        $terms = '';
        if($this->terms) {
            $hidden = '';
            foreach($this->terms as $key => $value) {
                if(!is_array($value)) {
                    $value = array($value);
                }
                foreach($value as $item) {
                    $terms .= replace(array('term' => ucfirst($key).": $item",
                                            'key' => $key,
                                            'value' => $item),
                                      $this->fragments['search_term']);
                }
            }
        }
        $products = 'Inga artiklar hittade.';
        if($this->product_hits) {
            $products = $this->build_product_table($this->product_hits);
        }
        $users = 'Inga användare hittade.';
        if($this->user_hits) {
            $users = $this->build_user_table($this->user_hits);
        }
        print(replace(array('terms' => $terms,
                            'hidden' => $hidden,
                            'product_results' => $products,
                            'user_results' => $users),
                      $this->fragments['search_form']));
    }
}
?>
