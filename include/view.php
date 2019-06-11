<?php

require_once('./include/db.php');
require_once('./include/ldap.php');
require_once('./include/functions.php');
include_once('./phpqrcode/qrlib.php');

function make_page($page) {
    switch($page) {
        default:
        case 'checkout':
            return new CheckoutPage();
        case 'return':
            return new ReturnPage();
        case 'search':
            return new SearchPage();
        case 'products':
            return new ProductPage();
        case 'users':
            return new UserPage();
        case 'inventory':
            return new InventoryPage();
        case 'history':
            return new HistoryPage();
        case 'ajax':
            return new Ajax();
        case 'qr':
            return new QR();
        case 'print':
            return new Printer();
    }
}

abstract class Responder {
    protected $fragments = array();
    
    public function __construct() {
        $this->fragments = get_fragments('./html/fragments.html');
    }

    final protected function escape_tags($tags) {
        foreach($tags as $key => $tag) {
            $tags[$key] = str_replace(array("'",
                                            '"'),
                                      array('&#39;',
                                            '&#34;'),
                                      strtolower($tag));
        }
        return $tags;
    }
    
    final protected function unescape_tags($tags) {
        foreach($tags as $key => $tag) {
            $tags[$key] = str_replace(array('&#39;',
                                            '&#34;'),
                                      array("'",
                                            '"'),
                                      strtolower($tag));
        }
        return $tags;
    }
}

abstract class Page extends Responder {
    protected abstract function render_body();
    
    protected $page = 'checkout';
    protected $title = "DSV Utlåning";
    protected $subtitle = '';
    protected $error = null;
    protected $menuitems = array('checkout' => 'Låna',
                                 'return' => 'Lämna',
                                 'products' => 'Artiklar',
                                 'users' => 'Låntagare',
                                 'inventory' => 'Inventera',
                                 'history' => 'Historik',
                                 'search' => 'Sök');
    private $template_parts = array();
    
    public function __construct() {
        parent::__construct();
        $this->template_parts = get_fragments('./html/base.html');
        
        if(isset($_GET['page'])) {
            $this->page = $_GET['page'];
        }
        if(isset($this->menuitems[$this->page])) {
            $this->subtitle = $this->menuitems[$this->page];
        }
    }
    
    public function render() {
        $this->render_head();
        $this->render_body();
        if($this->error) {
            $this->render_error();
        }
        $this->render_foot();
    }
    
    final private function render_head() {
        $headtitle = $this->title;
        $pagetitle = $this->title;
        if($this->subtitle) {
            $headtitle .= ' - '. $this->subtitle;
            $pagetitle = $this->subtitle;
        }
        $query = '';
        if(isset($_GET['q'])) {
            $query = $_GET['q'];
        }
        print(replace(
            array('title' => $headtitle,
                  'menu' => $this->build_menu(),
                  'query'=> $query),
            $this->template_parts['head']
        ));
        print(replace(array('title' => $pagetitle),
                      $this->fragments['title']));
    }

    private function build_menu() {
        $menu = '';
        foreach($this->menuitems as $page => $title) {
            $align = 'left';
            $active = '';
            if($this->page == $page) {
                $active = 'active';
            }
            if($page == 'search') {
                $align = 'right';
            }
            $menu .= replace(array('title' => $title,
                                   'page' => $page,
                                   'align' => $align,
                                   'active' => $active),
                             $this->template_parts['menuitem']);
        }
        return $menu;
    }

    final private function render_error() {
        print(replace(array('type' => 'error',
                            'message' => $this->error),
                      $this->fragments['message']));
    }
    
    final private function render_foot() {
        print($this->template_parts['foot']);
    }

