<?php
/**
 *  Requires that the SessionController class be available 
 *  and config.php be loaded
 */
 
 /* DB TABLE FOR USERS
 
  CREATE TABLE IF NOT EXISTS `users` (
    `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `password` varchar(1024) COLLATE utf8_unicode_ci NOT NULL,
    `uid` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `reset_key` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
    PRIMARY KEY (`uid`),
    UNIQUE KEY `email` (`email`),
    UNIQUE KEY `name` (`name`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

 */

class LoginController {
  private $tablename = 'users';
  private $sessionController;
  private $db;
  private $user = FALSE;
  
  public function __construct(){
    load_class('sessionController');
    $this->sessionController = new SessionController();
    $this->db = getDB();
  }
  
  /**
   *  Check if the current user has a session - i.e. is logged in
   */
  public function checkLoggedIn(){
    $cookie = $this->getCookie();
    global $errors_queue, $messages;
    if($cookie && $this->checkSession($cookie)){
      $values = $this->getCookieValues($cookie);
      $this->user = $this->loadById($values[1]);
      return TRUE;
    } else if(isset($_GET['reset']) && isset($_GET['key']) && isset($_GET['user'])) {
      if($this->checkResetLink($_GET['user'], $_GET['key'])){
        //need to load the current user and give them a session
        $this->user = $this->loadById($_GET['user']);
        $session = $this->sessionController->create($this->user);
            
        $this->user['sid'] = $session['sid'];
        $this->setCookie();
        $this->destroyResetLink();

        $messages[] = 'Please update your password.';
        //clear out the get variables
        redirect('index.php');
      } else {
        $errors_queue[] = 'Invalid Reset Link';
        return FALSE;
      }    
    } else {
      return FALSE;
    }
  }
  
/**
 *  Login in a user
 */
 public function login($name, $pass){
   $this->user = $this->loadByName($name);
   if($this->user){
     $session = $this->sessionController->create($this->user);
         
     $this->user['sid'] = $session['sid'];
     $this->setCookie();
     global $messages;
     $messages[] = 'Logged in Successfully';
     return TRUE;
   } else {
     global $errors_queue;
     $errors_queue[] = 'Login failed';
     return FALSE;
   }
 }
  
/**
 *  Log the user out and destroy their session
 */
 public function logout(){
   if($this->user){
     $this->sessionController->deleteSession();
     $this->deleteCookie();
     return TRUE;
   } else {
     return FALSE;
   }
 }  
 
