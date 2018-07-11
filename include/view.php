<?php

require_once('./include/db.php');
require_once('./include/functions.php');

class StartPage {
    public $title = '';
    public $content = '';
    private $menu = array();
    
    public function get_contents() {
        $menu_html = '';
        foreach($this->menu as $item) {
            $menu_html .= '<div class="menuitem">'.$item.'</div>';
        }
        return replace(array('¤pagetitle¤' => $this->title,
                             '¤contents¤'  => $this->content,
                             '¤menu¤'      => $menu_html),
                       file_get_contents('./html/base.html'));
    }
}

?>
