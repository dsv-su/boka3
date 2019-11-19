<?php
class Ajax extends Responder {
    private $action = '';
    
    public function __construct() {
        parent::__construct();
        if(isset($_GET['action'])) {
            $this->action = $_GET['action'];
        }
    }
    
    public function render() {
        $out = '';
        switch($this->action) {
            default:
                $out = new Success('ajax endpoint');
                break;
            case 'getfragment':
                $out = $this->get_fragment();
                break;
            case 'checkout':
                $out = $this->checkout_product();
                break;
            case 'return':
                $out = $this->return_product();
                break;
            case 'extend':
                $out = $this->extend_loan();
                break;
            case 'startinventory':
                $out = $this->start_inventory();
                break;
            case 'endinventory':
                $out = $this->end_inventory();
                break;
            case 'inventoryproduct':
                $out = $this->inventory_product();
                break;
            case 'updateproduct':
                $out = $this->update_product();
                break;
            case 'updateuser':
                $out = $this->update_user();
                break;
            case 'savetemplate':
                $out = $this->save_template();
                break;
            case 'deletetemplate':
                $out = $this->delete_template();
                break;
            case 'suggest':
                $out = $this->suggest();
                break;
            case 'suggestcontent':
                $out = $this->suggest_content();
                break;
            case 'discardproduct':
                $out = $this->discard_product();
                break;
            case 'toggleservice':
                $out = $this->toggle_service();
                break;
            case 'addattachment':
                $out = $this->add_attachment();
                break;
            case 'deleteattachment':
                $out = $this->delete_attachment();
                break;
        }
        print($out->toJson());
    }

    private function get_fragment() {
        $fragment = $_POST['fragment'];
        if(isset($this->fragments[$fragment])) {
            return new Success($this->fragments[$fragment]);
        }
        return new Failure("Ogiltigt fragment '$fragment'");
    }

    private function checkout_product() {
        $user = null;
        try {
            $user = new User($_POST['user'], 'name');
        } catch(Exception $e) {
            return new Failure('Ogiltigt användar-id.');
        }
        $product = null;
        try {
            $product = new Product($_POST['product'], 'serial');
        } catch(Exception $e) {
            return new Failure('Ogiltigt serienummer.');
        }
        try {
            Loan::create_loan($user, $product, $_POST['end']);
            return new Success($product->get_name() . 'utlånad.');
        } catch(Exception $e) {
            return new Failure('Artikeln är redan utlånad.');
        }
    }
    
    private function return_product() {
        $product = null;
        try {
            $product = new Product($_POST['serial'], 'serial');
        } catch(Exception $e) {
            return new Failure('Ogiltigt serienummer.');
        }
        $loan = $product->get_active_loan();
        if($loan) {
            $loan->end();
            $user = $loan->get_user();
            $userlink = replace(array('page' => 'users',
                                      'id'   => $user->get_id(),
                                      'name' => $user->get_displayname()),
                                $this->fragments['item_link']);
            $productlink = replace(array('page' => 'products',
                                         'id'   => $product->get_id(),
                                         'name' => $product->get_name()),
                                   $this->fragments['item_link']);
            $user = $loan->get_user();
            return new Success($productlink . ' åter från ' . $userlink);
        }
        return new Failure('Artikeln är inte utlånad.');
    }

    private function extend_loan() {
        $product = null;
        try {
            $product = new Product($_POST['product']);
        } catch(Exception $e) {
            return new Failure('Ogiltigt ID.');
        }
        $loan = $product->get_active_loan();
        if($loan) {
            $loan->extend($_POST['end']);
            return new Success('Lånet förlängt');
        }
        return new Failure('Lån saknas.');
    }
    
    private function start_inventory() {
        try {
            Inventory::begin();
            return new Success('Inventering startad.');
        } catch(Exception $e) {
            return new Failure('Inventering redan igång.');
        }
    }
    
    private function end_inventory() {
        $inventory = Inventory::get_active();
        if($inventory === null) {
            return new Failure('Ingen inventering pågår.');
        }
        $inventory->end();
        return new Success('Inventering avslutad.');
    }
    
    private function inventory_product() {
        $inventory = Inventory::get_active();
        if($inventory === null) {
            return new Failure('Ingen inventering pågår.');
        }
        $product = null;
        try {
            $product = new Product($_POST['serial'], 'serial');
        } catch(Exception $e) {
            return new Failure('Ogiltigt serienummer.');
        }
        $result = $inventory->add_product($product);
        if(!$result) {
            return new Failure('Artikeln är redan registrerad.');
        }
        return new Success('Artikeln registrerad.');
    }

