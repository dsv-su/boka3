<?php
spl_autoload_register(function ($class) {
    include('./include/'.$class.'.php');
});
require('./config.php');
require('./include/functions.php');

header('Content-Type: text/html; charset=UTF-8');

$cron = new Cron(time());
$cron->run();

class Cron {
    private $kvs;
    private $now = 0;
    public function __construct($now) {
        $this->now = $now;
        $this->kvs = new Kvs();
    }

    public function run() {
        $lastrun = $this->kvs->get_value('lastrun');
        $interval = 3600*24; //1 day in seconds
        
        if($lastrun && $this->now - $lastrun < $interval) {
            return;
        }
        $this->kvs->set_key('lastrun', $this->now);
        
        $users = get_items('user');
        foreach($users as $user) {
            $this->check_loans($user);
        }
    }

    private function check_loans($user) {
        $overdue = $user->get_overdue_loans();
        if($overdue) {
            $this->send_reminder($user, $overdue);
        }
    }
    
    private function send_reminder($user, $loans) {
        $subject_template = "DSV Helpdesk: Du har ¤count¤ ¤late¤ lån";
        $reminder_template_sv = "¤name¤, försenad sedan ¤due¤\n";
        $reminder_template_en = "¤name¤, late since ¤due¤\n";
        $message_template = <<<EOF
Hej ¤name¤

Vi vill påminna dig om att ditt lån har gått ut på följande ¤product_sv¤:

¤list_sv¤

Vänligen återlämna ¤it_sv¤ till Helpdesk så snart som möjligt, alternativt svara på det här meddelandet för att förlänga ¤loan_sv¤.

----

We would like to remind you that your loan has expired on the following ¤product_en¤:

¤list_en¤

Please return ¤it_en¤ to the Helpdesk as soon as possible, or reply to this message in order to extend the ¤loan_en¤.

Mvh
DSV Helpdesk
helpdesk@dsv.su.se
08 - 16 16 48
EOF;

        $overdue_count = count($loans);
        $reminder_list_sv = '';
        $reminder_list_en = '';
        $late = 'försenat';
        $product_sv = 'artikel';
        $product_en = 'product';
        $it_sv = 'den';
        $it_en = 'it';
        $loan_sv = 'lånet';
        $loan_en = 'loan';
        if($overdue_count > 1) {
            $late = 'försenade';
            $product_sv = 'artiklar';
            $product_en = 'products';
            $it_sv = 'dem';
            $it_en = 'them';
            $loan_sv = 'lånen';
            $loan_en = 'loans';
        }
        foreach($loans as $loan) {
            $replacements = array('name' => $loan->get_product()->get_name(),
                                  'due'  => format_date($loan->get_endtime()));
            $reminder_list_sv .= replace($replacements, $reminder_template_sv);
            $reminder_list_en .= replace($replacements, $reminder_template_en);
        }

        $subject = replace(array('count' => $overdue_count,
                                 'late'  => $late), $subject_template);
        $message = replace(array('name'       => $user->get_displayname(),
                                 'list_sv'    => $reminder_list_sv,
                                 'product_sv' => $product_sv,
                                 'it_sv'      => $it_sv,
                                 'loan_sv'    => $loan_sv,
                                 'list_en'    => $reminder_list_en,
                                 'product_en' => $product_en,
                                 'it_en'      => $it_en,
                                 'loan_en'    => $loan_en),
                           $message_template);

        try {
            mb_send_mail($user->get_email(),
                         $subject,
                         $message,
                         'From: helpdesk@dsv.su.se');
        } catch(Exception $e) {
            mb_send_mail('root@dsv.su.se',
                         "Kunde inte skicka påminnelse",
                         "Påminnelse kunde inte skickas till "
                         .$user->get_name());
        }
    }
}
?>
