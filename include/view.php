<?php

require_once('./include/db.php');
require_once('./include/ldap.php');
require_once('./include/functions.php');

$fragments = get_fragments('./html/fragments.html');

function make_page($action) {
    switch($action) {
        case 'home':
        default:
            return new StartPage();
        case 'search':
            return new SearchPage();
    }
}

abstract class Page {
    protected abstract function render_body();

    protected $action = 'home';
    protected $title = "Boka2";
    protected $subtitle = '';
    protected $menuitems = array('home' => 'Start',
                                 'products' => 'Produkter',
                                 'users' => 'Användare',
                                 'checkout' => 'Låna ut');
    private $template_parts = array();

    public function __construct() {
        $this->template_parts = get_fragments('./html/base.html');
        if(isset($_GET['action'])) {
            $this->action = $_GET['action'];
        }
        if(isset($this->menuitems[$this->action])) {
            $this->subtitle = $this->menuitems[$this->action];
        }
    }
    
    public function render() {
        $this->render_head();
        $this->render_body();
        $this->render_foot();
    }
    
    final private function render_head() {
        global $fragments;
        
        $headtitle = $this->title;
        $pagetitle = $this->title;
        if($this->subtitle) {
            $headtitle .= ' - '. $this->subtitle;
            $pagetitle = $this->subtitle;
        }
        print(replace(
            array('title' => $headtitle,
                  'menu' => $this->build_menu()),
            $this->template_parts['head']
        ));
        
        print(replace(array('title' => $pagetitle),
                      $fragments['title']));
            
    }

    private function build_menu() {
        $menu = '';
        foreach($this->menuitems as $action => $title) {
            $active = '';
            if($this->action == $action) {
                $active = 'active';
            }
            $menu .= replace(array('title' => $title,
                                   'action' => $action,
                                   'active' => $active),
                             $this->template_parts['menuitem']);
        }
        return $menu;
    }
    
    final private function render_foot() {
        print($this->template_parts['foot']);
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
        $this->subtitle = 'Sökresultat';
        if(isset($_GET['q'])) {
            $this->query = $_GET['q'];
        }
    }

    private function do_search() {
        $out = array('users' => array(),
                     'products' => array(),
                     'loans' => array());
        if(!$this->query) {
            return $out;
        }
        $out['users'] = search_users($this->query);
        $out['products'] = search_products($this->query);
        return $out;
    }
    
    protected function render_body() {
        $hits = $this->do_search();
        if($hits['users']) {
            print(build_user_table($hits['users']));
        }
        if($hits['products']) {
            print(build_product_table($hits['products']));
        }
    }
}

function build_user_table($users) {
    global $fragments;
    
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
        $rows .= replace($replacements, $fragments['user_row']);
    }
    return replace(array('title' => 'Användare',
                         'rows' => $rows),
                   $fragments['user_table']);
}

function build_product_table($products) {
    global $fragments;

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
        }
        $replacements['available'] = $available;
        $rows .= replace($replacements, $fragments['product_row']);
    }
    return replace(array('title' => 'Produkter',
                         'rows' => $rows),
                   $fragments['product_table']);
}

?>
