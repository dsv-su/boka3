<?php
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
?>
