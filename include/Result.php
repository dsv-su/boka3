<?php
class Result {
    private $type = '';
    private $message = '';

    public function __construct($type, $message) {
        $this->type = $type;
        $this->message = $message;
    }

    public function toJson() {
        return json_encode(array(
            'type' => $this->type,
            'message' => $this->message
        ));
    }
}
?>
