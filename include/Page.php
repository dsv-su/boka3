<?php
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
?>
