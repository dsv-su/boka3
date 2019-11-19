<?php
class Download extends Responder {
    private $attachment;
    
    public function __construct() {
        parent::__construct();
        if(isset($_GET['id'])) {
            $this->attachment = new Attachment($_GET['id']);
        }
    }

    public function render() {
        $filename = $this->attachment->get_filename();
        $filepath = $this->attachment->get_filepath();
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Expires: 0');
        header('Cache-Control: no-cache');
        header('Content-length: '.filesize($filepath));
        readfile($filepath);
        exit(0);
    }
}
?>
