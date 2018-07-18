<?php

require_once('./include/db.php');
require_once('./include/ldap.php');
require_once('./include/functions.php');

function make_page($action) {
    switch($action) {
        case 'home':
        default:
            return new StartPage();
    }
}

abstract class Page {
    protected abstract function render_body();

    protected $action = 'home';
    protected $title = "Boka2";
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
    }
    
    public function render() {
        $this->render_head();
        $this->render_body();
        $this->render_foot();
    }
    
    final private function render_head() {
        print(replace(
            array('¤title¤' => $this->title,
                  '¤menu¤' => $this->build_menu()),
            $this->template_parts['head']
        ));
    }

    private function build_menu() {
        $menu = '';
        foreach($this->menuitems as $action => $title) {
            $active = '';
            if($this->action == $action) {
                $active = 'active';
            }
            $menu .= replace(array('¤title¤' => $title,
                                   '¤action¤' => $action,
                                   '¤active¤' => $active),
                             $this->template_parts['menuitem']);
        }
        return $menu;
    }
    
    final private function render_foot() {
        print($this->template_parts['foot']);
    }
}

class StartPage extends Page {
    protected function render_body() {
        foreach(get_ids('user') as $userid) {
            $user = new User($userid);
            $users[] = $user;
        }

        foreach(get_ids('product') as $prodid) {
            $product = new Product($prodid);
            $products[] = $product;
        }

        $ldap = new Ldap();

        foreach($users as $user) {
            echo "User: ".$ldap->find_user($user->get_name())."<br/>";
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
            if($product->is_available()) {
                echo "yes";
            } else {
                echo "no";
            }
            echo "<br/>";
        }

    }
}

?>
