<?php
require_once('./include/view.php');

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
        $reminder_template = "¤name¤, försenad sedan ¤due¤\n";
        $message_template = <<<EOF
Hej ¤name¤

Vi vill påminna dig om att ditt lån av följande ¤product¤ har gått ut:

¤list¤

Vänligen återlämna dem till Helpdesk så snart som möjligt, alternativt kontakta
oss för att få lånet förlängt.

Mvh
DSV Helpdesk
helpdesk@dsv.su.se
08 - 16 16 48
EOF;

        $overdue_count = count($loans);
        $reminder_list = '';
        $late = 'försenat';
        $product = 'artikel';
        if($count > 1) {
            $late = 'försenade';
            $product = 'artiklar';
        }
        foreach($loans as $loan) {
            $replacements = array('name' => $loan->get_product()->get_name(),
                                  'due'  => $loan->get_endtime());
            $reminder_list .= replace($replacements, $reminder_template);
        }

        $subject = replace(array('count' => $overdue_count,
                                 'late'  => $late), $subject_template);
        $message = replace(array('name'    => $user->get_displayname(),
                                 'list'    => $reminder_list,
                                 'product' => $product), $message_template);

        try {
            mb_send_mail($user->get_email(),
                         $subject,
                         $message,
                         'From: noreply-boka@dsv.su.se');
        } catch(Exception $e) {
            mb_send_mail('root@dsv.su.se',
                         "Kunde inte skicka påminnelse",
                         "Påminnelse kunde inte skickas till "
                         .$user->get_name());
        }
    }
}


?>
