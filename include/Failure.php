<?php
class Failure extends Result {
    public function __construct($message) {
        parent::__construct('error', $message);
    }
}
?>
