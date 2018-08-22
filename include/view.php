<?php

require_once('./include/db.php');
require_once('./include/ldap.php');
require_once('./include/functions.php');

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
    }
}

abstract class Page {
    protected abstract function render_body();

    protected $page = 'checkout';
    protected $title = "Boka2";
    protected $subtitle = '';
    protected $error = null;
    protected $menuitems = array('checkout' => 'Låna',
                                 'return' => 'Lämna',
                                 'products' => 'Artiklar',
                                 'users' => 'Låntagare',
                                 'inventory' => 'Inventera');
    private $template_parts = array();
    protected $fragments = array();

    public function __construct() {
        $this->template_parts = get_fragments('./html/base.html');
        $this->fragments = get_fragments('./html/fragments.html');

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
            $active = '';
            if($this->page == $page) {
                $active = 'active';
            }
            $menu .= replace(array('title' => $title,
                                   'page' => $page,
                                   'active' => $active),
                             $this->template_parts['menuitem']);
        }
        return $menu;
    }

    final private function render_error() {
        print(replace(array('message' => $this->error),
                      $this->fragments['error']));
    }
    
    final private function render_foot() {
        print($this->template_parts['foot']);
    }

    final protected function build_user_table($users) {
        $rows = '';
        $replacements = array('name' => '',
                              'loan' => '',
                              'item_link' => '');
        foreach($users as $user) {
            $replacements['name'] = $user->get_name();
            $userlink = replace(array('id' => $user->get_id(),
                                      'name' => $user->get_displayname(),
                                      'page' => 'users'
            ), $this->fragments['item_link']);
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
                                      'page' => 'products'
            ), $this->fragments['item_link']);
            $available = 'Tillgänglig';
            $status = 'available';
            $loan = $product->get_active_loan();
            if($loan) {
                $user = $loan->get_user();
                $userlink = replace(array('name' => $user->get_displayname(),
                                          'id' => $user->get_id(),
                                          'page' => 'users'
                ), $this->fragments['item_link']);
                $available = 'Utlånad till '.$userlink;
                if($loan->is_overdue()) {
                    $status = 'overdue';
                    $available .= ', försenad';
                } else {
                    $status = 'on_loan';
                    $available .= ', åter '.$loan->get_duration()['end'];
                }
            }
            $rows .= replace(array('available' => $available,
                                   'status' => $status,
                                   'item_link' => $prodlink
            ), $this->fragments['product_row']);
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
                                      'page' => 'products'
            ), $this->fragments['item_link']);
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
                                   'end_new' => $duration['end_renew']
            ), $this->fragments['loan_row']);
        }
        return replace(array('rows' => $rows,
                             'vis_renew' => $vis_renew,
                             'vis_return' => $vis_return,
                             'item' => 'Artikel'
        ), $this->fragments['loan_table']);
    }

    final protected function build_product_loan_table($loans) {
        $rows = '';
        foreach($loans as $loan) {
            $user = $loan->get_user();
            $userlink = replace(array('id' => $user->get_id(),
                                      'name' => $user->get_name(),
                                      'page' => 'users'
            ), $this->fragments['item_link']);
            $available = '';
            $duration = $loan->get_duration();
            $status = 'on_loan';
            if($loan->is_overdue()) {
                $status = 'overdue';
            }
            $returndate = '';
            if(isset($duration['return'])) {
                $returndate = $duration['return'];
            }
            $rows .= replace(array('item_link' => $userlink,
                                   'start_date' => $duration['start'],
                                   'end_date' => $duration['end'],
                                   'return_date' => $returndate,
                                   'status' => $status,
                                   'visibility' => ''
            ), $this->fragments['loan_row']);
        }
        return replace(array('rows' => $rows,
                             'visibility' => '',
                             'item' => 'Låntagare'
        ), $this->fragments['loan_table']);
    }
}

class SearchPage extends Page {
    private $query = '';
    
    public function __construct() {
        parent::__construct();
        $this->subtitle = 'Sökresultat för ';
        if(isset($_GET['q'])) {
            $this->query = $_GET['q'];
            $this->subtitle .= "'$this->query'";
        }
    }

    private function do_search() {
        $out = array('users' => array(),
                     'products' => array());
        if(!$this->query) {
            return $out;
        }
        $out['users'] = search_users($this->query);
        $out['products'] = search_products($this->query);
        return $out;
    }
    