    private function update_product() {
        $info = $_POST;
        $id = $info['id'];
        $name = $info['name'];
        $brand = $info['brand'];
        $serial = $info['serial'];
        $invoice = $info['invoice'];
        $tags = array();
        if(isset($info['tag'])) {
            $tags = $this->unescape_tags($info['tag']);
        }
        foreach(array('id',
                      'name',
                      'brand',
                      'serial',
                      'invoice',
                      'tag') as $key) {
            unset($info[$key]);
        }
        if(!$name) {
            return new Failure('Artikeln måste ha ett namn.');
        }
        if(!$serial) {
            return new Failure('Artikeln måste ha ett serienummer.');
        }
        if(!$invoice) {
            return new Failure('Artikeln måste ha ett fakturanummer.');
        }
        $product = null;
        if(!$id) {
            try {
                $temp = new Product($serial, 'serial');
                return new Failure(
                    'Det angivna serienumret finns redan på en annan artikel.');
            } catch(Exception $e) {}
            try {
                $product = Product::create_product($brand,
                                                   $name,
                                                   $invoice,
                                                   $serial,
                                                   $info,
                                                   $tags);
                $prodlink = replace(array('page' => 'products',
                                          'id' => $product->get_id(),
                                          'name' => $product->get_name()),
                                    $this->fragments['item_link']);
                return new Success("Artikeln '$prodlink' sparad.");
            } catch(Exception $e) {
                return new Failure($e->getMessage());
            }
        }
        $product = new Product($id);
        if($product->get_discardtime()) {
            return new Failure('Skrotade artiklar får inte modifieras.');
        }
        if($brand != $product->get_brand()) {
            $product->set_brand($brand);
        }
        if($name != $product->get_name()) {
            $product->set_name($name);
        }
        if($serial != $product->get_serial()) {
            try {
                $product->set_serial($serial);
            } catch(Exception $e) {
                return new Failure('Det angivna serienumret finns redan på en annan artikel.');
            }
        }
        if($invoice != $product->get_invoice()) {
            $product->set_invoice($invoice);
        }
        foreach($product->get_info() as $key => $prodvalue) {
            if(!isset($info[$key]) || !$info[$key]) {
                $product->remove_info($key);
                continue;
            }
            if($prodvalue != $info[$key]) {
                $product->set_info($key, $info[$key]);
            }
            unset($info[$key]);
        }
        foreach($info as $key => $invalue) {
            if($invalue) {
                $product->set_info($key, $invalue);
            }
        }
        foreach($product->get_tags() as $tag) {
            if(!in_array($tag, $tags)) {
                $product->remove_tag($tag);
                continue;
            }
            unset($tags[array_search($tag, $tags)]);
        }
        foreach($tags as $tag) {
            $product->add_tag($tag);
        }
        return new Success('Ändringarna sparade.');
    }
    
    private function update_user() {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $notes = $_POST['notes'];
        if(!$name) {
            return new Failure('Användarnamnet får inte vara tomt.');
        }
        $user = new User($id);
        if($user->get_name() != $name) {
            $user->set_name($name);
        }
        if($user->get_notes() != $notes) {
            $user->set_notes($notes);
        }
        return new Success('Ändringarna sparade.');
    }

    private function save_template() {
        $info = $_POST;
        $name = $info['template'];
        $tags = array();
        if(isset($info['tag'])) {
            $tags = $this->unescape_tags($info['tag']);
        }
        foreach(array('template',
                      'id',
                      'name',
                      'serial',
                      'invoice',
                      'brand',
                      'tag') as $key) {
            unset($info[$key]);
        }
        if(!$name) {
            return new Failure('Mallen måste ha ett namn.');
        }
        $template = null;
        try {
            $template = new Template($name, 'name');
        } catch(Exception $e) {
            $template = Template::create_template($name, $info, $tags);
            $name = $template->get_name();
            return new Success(
                "Aktuella fält och taggar har sparats till mallen '$name'.");
        }
        foreach($template->get_fields() as $field) {
            if(!isset($info[$field])) {
                $template->remove_field($field);
            }
        }
        $existingfields = $template->get_fields();
        foreach($info as $field) {
            if(!in_array($field, $existingfields)) {
                $template->add_field($field);
            }
        }
        foreach($template->get_tags() as $tag) {
            if(!in_array($tag, $tags)) {
                $template->remove_tag($tag);
            }
        }
        $existingtags = $template->get_tags();
        foreach($tags as $tag) {
            if(!in_array($tag, $existingtags)) {
                $template->add_tag($tag);
            }
        }
        $name = $template->get_name();
        return new Success("Mallen '$name' uppdaterad.");
    }

    private function delete_template() {
        try {
            $template = $_POST['template'];
            Template::delete_template($template);
            $name = ucfirst(strtolower($template));
            return new Success("Mallen '$name' har raderats.");
        } catch(Exception $e) {
            return new Failure('Det finns ingen mall med det namnet.');
        }
    }
    
    private function suggest() {
        return new Success(suggest($_POST['type']));
    }

    private function suggest_content() {
        return new Success(suggest_content($_POST['fieldname']));
    }

    private function discard_product() {
        $product = new Product($_POST['id']);
        if(!$product->get_discardtime()) {
            if($product->get_active_loan()) {
                return new Failure('Artikeln har ett aktivt lån.<br/>'
                                  .'Lånet måste avslutas innan artikeln skrotas.');
            }
            $product->discard();
            return new Success('Artikeln skrotad.');
        } else {
            return new Failure('Artikeln är redan skrotad.');
        }
    }

    private function toggle_service() {
        $product = new Product($_POST['id']);
        try {
            $product->toggle_service();
            return new Success('Service-status uppdaterad.');
        } catch(Exception $e) {
            return new Failure('Service kan inte registreras '
                              .'på den här artikeln nu.');
        }
    }

    private function add_attachment() {
        try {
            $product = new Product($_POST['id']);
            $uploadfile = $_FILES['uploadfile'];
            $attach = Attachment::create($uploadfile, $product->get_id());
            $date = format_date($attach->get_uploadtime());
            $fragment = replace(array('name' => $attach->get_filename(),
                                      'id' => $attach->get_id(),
                                      'date' => $date),
                                $this->fragments['attachment']);
            return new Success($fragment);
        } catch(Exception $e) {
            return new Failure($e->getMessage());
        }
    }

    private function delete_attachment() {
        $attach = new Attachment($_POST['id']);
        try {
            $attach->delete();
            return new Success('');
        } catch(Exception $e) {
            return new Failure($e->getMessage());
        }
    }
}
?>
