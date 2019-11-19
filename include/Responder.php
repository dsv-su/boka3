<?php
abstract class Responder {
    protected $fragments = array();
    
    public function __construct() {
        $this->fragments = get_fragments('./html/fragments.html');
    }
    
    final protected function escape_tags($tags) {
        foreach($tags as $key => $tag) {
            $tags[$key] = $this->escape_string(strtolower($tag));
        }
        return $tags;
    }
    
    final protected function unescape_tags($tags) {
        foreach($tags as $key => $tag) {
            $tags[$key] = $this->unescape_string(strtolower($tag));
        }
        return $tags;
    }
    
    final protected function escape_string($string) {
        return str_replace(array("'",
                                 '"'),
                           array('&#39;',
                                 '&#34;'),
                           $string);
    }

    final protected function unescape_string($string) {
        return str_replace(array('&#39;',
                                 '&#34;'),
                           array("'",
                                 '"'),
                           $string);
    }
}
?>