 public function getCurrentUID(){
   return isset($this->user['uid']) ? $this->user['uid'] : FALSE;
 }
  
/**
 *  Return list items for rendering
 *  $num_items - the number of items to get
 *  $offset - the first item to get - 0 indexed
 */
  public function getList($num_items=10, $offset=0){
    $offset = $this->db->real_escape_string($offset);
    $num_items = $this->db->real_escape_string($num_items);

    $query = "SELECT * FROM $this->tablename ORDER BY name ASC LIMIT $offset, $num_items";
    $result = $this->db->query($query);

    $users = array();
    
    if($result){
      while(($user = $result->fetch_assoc())){
        $users[] = $user;
      }
      return $users;
    } else {
      global $errors_queue;
      $errors_queue[] = $this->db->error;
      return FALSE;
    }
  }
  
/**
 *  Save a user object - should be something like:
 *  [
 *    'uid' = ony set if its an update
 *    'name' = the username of the user
 *    'email' = the email of the user
 *    'password' = the password
 *    'hashed' = if the password is hashed set to TRUE
 *  ]
 */
  public function saveUser($user){
    //Only allow logged in users to update self or other users
    if(!$this->user){
      global $errors_queue;
      $errors_queue[] = 'User not logged in - You may not update users';
      return FALSE;
    }
    
    if(isset($user['uid']) && !empty($user['uid'])){
      //if there is a session id, remove it before trying to save
      if(isset($user['sid'])){
        unset($user['sid']);
      }
      
      if(empty($user['hashed'])){
        $user['password'] = $this->hashPassword($user['password']);
      }
            
      //its an update
      $query = "UPDATE $this->tablename SET ";
      
      $uid = $this->db->real_escape_string($user['uid']);
      unset($user['uid']);
      
      foreach($user as $key=>$value){
        $query .= $this->db->real_escape_string($key) . "='" . $this->db->real_escape_string($value) . "' ";
      }
      
      $query .= "WHERE uid='$uid'";
      if($this->db->query($query)){
        $user['uid'] = $uid;
        return $user;
      } else {
        global $errors_queue;
        $errors_queue[] = $this->db->error;
        return FALSE;
      }
    } else {
      if(!isset($user['hashed']) || !$user['hashed']){
        $user['password'] = $this->hashPassword($user['password']);
      }
      $query = "INSERT INTO $this->tablename (";
      //create a columns array and a values array - each being escaped of course 
      $columns = array();
      $values = array();
      foreach($user as $key=>$value){
        $columns[] = $this->db->real_escape_string($key);
        $values[] = $this->db->real_escape_string($value);
      }
      

      
      $query .= implode(', ', $columns) . ') VALUES (\'' . implode("', '", $values) . '\')';
      if($this->db->query($query)){
        $user['uid'] = $this->db->insert_id;
        return $user;
      } else {
        global $errors_queue;
        $errors_queue[] = $this->db->error;
        $errors_queue[] = 'Query: ' . $query;
        return FALSE;
      }
    }
  }
  
/**
 *  Deletes the user with the given id
 *  $user must have 'uid' set  
 */
  public function deleteUser($user){
    if(isset($user['uid']) && !empty($user['uid'])){
      $id = $this->db->real_escape_string($user['uid']);
      
      if($this->db->query("DELETE FROM $this->tablename WHERE uid=$id")){
        global $messages; 
        $messages[] = 'User successfully deleted';
        return TRUE;
      } else {
        global $errors_queue;
        $errors_queue[] = $this->db->error;
        return FALSE;
      }
    }
    
  }
  
/**
 *  Check to see if a users password is legitimate 
 *
 *  Used for verifying changes
 */
 public function verifyPassword($password){
   $hashed = $this->hashPassword($password);
   
   return ($this->user && $this->user['password'] == $hashed);
 }
 
/**
 *  Checks whether a reset link is legitimate
 */
 private function checkResetLink($uid, $reset_key){
   $uid = $this->db->real_escape_string($uid);
   $result = $this->db->query("SELECT reset_key FROM $this->tablename WHERE uid=$uid");
   if($result){
     $real = $result->fetch_assoc();
     $real = $real['reset_key'];   
     return $real == $reset_key;
   } else {
     return FALSE;
   } 
 }
 
/**
 *  Create a login link for when the user forgets their password.
 */ 
 public function createResetLink($uid){
   $key = md5(rand(1000000000, 999999999999) * $uid);
   $uid = $this->db->real_escape_string($uid);

   $this->db->query("UPDATE $this->tablename SET reset_key='$key' WHERE uid=$uid");
   
   global $domain;
   return 'http://' . $domain . "/login?reset=1&user=$uid&key=$key";
 } 
 
/**
 *  Remove a reset link - to make it one time use
 */
 private function destroyResetLink(){
   $uid = $this->user['uid'];
   $this->db->query("UPDATE $this->tablename SET reset_key='' WHERE uid=$uid");  
 }
 
/**
 *  Requires the config.php to be properly included
 *  $pass - the password to be hashed
 */
  private function hashPassword($pass){
    global $site_secret;
    return md5($pass . $site_secret);
  }
  
/**
 *  Check if there is a cookie set for the session
 *  If it is return it, else return FALSE
 */
  private function getCookie(){
    if(isset($_COOKIE['user_login_sid'])){
      return  $_COOKIE['user_login_sid'];
    } else {
      return FALSE;
    }
  }
  
/**
 *  Set a cookie for the current user
 */
  private function setCookie(){
    if($this->user){
      $value = array(
        $this->user['sid'], 
        $this->user['uid']
      );
      $value = base64_encode(implode('|', $value));
      
      setcookie('user_login_sid', $value, time()+3600, '/');
    }
  }
  
/**
 *  Delete a current cookie
 */
  private function deleteCookie(){
    $value = $_COOKIE['user_login_sid'];
    setcookie('user_login_sid', $value, time()-3600, '/');
  }

/**
 *  Check if the users session is valid
 */
  private function checkSession($cookie){
    //decode the cookie
    list($sid, $uid) = $this->getCookieValues($cookie);
   
    return $this->sessionController->checkSession($sid, $uid);
  }
 
  private function getCookieValues($cookie){
    return explode('|', base64_decode($cookie));
  }
 /**
  *  load the user 
  */
  private function loadByName($name){
    $name = $this->db->real_escape_string($name);
    
    $result = $this->db->query("SELECT * FROM $this->tablename WHERE name='$name'");
    if($result){
      $user = $result->fetch_assoc();
      return $user;
    } else {
      global $errors_queue;
      $errors_queue[] = $this->db->error;
      return FALSE;
    }
  }
  
  private function loadById($id){
    $id = $this->db->real_escape_string($id);
    
    $result = $this->db->query("SELECT * FROM $this->tablename WHERE uid='$id'");
    if($result){
      $user = $result->fetch_assoc();
      return $user;
    } else {
      global $errors_queue;
      $errors_queue[] = $this->db->error;
      return FALSE;
    }
  }
  
  public function loadByEmail($email){
    $email = $this->db->real_escape_string($email);
    
    $result = $this->db->query("SELECT * FROM $this->tablename WHERE email='$email'");
    if($result){
      $user = $result->fetch_assoc();
      return $user;
    } else {
      global $errors_queue;
      $errors_queue[] = $this->db->error;
      return FALSE;
    }
  }  
}
  
?>