    final protected function build_user_table($users) {
        $rows = '';
        foreach($users as $user) {
            $replacements = array('name' => '',
                                  'loan' => '',
                                  'has_notes' => '',
                                  'notes' => '',
                                  'item_link' => '');
            $replacements['name'] = $user->get_name();
            $notes = $user->get_notes();
            if($notes) {
                $replacements['notes'] = $notes;
                $replacements['has_notes'] = '*';
            }
            $userlink = replace(array('id' => $user->get_id(),
                                      'name' => $user->get_displayname(),
                                      'page' => 'users'),
                                $this->fragments['item_link']);
            $replacements['item_link'] = $userlink;
            $loans = $user->get_loans('active');
            $loan_str = '';
            $count = count($loans);
            switch($count) {
                case 0:
                    break;
                case 1:
                    $product = $loans[0]->get_product();
                    $loan_str = $product->get_name();
                    break;
                default:
                    $loan_str = $count .' artiklar';
                    break;
            }
            $replacements['loan'] = $loan_str;
            $rows .= replace($replacements, $this->fragments['user_row']);
        }
        return replace(array('rows' => $rows),
                       $this->fragments['user_table']);
    }

    final protected function build_product_table($products) {
        $rows = '';
        foreach($products as $product) {
            $prodlink = replace(array('id' => $product->get_id(),
                                      'name' => $product->get_name(),
                                      'page' => 'products'),
                                $this->fragments['item_link']);
            $available = 'Tillgänglig';
            $status = 'available';
            $discarded = $product->get_discardtime();
            if($discarded) {
                $available = 'Skrotad '.$discarded;
                $status = 'discarded';
            } else {
                $loan = $product->get_active_loan();
                if($loan) {
                    $user = $loan->get_user();
                    $userlink = replace(array('name' => $user->get_displayname(),
                                              'id' => $user->get_id(),
                                              'page' => 'users'),
                                        $this->fragments['item_link']);
                    $available = 'Utlånad till '.$userlink;
                    if($loan->is_overdue()) {
                        $status = 'overdue';
                        $available .= ', försenad';
                    } else {
                        $status = 'on_loan';
                        $available .= ', åter '.$loan->get_duration()['end'];
                    }
                }
            }
            $rows .= replace(array('available' => $available,
                                   'status' => $status,
                                   'item_link' => $prodlink),
                             $this->fragments['product_row']);
        }
        return replace(array('rows' => $rows),
                       $this->fragments['product_table']);
    }

    final protected function build_user_loan_table($loans, $show = 'none') {
        $vis_return = 'hidden';
        $vis_renew = 'hidden';
        switch($show) {
            case 'return':
                $vis_return = '';
                break;
            case 'renew':
                $vis_renew = '';
                break;
            case 'both':
                $vis_return = '';
                $vis_renew = '';
                break;
            case 'none':
                break;
            default:
                throw new Exception('Invalid argument.');
        }
        $rows = '';
        foreach($loans as $loan) {
            $product = $loan->get_product();
            $prodlink = replace(array('id' => $product->get_id(),
                                      'name' => $product->get_name(),
                                      'page' => 'products'),
                                $this->fragments['item_link']);
            $available = '';
            $duration = $loan->get_duration();
            $status = 'on_loan';
            if($loan->is_overdue()) {
                $status = 'overdue';
            }
            $returndate = '';
            if($duration['return'] !== null) {
                $returndate = $duration['return'];
            }
            $rows .= replace(array('id' => $product->get_id(),
                                   'item_link' => $prodlink,
                                   'start_date' => $duration['start'],
                                   'end_date' => $duration['end'],
                                   'return_date' => $returndate,
                                   'status' => $status,
                                   'vis_renew' => $vis_renew,
                                   'vis_return' => $vis_return,
                                   'end_new' => $duration['end_renew']),
                             $this->fragments['loan_row']);
        }
        return replace(array('rows' => $rows,
                             'vis_renew' => $vis_renew,
                             'vis_return' => $vis_return,
                             'item' => 'Artikel'),
                       $this->fragments['loan_table']);
    }

