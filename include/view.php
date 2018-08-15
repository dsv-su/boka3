<?php

require_once('./include/db.php');
require_once('./include/ldap.php');
require_once('./include/functions.php');

function make_page($page) {
    switch($page) {
        case 'home':
        default:
            return new StartPage();
        case 'search':
            return new SearchPage();
        case 'products':
            return new ProductPage();
    }
}

abstract class Page {
    protected abstract function render_body();

    protected $page = 'home';
    protected $title = "Boka2";
    protected $subtitle = '';
    protected $menuitems = array('home' => 'Start',
                                 'products' => 'Produkter',
                                 'users' => 'Användare',
                                 'checkout' => 'Låna ut');
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
    
    final private function render_foot() {
        print($this->template_parts['foot']);
    }

    final protected function build_user_table($users) {
        $rows = '';
        $replacements = array('id' => 0,
                              'name' => '',
                              'displayname' => '',
                              'loan' => '');
        foreach($users as $user) {
            $replacements['id'] = $user->get_id();
            $replacements['name'] = $user->get_name();
            $replacements['displayname'] = $user->get_displayname();
            $loans = $user->get_loans('active');
            $loan_str = '';
            $count = count($loans);
            switch($count) {
                case 0:
                    break;
                case 1:
                    $product = new Product($loans[0]->get_product());
                    $loan_str = $product->get_name();
                    break;
                default:
                    $loan_str = $count .' produkter';
                    break;
            }
            $replacements['loan'] = $loan_str;
            $rows .= replace($replacements, $this->fragments['user_row']);
        }
        return replace(array('title' => 'Användare',
                             'rows' => $rows),
                       $this->fragments['user_table']);
    }

    final protected function build_product_table($products) {
        $rows = '';
        $replacements = array('id' => 0,
                              'name' => '',
                              'available' => '');
        foreach($products as $product) {
            $replacements['id'] = $product->get_id();
            $replacements['name'] = $product->get_name();
            $available = '';
            $loan = $product->get_active_loan();
            if($loan) {
                if($loan->is_overdue()) {
                    $available = 'Försenad';
                } else {
                    $end = date('Y/m/d', $loan->get_duration()['end']);
                    $available = 'Utlånad till '.$end;
                }
            } else {
                $available = 'Tillgänglig';
            }
            $replacements['available'] = $available;
            $rows .= replace($replacements, $this->fragments['product_row']);
        }
        return replace(array('title' => 'Produkter',
                             'rows' => $rows),
                       $this->fragments['product_table']);
    }

}

class StartPage extends Page {
    public function __construct() {
        parent::__construct();
    }

    protected function render_body() {
        global $ldap;

        foreach(get_ids('user') as $userid) {
            $user = new User($userid);
            $users[] = $user;
        }

        foreach(get_ids('product') as $prodid) {
            $product = new Product($prodid);
            $products[] = $product;
        }

        foreach($users as $user) {
            echo "User: ".$ldap->get_user($user->get_name())."<br/>";
            foreach($user->get_loans() as $loan) {
                $product = new Product($loan->get_product());
                $active = $loan->is_active();
                if($active) {
                    echo "Borrowed product: ".$product->get_name()."<br/>";
                    if($loan->is_overdue()) {
                        echo "Loan is overdue.";
                    } else {
                        $end = $loan->get_duration()['end'];
                        echo "Loan expires on $end.";
                    }
                } else {
                    echo "Returned product: ".$product->get_name();
                }
                echo "<br/>";
            }
            echo "<br/>";
        }

        echo "<br/>";

        foreach($products as $product) {
            echo "Product name: ".$product->get_name()."<br/>";
            echo "Available: ";
            if($product->get_active_loan() === null) {
                echo "yes";
            } else {
                echo "no";
            }
            echo "<br/>";
        }

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
            print($this->build_user_table($hits['users']));
            $nohits = false;
        }
        if($hits['products']) {
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
                $this->subtitle = 'Produktdetaljer';
                break;
            case 'new':
                $this->subtitle = 'Ny produkt';
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
        return replace(array('id' => $this->product->get_id(),
                             'name' => $this->product->get_name(),
                             'serial' => $this->product->get_serial(),
                             'invoice' => $this->product->get_invoice(),
                             'tags' => $tags,
                             'info' => $info
        ), $this->fragments['product_details']);
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

?>
