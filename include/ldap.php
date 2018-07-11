<?php

class Ldap {
    private $conn;
    
    public function __construct() {
        $this->conn = ldap_connect('ldaps://ldap.su.se');
        ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_bind($this->conn);
    }
    
    public function find_user($uid) {
        $result = ldap_search($this->conn, "dc=su,dc=se", "uid=$uid");
        $data = ldap_get_entries($this->conn, $result);
        if($data['count'] !== 1) {
            throw new Exception("LDAP search for '$uid' returns more than one result"); 
        }
        return $data[0]['cn'][0];
    }
}
?>