    final protected function build_product_loan_table($loans) {
        $rows = '';
        $renew_column_visible = 'hidden';
        foreach($loans as $loan) {
            $user = $loan->get_user();
            $product = $loan->get_product();
            $userlink = replace(array('id' => $user->get_id(),
                                      'name' => $user->get_name(),
                                      'page' => 'users'),
                                $this->fragments['item_link']);
            $available = '';
            $duration = $loan->get_duration();
            $status = 'on_loan';
            if($loan->is_overdue()) {
                $status = 'overdue';
            }
            $returndate = '';
            $renew_visible = '';
            if($duration['return']) {
                $returndate = $duration['return'];
                $renew_visible = 'hidden';
            } else {
                $renew_column_visible = '';
            }
            $rows .= replace(array('item_link' => $userlink,
                                   'start_date' => $duration['start'],
                                   'end_date' => $duration['end'],
                                   'return_date' => $returndate,
                                   'status' => $status,
                                   'vis_renew' => $renew_column_visible,
                                   'vis_renew_button' => $renew_visible,
                                   'vis_return' => '',
                                   'id' => $product->get_id(),
                                   'end_new' => $duration['end_renew']),
                             $this->fragments['loan_row']);
        }
        return replace(array('rows' => $rows,
                             'vis_renew' => $renew_column_visible,
                             'vis_return' => '',
                             'item' => 'Låntagare'),
                       $this->fragments['loan_table']);
    }

    final protected function build_inventory_details($inventory,
                                                     $interactive = true) {
        $duration = $inventory->get_duration();
        $all_products = get_items('product');
        $seen = $inventory->get_seen_products();
        $unseen = array();
        foreach($all_products as $product) {
            if(!in_array($product, $seen)) {
                $unseen[] = $product;
            }
        }
        $missing = 'Saknade artiklar';
        $hidden = 'hidden';
        if($interactive) {
            $missing = 'Kvarvarande artiklar';
            $hidden = '';
        }
        $out = replace(array('start_date' => $duration['start'],
                             'total_count' => count($all_products),
                             'seen_count' => count($seen),
                             'hide' => $hidden),
                       $this->fragments['inventory_do']);
        $out .= replace(array('title' => $missing),
                        $this->fragments['subtitle']);
        if($unseen) {
            $out .= $this->build_product_table($unseen);
        } else {
            $out .= 'Inga artiklar saknas.';
        }
        $out .= replace(array('title' => 'Inventerade artiklar'),
                        $this->fragments['subtitle']);
        if($seen) {
            $out .= $this->build_product_table($seen);
        } else {
            $out .= 'Inga artiklar inventerade.';
        }
        return $out;
    }
}

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

class UserPage extends Page {
    private $action = 'list';
    private $user = null;
    
    public function __construct() {
        parent::__construct();
        if(isset($_GET['action'])) {
            $this->action = $_GET['action'];
        }
        if(isset($_GET['id'])) {
            $id = $_GET['id'];
            if($id) {
                try {
                    $this->user = new User($_GET['id']);
                } catch(Exception $e) {
                    $this->user = null;
                    $this->action = 'list';
                    $this->error = 'Det finns ingen användare med det ID-numret.';
                }
            }
        }
        switch($this->action) {
            case 'show':
                $this->subtitle = 'Låntagardetaljer';
                break;
            case 'list':
                $this->subtitle = 'Låntagarlista';
                break;
        }
    }

    protected function render_body() {
        switch($this->action) {
            case 'list':
                print($this->build_user_table(get_items('user')));
                break;
            case 'show':
                print($this->build_user_details());
                break;
        }
    }
    
    private function build_user_details() {
        $active_loans = $this->user->get_loans('active');
        $table_active = 'Inga aktuella lån.';
        if($active_loans) {
            $table_active = $this->build_user_loan_table($active_loans, 'renew');
        }
        $inactive_loans = $this->user->get_loans('inactive');
        $table_inactive = 'Inga gamla lån.';
        if($inactive_loans) {
            $table_inactive = $this->build_user_loan_table($inactive_loans,
                                                           'return');
        }
        return replace(array('active_loans' => $table_active,
                             'inactive_loans' => $table_inactive,
                             'id' => $this->user->get_id(),
                             'name' => $this->user->get_name(),
                             'displayname' => $this->user->get_displayname(),
                             'notes' => $this->user->get_notes()),
                       $this->fragments['user_details']);
    }
}

class CheckoutPage extends Page {
    private $userstr = '';
    private $user = null;

    public function __construct() {
        parent::__construct();
        if(isset($_GET['user'])) {
            $this->userstr = $_GET['user'];
            try {
                $this->user = new User($this->userstr, 'name');
            } catch(Exception $ue) {
                try {
                    $ldap = new Ldap();
                    $ldap->get_user($this->userstr);
                    $this->user = User::create_user($this->userstr);
                } catch(Exception $le) {
                    $this->error = "Användarnamnet '";
                    $this->error .= $this->userstr;
                    $this->error .= "' kunde inte hittas.";
                }
            }
        }
    }

