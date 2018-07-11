<?php

require_once("./config.php");

/*
   Takes an html file containing named fragments.
   Returns an associative array on the format array[name]=>fragment.
   
   Fragments are delimited like this:
   
   ¤¤ name 1 ¤¤
   fragment 1
   ¤¤ name 2 ¤¤
   fragment 2
   ¤¤ name 3 ¤¤
   fragment 3

   The first delimiter and name ('¤¤ name 1 ¤¤' in the above example) can
   be omitted, in which case the first fragment will be assigned the
   name 'base'. All other fragments must be named.

   Throws an exception if:
   - any fragment except the first is missing a name
   - two (or more) fragments share a name
 */
function get_fragments($infile) {
    $out = array();
    $name = '';
    $current_fragment = '';

    $filecontents = file($infile);
    $iter = 0;
    foreach($filecontents as $line) {
        if(strpos($line, '¤¤') === 0) {
            if($iter != 0) {
                $out = try_adding($name, $current_fragment, $out, $infile);
            }
            $name = trim($line, "\t\n\r ¤");
            $current_fragment = '';
        } else {
            $current_fragment .= $line;
        }
        $iter++;
    }
    return try_adding($name, $current_fragment, $out, $infile);
}

function try_adding($key, $value, $array, $filename) {
    if(array_key_exists($key, $array)) {
        throw new Exception('There is already a fragment with that name in '.$filename);
    } else if($key === '') {
        throw new Exception('There is an unnamed fragment in '.$filename);
    }
    
    $array[$key] = $value;

    return $array;
}

/*
   Takes an associative array and a string.
   Returns a string.

   Replaces each occurrence of each array key in the input string
   with the associated array value, and returns the result.
 */
function replace($assoc_arr, $subject) {
    $keys = array();
    $values = array();

    foreach($assoc_arr as $key => $value) {
        $keys[] = $key;
        $values[] = $value;
    }

    return str_replace($keys, $values, $subject);
}

?>
