<?php
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
            $enddate = format_date(default_loan_end(time()));
            $disabled = '';
            $loans = $this->user->get_loans('active');
            $loan_table = 'Inga pågående lån.';
            if($loans) {
                $loan_table = $this->build_user_loan_table($loans);
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
?>