    protected function render_body() {
        $username = '';
        $displayname = '';
        $notes = '';
        $loan_table = '';
        $subhead = '';
        $enddate = '';
        $disabled = 'disabled';
        if($this->user !== null) {
            $username = $this->user->get_name();
            $displayname = $this->user->get_displayname();
            $notes = $this->user->get_notes();
            $enddate = gmdate('Y-m-d', time() + 604800); # 1 week from now
            $disabled = '';
            $loans = $this->user->get_loans('active');
            $loan_table = 'Inga pågående lån.';
            if($loans) {
                $loan_table = $this->build_user_loan_table($loans, 'renew');
            }
            $subhead = replace(array('title' => 'Lånade artiklar'),
                               $this->fragments['subtitle']);
        }
        print(replace(array('user' => $this->userstr,
                            'displayname' => $displayname,
                            'notes' => $notes,
                            'end' => $enddate,
                            'subtitle' => $subhead,
                            'disabled' => $disabled,
                            'loan_table' => $loan_table),
                      $this->fragments['checkout_page']));
    }
}

class ReturnPage extends Page {
    protected function render_body() {
        print($this->fragments['return_page']);
    }
}

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
                $this->subtitle = 'Genomförda inventeringar';
                break;
        }
    }

    protected function render_body() {
        switch($this->action) {
            case 'list':
                print($this->build_inventory_table());
                print(replace(array('title' => 'Skrotade artiklar'),
                              $this->fragments['title']));
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
            $duration = $inventory->get_duration();
            $num_seen = count($inventory->get_seen_products());
            $num_unseen = count($inventory->get_unseen_products());
            $rows .= replace(array('item_link' => $inventory_link,
                                   'start_date' => $duration['start'],
                                   'end_date' => $duration['end'],
                                   'num_seen' => $num_seen,
                                   'num_unseen' => $num_unseen),
                             $this->fragments['inventory_row']);
        }
        return replace(array('item' => 'Tillfälle',
                             'rows' => $rows),
                       $this->fragments['inventory_table']);
    }
}

class Ajax extends Responder {
    private $action = '';
    
    public function __construct() {
        parent::__construct();
        if(isset($_GET['action'])) {
            $this->action = $_GET['action'];
        }
    }
    
    public function render() {
        $out = '';
        switch($this->action) {
            default:
                $out = new Success('ajax endpoint');
                break;
            case 'getfragment':
                $out = $this->get_fragment();
                break;
            case 'checkout':
                $out = $this->checkout_product();
                break;
            case 'return':
                $out = $this->return_product();
                break;
            case 'extend':
                $out = $this->extend_loan();
                break;
            case 'startinventory':
                $out = $this->start_inventory();
                break;
            case 'endinventory':
                $out = $this->end_inventory();
                break;
            case 'inventoryproduct':
                $out = $this->inventory_product();
                break;
            case 'updateproduct':
                $out = $this->update_product();
                break;
            case 'updateuser':
                $out = $this->update_user();
                break;
            case 'savetemplate':
                $out = $this->save_template();
                break;
            case 'deletetemplate':
                $out = $this->delete_template();
                break;
            case 'suggest':
                $out = $this->suggest();
                break;
            case 'discardproduct':
                $out = $this->discard_product();
                break;
        }
        print($out->toJson());
    }

    private function get_fragment() {
        $fragment = $_POST['fragment'];
        if(isset($this->fragments[$fragment])) {
            return new Success($this->fragments[$fragment]);
        }
        return new Failure("Ogiltigt fragment '$fragment'");
    }

    private function checkout_product() {
        $user = new User($_POST['user'], 'name');
        $product = null;
        try {
            $product = new Product($_POST['product'], 'serial');
        } catch(Exception $e) {
            return new Failure('Ogiltigt serienummer.');
        }
        try {
            $user->create_loan($product, $_POST['end']);
            return new Success($product->get_name() . 'utlånad.');
        } catch(Exception $e) {
            return new Failure('Artikeln är redan utlånad.');
        }
    }
    
