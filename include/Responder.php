<?php
abstract class Responder {
    protected $fragments = array();
    
    public function __construct() {
        $this->fragments = get_fragments('./html/fragments.html');
    }

    final protected function escape_tags($tags) {
        foreach($tags as $key => $tag) {
            $tags[$key] = str_replace(array("'",
                                            '"'),
                                      array('&#39;',
                                            '&#34;'),
                                      strtolower($tag));
        }
        return $tags;
    }
    
    final protected function unescape_tags($tags) {
        foreach($tags as $key => $tag) {
            $tags[$key] = str_replace(array('&#39;',
                                            '&#34;'),
                                      array("'",
                                            '"'),
                                      strtolower($tag));
        }
        return $tags;
    }
}
?>
