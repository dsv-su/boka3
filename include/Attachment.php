<?php
class Attachment {
    private $id;
    private $product;
    private $filename;
    private $uploadtime;

    public static function create($file, $prodid) {
        begin_trans();
        try {
            if(!isset($file['error']) || is_array($file['error'])) {
                throw new Exception('Ogiltigt anrop.');
            }
            switch($file['error']) {
                case UPLOAD_ERR_OK:
                    break;
                case UPLOAD_ERR_NO_FILE:
                    throw new Exception('Ingen fil skickades.');
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    throw new Exception('Filen är för stor.');
                default:
                    throw new Exception('Ett okänt fel har inträffat.');
            }
            $filename = $file['name'];
            $insert = prepare('insert into `attachment`
                                   (`product`, `filename`, `uploadtime`)
                                   values (?, ?, ?)');
            bind($insert, 'isi', $prodid, $filename, time());
            execute($insert);
            $attachid = $insert->insert_id;
            global $files_dir;
            $savepath = $files_dir;
            if(substr($savepath, -1) !== '/') {
                $savepath .= '/';
            }
            $savepath .= $attachid;
            $tmp_name = $file['tmp_name'];
            if(file_exists($savepath)) {
                throw new Exception('Filens plats är upptagen. '
                                   .'Det här borde aldrig inträffa.');
            }
            if(!move_uploaded_file($tmp_name, $savepath)) {
                throw new Exception('Filen kunde inte sparas.');
            }
            commit_trans();
            return new Attachment($attachid);
        } catch(Exception $e) {
            revert_trans();
            throw $e;
        }
    }

    public function __construct($id) {
        $search = prepare('select * from `attachment` where `id`=?');
        bind($search, 'i', $id);
        execute($search);
        $result = result_single($search);
        if($result === null) {
            throw new Exception('Attachment does not exist.');
        }
        $this->id = $result['id'];
        $this->product = $result['product'];
        $this->filename = $result['filename'];
        $this->uploadtime = $result['uploadtime'];
    }

    public function delete() {
        $delete = prepare('update `attachment` set `deletetime`=?
                           where `id`=?');
        bind($delete, 'ii', time(), $this->get_id());
        execute($delete);
        $path = $this->get_filepath();
        if(file_exists($path)) {
            unlink($path);
        }
        return true;
    }

    public function get_id() {
        return $this->id;
    }
    
    public function get_product() {
        return new Product($this->product);
    }

    public function get_filename() {
        return $this->filename;
    }

    public function get_uploadtime() {
        return $this->uploadtime;
    }

    public function get_filepath() {
        global $files_dir;
        $path = $files_dir;
        if(substr($path, -1) !== '/') {
            $path .= '/';
        }
        $path .= $this->get_id();
        if(!file_exists($path)) {
            throw new Exception('Filen har försvunnit.');
        }
        return $path;
    }
}
?>
