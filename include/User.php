<?php
class User {
    private $id = 0;
    private $name = '';
    private $notes = '';
    private $ldap = null;
    
    public static function create_user($name) {
        $ins_user = prepare('insert into `user`(`name`) values (?)');
        bind($ins_user, 's', $name);
        execute($ins_user);
        return new User($ins_user->insert_id);
    }

    public function __construct($clue, $type = 'id') {
        $find = null;
        switch($type) {
            case 'id':
                $find = prepare('select `id` from `user` where `id`=?');
                bind($find, 'i', $clue);
                break;
            case 'name':
                $find = prepare('select `id` from `user` where `name`=?');
                bind($find, 's', $clue);
                break;
            default:
                throw new Exception('Invalid type');
        }
        execute($find);
        $id = result_single($find)['id'];
        if($id === null) {
            throw new Exception("Invalid username '$clue'");
        }
        $this->id = $id;
        $this->update_fields();
        $this->ldap = new Ldap();
    }
    
    private function update_fields() {
        $get = prepare('select * from `user` where `id`=?');
        bind($get, 'i', $this->id);
        execute($get);
        $user = result_single($get);
        $this->name = $user['name'];
        $this->notes = $user['notes'];
        return true;
    }

    public function matches($terms) {
        foreach($terms as $field => $values) {
            $matchvalues = array();
            if($field == 'name') {
                $matchvalues[] = $this->name;
                $matchvalues[] = $this->get_displayname();
            } else if(property_exists($this, $field)) {
                $matchvalues[] = $this->$field;
            } else if($field == 'fritext') {
                $matchvalues[] = $this->name;
                $matchvalues[] = $this->get_displayname();
                $matchvalues[] = $this->notes;
            } else {
                return false;
            }
            if(!match($values, $matchvalues)) {
                return false;
            }
        }
        return true;
    }

    public function get_displayname() {
        try {
            return $this->ldap->get_user($this->name);
        } catch(Exception $e) {
            return 'Ej i SUKAT';
        }
    }

    public function get_email() {
        try {
            return $this->ldap->get_user_email($this->name);
        } catch(Exception $e) {
            return 'Mailadress saknas';
        }
    }

    public function get_id() {
        return $this->id;
    }
    
    public function get_name() {
        return $this->name;
    }
    
    public function set_name($newname) {
        $update = prepare('update `user` set `name`=? where `id`=?');
        bind($update, 'si', $newname, $this->id);
        execute($update);
        $this->name = $newname;
        return true;
    }

    public function get_notes() {
        return $this->notes;
    }
    
    public function set_notes($newnotes) {
        $update = prepare('update `user` set `notes`=? where `id`=?');
        bind($update, 'si', $newnotes, $this->id);
        execute($update);
        $this->notes = $newnotes;
        return true;
    }

    public function get_loans($type = 'both') {
        $statement = 'select `id` from `event`
                          inner join `loan` on
                          `event`.`id` = `loan`.`event`
                      where `user`=?';
        switch($type) {
            case 'active':
                $statement .= ' and `returntime` is null';
                break;
            case 'inactive':
                $statement .= ' and `returntime` is not null';
                break;
            case 'both':
                break;
            default:
                $err = "$type is not a valid argument. Valid arguments are active, inactive, both.";
                throw new Exception($err);
                break;
        }
        $statement .= ' order by `starttime` desc';
        $get = prepare($statement);
        bind($get, 'i', $this->id);
        execute($get);
        $loans = array();
        foreach(result_list($get) as $row) {
            $loans[] = new Loan($row['id']);
        }
        return $loans;
    }

    public function get_overdue_loans() {
        $overdue = array();
        foreach($this->get_loans('active') as $loan) {
            if($loan->is_overdue()) {
                $overdue[] = $loan;
            }
        }
        return $overdue;
    }
}
?>
