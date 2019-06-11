<?php
class Ldap {
    private $conn;
    private $base_dn = "dc=su,dc=se";
    
    public function __construct() {
        $this->conn = ldap_connect('ldaps://ldap.su.se');
        ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_bind($this->conn);
    }

    private function search($term, ...$attributes) {
        $result = ldap_search($this->conn, $this->base_dn, $term, $attributes);
        return ldap_get_entries($this->conn, $result);
    }
    
    public function get_user($uid) {
        $data = $this->search("uid=$uid", 'cn', 'uid');
        if($data['count'] !== 1) {
            throw new Exception("LDAP search for '$uid' did not return exactly one result");
        }
        return $data[0]['cn'][0];
    }

    public function get_user_email($uid) {
        $data = $this->search("uid=$uid", 'mail', 'uid');
        if($data['count'] !== 1) {
            throw new Exception("LDAP search for '$uid' did not return exactly one result");
        }
        return $data[0]['mail'][0];
    }

    public function search_user($uid) {
        $data = $this->search("uid=$uid", 'cn', 'uid');
        $out = array();
        foreach($data as $result) {
            if(isset($result['uid'])) {
                $out[$result['uid'][0]] = $result['cn'][0];
            }
        }
        return $out;
    }
}
?>
