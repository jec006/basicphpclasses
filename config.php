<?php
//start a session
session_start();

//database config
$db_user='';
$db_pass='';
$db_host='localhost';
$db_name='';
//Domain name, like jec006.com
$domain='';
//Site secret for use in hashing passwords
//WARNING: do not change this after you have users on your site 
//this will result in all users being unable to login
$secret = 'sdaf3456fvjlday5l$jlka2#@lksdeb-sd';

function getDB(){ 
  global $db_user;
  global $db_pass;
  global $db_host;
  global $db_name;
  
  return new mysqli($db_host, $db_user, $db_pass, $db_name);
}



// These are support functions for a site using classes 

/**
 *  A utility function to load the file for a class
 */
function load_class($name){
  require_once(get_class_path($name));
  $name = strtoupper(substr($name, 0, 1)) . substr($name, 1);
  return $name;
}
 
function get_class_path($name){
  return dirname(__FILE__) . '/' . $name . '.class.php';
}

function print_errors() {
  global $error_queue;
  echo '<ul class="error-queue">';
  foreach ($error_queue as $error) {
    echo '<li class="error">' . $error[0] . '<span class="code">' . $error[1] . '</span><li>';
  }
  echo '</ul>';
}