    private function return_product() {
        $product = null;
        try {
            $product = new Product($_POST['serial'], 'serial');
        } catch(Exception $e) {
            return new Failure('Ogiltigt serienummer.');
        }
        $loan = $product->get_active_loan();
        if($loan) {
            $loan->end();
            $user = $loan->get_user();
            $userlink = replace(array('page' => 'users',
                                      'id'   => $user->get_id(),
                                      'name' => $user->get_displayname()),
                                $this->fragments['item_link']);
            $productlink = replace(array('page' => 'products',
                                         'id'   => $product->get_id(),
                                         'name' => $product->get_name()),
                                   $this->fragments['item_link']);
            $user = $loan->get_user();
            return new Success($productlink . ' åter från ' . $userlink);
        }
        return new Failure('Artikeln är inte utlånad.');
    }

    private function extend_loan() {
        $product = null;
        try {
            $product = new Product($_POST['product']);
        } catch(Exception $e) {
            return new Failure('Ogiltigt ID.');
        }
        $loan = $product->get_active_loan();
        if($loan) {
            $loan->extend($_POST['end']);
            return new Success('Lånet förlängt');
        }
        return new Failure('Lån saknas.');
    }
    
    private function start_inventory() {
        try {
            Inventory::begin();
            return new Success('Inventering startad.');
        } catch(Exception $e) {
            return new Failure('Inventering redan igång.');
        }
    }
    
    private function end_inventory() {
        $inventory = Inventory::get_active();
        if($inventory === null) {
            return new Failure('Ingen inventering pågår.');
        }
        $inventory->end();
        return new Success('Inventering avslutad.');
    }
    
    private function inventory_product() {
        $inventory = Inventory::get_active();
        if($inventory === null) {
            return new Failure('Ingen inventering pågår.');
        }
        $product = null;
        try {
            $product = new Product($_POST['serial'], 'serial');
        } catch(Exception $e) {
            return new Failure('Ogiltigt serienummer.');
        }
        $result = $inventory->add_product($product);
        if(!$result) {
            return new Failure('Artikeln är redan registrerad.');
        }
        return new Success('Artikeln registrerad.');
    }

    private function update_product() {
        $info = $_POST;
        $id = $info['id'];
        $name = $info['name'];
        $serial = $info['serial'];
        $invoice = $info['invoice'];
        $tags = array();
        if(isset($info['tag'])) {
            $tags = $this->unescape_tags($info['tag']);
        }
        foreach(array('id', 'name', 'serial', 'invoice', 'tag') as $key) {
            unset($info[$key]);
        }
        if(!$name) {
            return new Failure('Artikeln måste ha ett namn.');
        }
        if(!$serial) {
            return new Failure('Artikeln måste ha ett serienummer.');
        }
        if(!$invoice) {
            return new Failure('Artikeln måste ha ett fakturanummer.');
        }
        $product = null;
        if(!$id) {
            try {
                $temp = new Product($serial, 'serial');
                return new Failure(
                    'Det angivna serienumret finns redan på en annan artikel.');
            } catch(Exception $e) {}
            try {
                $product = Product::create_product($name,
                                                   $invoice,
                                                   $serial,
                                                   $info,
                                                   $tags);
                $prodlink = replace(array('page' => 'products',
                                          'id' => $product->get_id(),
                                          'name' => $product->get_name()),
                                    $this->fragments['item_link']);
                return new Success("Artikeln '$prodlink' sparad.");
            } catch(Exception $e) {
                return new Failure($e->getMessage());
            }
        }
        $product = new Product($id);
        if($product->get_discardtime()) {
            return new Failure('Skrotade artiklar får inte modifieras.');
        }
        if($name != $product->get_name()) {
            $product->set_name($name);
        }
        if($serial != $product->get_serial()) {
            try {
                $product->set_serial($serial);
            } catch(Exception $e) {
                return new Failure('Det angivna serienumret finns redan på en annan artikel.');
            }
        }
        if($invoice != $product->get_invoice()) {
            $product->set_invoice($invoice);
        }
        foreach($product->get_info() as $key => $prodvalue) {
            if(!isset($info[$key]) || !$info[$key]) {
                $product->remove_info($key);
                continue;
            }
            if($prodvalue != $info[$key]) {
                $product->set_info($key, $info[$key]);
            }
            unset($info[$key]);
        }
        foreach($info as $key => $invalue) {
            if($invalue) {
                $product->set_info($key, $invalue);
            }
        }
        foreach($product->get_tags() as $tag) {
            if(!in_array($tag, $tags)) {
                $product->remove_tag($tag);
                continue;
            }
            unset($tags[array_search($tag, $tags)]);
        }
        foreach($tags as $tag) {
            $product->add_tag($tag);
        }
        return new Success('Ändringarna sparade.');
    }
    
