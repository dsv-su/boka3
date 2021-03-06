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
                                 'new' => 'Ny artikel',
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
            $note = 'Tillgänglig';
            $status = $product->get_status();
            switch($status) {
                case 'discarded':
                    $discarded = format_date($product->get_discardtime());
                    $note = 'Skrotad '.$discarded;
                    break;
                case 'service':
                    $service = $product->get_active_service();
                    $note = 'På service sedan '
                                .format_date($service->get_starttime());
                    break;
                case 'on_loan':
                case 'overdue':
                    $loan = $product->get_active_loan();
                    $user = $loan->get_user();
                    $replacements = array('name' => $user->get_displayname(),
                                          'id'   => $user->get_id(),
                                          'page' => 'users');
                    $userlink = replace($replacements,
                                        $this->fragments['item_link']);
                    $note = 'Utlånad till '.$userlink;
                    if($loan->is_overdue()) {
                        $note .= ', försenad';
                    } else {
                        $note .= ', slutdatum '
                                .format_date($loan->get_endtime());
                    }
                    break;
            }
            $rows .= replace(array('status' => $status,
                                   'item_link' => $prodlink,
                                   'serial' => $product->get_serial(),
                                   'note' => $note),
                             $this->fragments['product_row']);
        }
        return replace(array('rows' => $rows),
                       $this->fragments['product_table']);
    }

    final protected function build_user_loan_table($loans) {
        $rows = '';
        foreach($loans as $loan) {
            $product = $loan->get_product();
            $prodlink = replace(array('id' => $product->get_id(),
                                      'name' => $product->get_name(),
                                      'page' => 'products'),
                                $this->fragments['item_link']);
            $status = $loan->get_status();
            $note = '';
            if($status !== 'inactive_loan') {
                $extend = format_date(default_loan_end(time()));
                $note = replace(array('id' => $product->get_id(),
                                      'end_new' => $extend),
                                $this->fragments['loan_extend_form']);
            }
            $start = $loan->get_starttime();
            $end = $loan->get_returntime();
            if(!$end) {
                $end = $loan->get_endtime();
            }
            $rows .= replace(array('status' => $status,
                                   'item_link' => $prodlink,
                                   'start_date' => format_date($start),
                                   'end_date' => format_date($end),
                                   'note' => $note),
                             $this->fragments['history_row']);
        }
        return replace(array('rows' => $rows,
                             'item' => 'Artikel'),
                       $this->fragments['history_table']);
    }

    final protected function build_seen_table($products, $inventory) {
        $rows = '';
        foreach($products as $product) {
            $prodid = $product->get_id();
            $prodlink = replace(array('id' => $prodid,
                                      'name' => $product->get_name(),
                                      'page' => 'products'),
                                $this->fragments['item_link']);
            $regtime = $inventory->get_product_regtime($product);
            $event = $product->get_historic_event($regtime);
            $status = '';
            $note = '';
            if(!$event) {
                $discardtime = $product->get_discardtime();
                if($discardtime && $discardtime < $regtime) {
                    $status = 'discarded';
                    $note = 'Skrotad '.format_date($discardtime);
                } else {
                    $status = 'available';
                }
            } else if($event instanceof Service) {
                $status = 'service';
                $note = 'På service sedan '.format_date($event->get_starttime());
                $returntime = $event->get_returntime();
                if($returntime) {
                    $note .= ', åter den '.format_date($returntime);
                }
            } else if($event instanceof Loan) {
                $user = $event->get_user();
                $userlink = replace(array('name' => $user->get_displayname(),
                                          'id' => $user->get_id(),
                                          'page' => 'users'),
                                    $this->fragments['item_link']);
                $status = 'on_loan';
                $note = 'Utlånad till '.$userlink;
                $returntime = $event->get_returntime();
                if($event->get_endtime() < $regtime) {
                    $status = 'overdue';
                    $note .= ', försenad';
                } else {
                    $note .= ', slutdatum '.format_date($event->get_endtime());
                }
                if($returntime) {
                    $note .= ', återlämnad '.format_date($returntime);
                }
            }
            $rows .= replace(array('status' => $status,
                                   'item_link' => $prodlink,
                                   'serial' => $product->get_serial(),
                                   'note' => $note),
                             $this->fragments['product_row']);
        }
        return replace(array('rows' => $rows),
                       $this->fragments['product_table']);

    }

    final protected function build_inventory_details($inventory,
                                                     $interactive = true) {
        $startdate = format_date($inventory->get_starttime());
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
        $unseen_table = 'Inga artiklar saknas.';
        if($unseen) {
            $unseen_table = $this->build_product_table($unseen);
        }
        $seen_table = 'Inga artiklar inventerade.';
        if($seen) {
            $seen_table = $this->build_seen_table($seen, $inventory);
        }
        return replace(array('start_date' => $startdate,
                             'total_count' => count($all_products),
                             'seen_count' => count($seen),
                             'hide' => $hidden,
                             'unseen_title' => $missing,
                             'unseen' => $unseen_table,
                             'seen' => $seen_table),
                       $this->fragments['inventory_do']);
    }
}
?>
