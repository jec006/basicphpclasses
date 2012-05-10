<?php
/**
 *  Session Controller - Creates and Destroys sessions
 */

/* DB TABLE FOR SESSIONS
 *
  CREATE TABLE IF NOT EXISTS `sessions` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `uid` bigint(20) unsigned NOT NULL,
    `ip` varchar(255) NOT NULL,
    `sid` varchar(255) NOT NULL,
    `expires` int(11) NOT NULL,
    PRIMARY KEY (`sid`),
    UNIQUE KEY `id` (`id`),
    KEY `uid` (`uid`)
  ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=0 ;
  
*/


class SessionController {
  private $tablename = 'sessions';
  private $db;
  
  public function __construct(){
    $this->db = getDB();
  }
  
  public function create($user){
    $uid = $user['uid'];
    $ip = $this->getIp();
    
    $sid = $this->makeSessionKey();
    
    $session = array(
      'uid' => $uid,
      'ip' => $ip,
      'sid' => $sid,
      'expires' => time() + 3600
    );
    
    if($this->insertSession($session)){
      return $session;
    } else {
      return FALSE;
    }
  }
  
  public function checkSession($sid, $uid){
    $query = "SELECT * FROM $this->tablename WHERE sid='$sid'";
    $result = $this->db->query($query);
    if($result !== FALSE){  
      if($result->num_rows > 0){
        $values = $result->fetch_assoc();
        $ip = $this->getIp();

        $ret = ($uid == $values['uid'] && $ip == $values['ip'] && ($values['expires'] >= time()));
        if($ret){ $this->updateSession($sid); }

        return $ret;
      } else {
        return FALSE;
      }
    } else {
      global $errors_queue;
      $errors_queue[] = $this->db->error;
      return FALSE;
    }
  }
  
  private function insertSession($session){
    $query = "INSERT INTO $this->tablename (";
    //create a columns array and a values array - each being escaped of course 
    $columns = array();
    $values = array();
    foreach($session as $key=>$value){
      $columns[] = $this->db->real_escape_string($key);
      $values[] = $this->db->real_escape_string($value);
    }
    
    $query .= implode(', ', $columns) . ') VALUES (\'' . implode("', '", $values) . '\')';
    if($this->db->query($query)){
      $session['id'] = $this->db->insert_id;
      return $session;
    } else {
      global $errors_queue;
      $errors_queue[] = $this->db->error;
      return FALSE;
    }
  }
  
  private function updateSession($sid){
    $sid = $this->db->real_escape_string($sid);
    $expires = time() + 3600;
    if($this->db->query("UPDATE $this->tablename SET expires=$expires")){
      return TRUE;
    } else {
      global $errors_queue;
      $errors_queue[] = $result->error;
      return FALSE;
    }
  }
  
  public function deleteSession($sid){
    $sid = $this->db->real_escape_string($sid);
    if($this->db->query("DELETE FROM $this->tablename WHERE sid=$sid")){
      return TRUE;
    } else {
      global $errors_queue;
      $errors_queue = $this->db->error;
      return FALSE;
    }
  }
  
  /**
   *  Retrieves the current users ip
   */
  private function getIp(){
    if (isset($_SERVER['HTTP_X_FORWARD_FOR'])) {
      $ip = $_SERVER['HTTP_X_FORWARD_FOR'];
    } else {
      $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
  }
  
  /**
   *  Get the last created id in the database
   */
  private function getLastId(){
    $query = 'SELECT id FROM ' . $this->tablename . ' ORDER BY id DESC LIMIT 1';
    $result = $this->db->query($query);
    if($result !== FALSE){
      if($result->num_rows > 0){
        $id = $result->fetch_assoc();
        $id = $id['id'];
        return $id;
      } else {
        return FALSE;
      }
    } else {
      global $errors_queue;
      $errors_queue[] = $result->error;
      return FALSE;
    }
  }
  
  /**
   *  Make a session key 
   *  Based on the site key and the current session num  
   */
  private function makeSessionKey(){
    global $site_secret;
    $id = $this->getLastId();
    return md5($site_secret . $id);
  }
}
?>