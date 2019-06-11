<?php
class Success extends Result {
    public function __construct($message) {
        parent::__construct('success', $message);
    }
}
?>
