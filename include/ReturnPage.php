<?php
class ReturnPage extends Page {
    protected function render_body() {
        print($this->fragments['return_page']);
    }
}
?>
