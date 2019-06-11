<?php
class SearchPage extends Page {
    private $terms = array();
    
    public function __construct() {
        parent::__construct();
        unset($_GET['page']);
        if(isset($_GET['q']) && !$_GET['q']) {
            unset($_GET['q']);
        }
        $this->terms = $this->translate_keys($_GET);
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

    private function translate_keys($terms) {
        $translated = array();
        foreach($terms as $key => $value) {
            $newkey = $key;
            switch($key) {
                case 'q':
                    $newkey = 'fritext';
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
                    $newitem = 'no_loan';
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
        $terms = '';
        foreach($this->terms as $key => $value) {
            if(!is_array($value)) {
                $terms .= replace(array('term' => ucfirst($key).": $value",
                                        'key' => $key,
                                        'value' => $value),
                                  $this->fragments['search_term']);
            } else {
                foreach($value as $item) {
                    $terms .= replace(array('term' => ucfirst($key).": $item",
                                            'key' => $key,
                                            'value' => $item),
                                      $this->fragments['search_term']);
                }
            }
        }
        print(replace(array('terms' => $terms),
                      $this->fragments['search_form']));
        if($this->terms) {
            $hits = $this->do_search();
            print(replace(array('title' => 'Sökresultat'),
                          $this->fragments['title']));
            $result = '';
            if(isset($hits['user'])) {
                $result = replace(array('title' => 'Låntagare'),
                                  $this->fragments['subtitle']);
                $result .= $this->build_user_table($hits['user']);
            }
            if(isset($hits['product'])) {
                $result .= replace(array('title' => 'Artiklar'),
                                   $this->fragments['subtitle']);
                $result .= $this->build_product_table($hits['product']);
            }
            if(!$result) {
                $result = 'Inga träffar.';
            }
            print($result);
        }
    }
}
?>
