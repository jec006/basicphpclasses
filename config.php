<?php
//start a session
session_start();

//database config
$db_user='imagemap';
$db_pass='34dvfda93';
$db_host='localhost';
$db_name='imagemap';
//Domain name
$domain='imagemap.jec006.com';
//Site secret for use in hashing passwords
//WARNING: do not change this after you have users on your site 
//this will result in all users being unable to login
$secret = 'sade%4345ghjyj678#RGH$#@!fsdagr2sdh';

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
  //get in the right dir
  $cur = getcwd();
  chdir('/opt/development/ladd/');
  
  if(include_once(get_class_path($name))){
    $name = strtoupper(substr($name, 0, 1)) . substr($name, 1);
    chdir($cur);
    $obj = new $name();
    return $obj;
  } else {
    global $error_queue;
    $error_queue[] = array('Class File Not Found', 'classes/' . $name . '/' . $name.'.class.php');
    chdir($cur);    
    return false;
  }
}
 
function get_class_path($name){
  return 'controllers/classes/' . $name . '/' . $name . '.class.php';
}