    private function update_user() {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $notes = $_POST['notes'];
        if(!$name) {
            return new Failure('Användarnamnet får inte vara tomt.');
        }
        $user = new User($id);
        if($user->get_name() != $name) {
            $user->set_name($name);
        }
        if($user->get_notes() != $notes) {
            $user->set_notes($notes);
        }
        return new Success('Ändringarna sparade.');
    }

    private function save_template() {
        $info = $_POST;
        $name = $info['template'];
        $tags = array();
        if(isset($info['tag'])) {
            $tags = $this->unescape_tags($info['tag']);
        }
        foreach(array('template',
                      'id',
                      'name',
                      'serial',
                      'invoice',
                      'tags') as $key) {
            unset($info[$key]);
        }
        if(!$name) {
            return new Failure('Mallen måste ha ett namn.');
        }
        $template = null;
        try {
            $template = new Template($name, 'name');
        } catch(Exception $e) {
            $template = Template::create_template($name, $info, $tags);
            $name = $template->get_name();
            return new Success(
                "Aktuella fält och taggar har sparats till mallen '$name'.");
        }
        foreach($template->get_fields() as $field) {
            if(!isset($info[$field])) {
                $template->remove_field($field);
            }
        }
        $existingfields = $template->get_fields();
        foreach($info as $field) {
            if(!in_array($field, $existingfields)) {
                $template->add_field($field);
            }
        }
        foreach($template->get_tags() as $tag) {
            if(!in_array($tag, $tags)) {
                $template->remove_tag($tag);
            }
        }
        $existingtags = $template->get_tags();
        foreach($tags as $tag) {
            if(!in_array($tag, $existingtags)) {
                $template->add_tag($tag);
            }
        }
        $name = $template->get_name();
        return new Success("Mallen '$name' uppdaterad.");
    }

    private function delete_template() {
        try {
            $template = $_POST['template'];
            Template::delete_template($template);
            $name = ucfirst(strtolower($template));
            return new Success("Mallen '$name' har raderats.");
        } catch(Exception $e) {
            return new Failure('Det finns ingen mall med det namnet.');
        }
    }
    
    private function suggest() {
        return new Success(suggest($_POST['type']));
    }

    private function discard_product() {
        $product = new Product($_POST['id']);
        if(!$product->get_discardtime()) {
            if($product->get_active_loan()) {
                return new Failure('Artikeln har ett aktivt lån.<br/>'
                                  .'Lånet måste avslutas innan artikeln skrotas.');
            }
            $product->discard();
            return new Success('Artikeln skrotad.');
        } else {
            return new Failure('Artikeln är redan skrotad.');
        }
    }
}

class QR extends Responder {
    protected $product = '';

    public function __construct() {
        parent::__construct();
        if(isset($_GET['id'])) {
            $this->product = new Product($_GET['id']);
        }
    }

    public function render() {
        if(class_exists('QRcode', false)) {
            QRcode::svg((string)$this->product->get_serial());
        }
    }
}

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

class Result {
    private $type = '';
    private $message = '';

    public function __construct($type, $message) {
        $this->type = $type;
        $this->message = $message;
    }

    public function toJson() {
        return json_encode(array(
            'type' => $this->type,
            'message' => $this->message
        ));
    }
}

class Success extends Result {
    public function __construct($message) {
        parent::__construct('success', $message);
    }
}

class Failure extends Result {
    public function __construct($message) {
        parent::__construct('error', $message);
    }
}
?>