    protected function render_body() {
        $hits = $this->do_search();
        $nohits = true;
        if($hits['users']) {
            print(replace(array('title' => 'Låntagare'),
                          $this->fragments['subtitle']));
            print($this->build_user_table($hits['users']));
            $nohits = false;
        }
        if($hits['products']) {
            print(replace(array('title' => 'Artiklar'),
                          $this->fragments['subtitle']));
            print($this->build_product_table($hits['products']));
            $nohits = false;
        }
        if($nohits) {
            print('Inga träffar.');
        }
    }
}

class ProductPage extends Page {
    private $action = 'list';
    private $product = null;
    
    public function __construct() {
        parent::__construct();
        if(isset($_GET['action'])) {
            $this->action = $_GET['action'];
        }
        if(isset($_GET['id'])) {
            $this->product = new Product($_GET['id']);
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
            $info .= replace(array('key' => $key,
                                   'value' => $value
            ), $this->fragments['info_item']);
        }
        $tags = '';
        foreach($this->product->get_tags() as $tag) {
            $tags .= replace(array('tag' => $tag), $this->fragments['tag']);
        }
        $out = replace(array('id' => $this->product->get_id(),
                             'name' => $this->product->get_name(),
                             'serial' => $this->product->get_serial(),
                             'invoice' => $this->product->get_invoice(),
                             'tags' => $tags,
                             'info' => $info
        ), $this->fragments['product_details']);
        $out .= replace(array('title' => 'Lånehistorik'),
                        $this->fragments['subtitle']);
        $out .= $this->build_product_loan_table(
            $this->product->get_loan_history());
        return $out;
    }

    private function build_new_page() {
        return replace(array('id' => '',
                             'name' => '',
                             'serial' => '',
                             'invoice' => '',
                             'tags' => '',
                             'info' => ''
        ), $this->fragments['product_details']);
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
            $this->user = new User($_GET['id']);
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
                             'notes' => $this->user->get_notes()
        ), $this->fragments['user_details']);
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
                $this->user = new User($this->userstr);
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
        $loan_table = '';
        $subhead = '';
        $enddate = gmdate('Y-m-d', time() + 604800); # 1 week from now
        if($this->user !== null) {
            $username = $this->user->get_name();
            $displayname = $this->user->get_displayname();
            $loans = $this->user->get_loans('active');
            $loan_table = 'Inga pågående lån.';
            if($loans) {
                $loan_table = $this->build_user_loan_table($loans, 'renew');
            }
            $subhead = replace(array(
                'title' => 'Lånade artiklar'
            ), $this->fragments['subtitle']);
        }
        print(replace(array('user' => $this->userstr,
                            'displayname' => $displayname,
                            'end' => $enddate,
                            'subtitle' => $subhead,
                            'loan_table' => $loan_table,
        ), $this->fragments['checkout_page']));
    }
}

class ReturnPage extends Page {
    public function __construct() {
        parent::__construct();
    }

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
        $duration = $this->inventory->get_duration();
        $all_products = get_items('product');
        $total = count($all_products);
        $on_loan = $this->inventory->get_products_on_loan();
        $seen = $this->inventory->get_seen_products();
        $inventoried = $seen;
        foreach($on_loan as $product) {
            if(!in_array($product, $inventoried)) {
                $inventoried[] = $product;
            }
        }
        $unseen = array();
        foreach($all_products as $product) {
            if(!in_array($product, $inventoried)) {
                $unseen[] = $product;
            }
        }
        $seen_title = replace(array('title' => 'Inventerade artiklar'),
                              $this->fragments['subtitle']);
        $unseen_title = replace(array('title' => 'Kvarvarande artiklar'),
                              $this->fragments['subtitle']);
        $seen_table = $this->build_product_table($inventoried);
        $unseen_table = $this->build_product_table($unseen);
        print(replace(array('start_date' => $duration['start'],
                            'total_count' => $total,
                            'seen_count' => count($on_loan) + count($seen),
                            'seen_title' => $seen_title,
                            'seen_table' => $seen_table,
                            'unseen_title' => $unseen_title,
                            'unseen_table' => $unseen_table
        ), $this->fragments['inventory_do']));
    }
}
?>
