<?php
class Template {
    private $id = 0;
    private $name = '';
    private $fields = array();
    private $tags = array();
    
    public static function create_template(
        $name = '',
        $fields = array(),
        $tags = array()
    ) {
        begin_trans();
        try {
            $stmt = 'insert into `template`(`name`) values (?)';
            $ins_prod = prepare($stmt);
            bind($ins_prod, 's', strtolower($name));
            execute($ins_prod);
            $template = new Template($name, 'name');
            foreach(array_keys($fields) as $field) {
                $template->add_field($field);
            }
            foreach($tags as $tag) {
                $template->add_tag($tag);
            }
            commit_trans();
            return $template;
        } catch(Exception $e) {
            revert_trans();
            throw $e;
        }
    }

    public static function delete_template($name) {
        $template = new Template($name, 'name');
        foreach($template->get_fields() as $field) {
            $template->remove_field($field);
        }
        foreach($template->get_tags() as $tag) {
            $template->remove_tag($tag);
        }
        $delete = prepare('delete from `template` where `id`=?');
        bind($delete, 'i', $template->get_id());
        execute($delete);
        return true;
    }
    
    public function __construct($clue, $type = 'id') {
        switch($type) {
            case 'id':
                $this->id = $clue;
                $search = prepare('select `name` from `template`
                                   where `id`=?');
                bind($search, 'i', $this->id);
                execute($search);
                $result = result_single($search);
                if($result === null) {
                    throw new Exception('Invalid id');
                }
                $this->name = $result['name'];
                break;
            case 'name':
                $this->name = strtolower($clue);
                $search = prepare('select `id` from `template`
                                   where `name`=?');
                bind($search, 's', $this->name);
                execute($search);
                $result = result_single($search);
                if($result === null) {
                    throw new Exception('Invalid name.');
                }
                $this->id = $result['id'];
                break;
            default:
                throw new Exception('Invalid type.');
        }
        $this->update_fields();
        $this->update_tags();
    }

    public function get_id() {
        return $this->id;
    }
    
    public function get_name() {
        return ucfirst($this->name);
    }

    public function set_name($name) {
        $update = prepare('update `template` set `name`=? where `id`=?');
        bind($update, 'si', $name, $this->id);
        execute($update);
        $this->name = $name;
        return true;
    }
    
    private function update_fields() {
        $get = prepare('select `field` from `template_info`
                        where `template`=? order by `field`');
        bind($get, 'i', $this->id);
        execute($get);
        $fields = array();
        foreach(result_list($get) as $row) {
            $fields[] = $row['field'];
        }
        $this->fields = $fields;
        return true;
    }
    
    private function update_tags() {
        $get = prepare('select * from `template_tag`
                        where `template`=? order by `tag`');
        bind($get, 'i', $this->id);
        execute($get);
        $newtags = array();
        foreach(result_list($get) as $row) {
            $newtags[] = $row['tag'];
        }
        $this->tags = $newtags;
        return true;
    }

    public function get_fields() {
        return $this->fields;
    }

    public function add_field($field) {
        $find = prepare('select * from `template_info`
                         where `template`=? and `field`=?');
        bind($find, 'is', $this->id, $field);
        execute($find);
        if(result_single($find) === null) {
            $update = prepare('insert into `template_info`(`template`, `field`)
                                   values (?, ?)');
            bind($update, 'is', $this->id, $field);
            execute($update);
            $this->update_fields();
        }
        return true;
    }
    
    public function remove_field($field) {
        $find = prepare('select * from `template_info`
                         where `template`=? and `field`=?');
        bind($find, 'is', $this->id, $field);
        execute($find);
        if(result_single($find) === null) {
            return true;
        }
        $update = prepare('delete from `template_info`
                           where `field`=? and `template`=?');
        bind($update, 'si', $field, $this->id);
        execute($update);
        $this->update_fields();
        return true;
    }

    public function get_tags() {
        return $this->tags;
    }

    public function add_tag($tag) {
        if(!$tag) {
            return true;
        }
        $find = prepare('select * from `template_tag`
                         where `template`=? and `tag`=?');
        bind($find, 'is', $this->id, $tag);
        execute($find);
        if(result_single($find) === null) {
            $update = prepare('insert into `template_tag`(`tag`, `template`)
                                   values (?, ?)');
            bind($update, 'si', $tag, $this->id);
            execute($update);
            $this->update_tags();
        }
        return true;
    }
    
    public function remove_tag($tag) {
        $find = prepare('select * from `template_tag`
                         where `template`=? and `tag`=?');
        bind($find, 'is', $this->id, $tag);
        execute($find);
        if(result_single($find) === null) {
            return true;
        }
        $update = prepare('delete from `template_tag`
                           where `tag`=? and `template`=?');
        bind($update, 'si', $tag, $this->id);
        execute($update);
        $this->update_tags();
        return true;
    }
}
?>
