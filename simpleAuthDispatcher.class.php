<?php
/**
 *  An implementation of Dispatcher that uses the  loginController (and session controller) to check if
 *  a user is logged in for handlers that specify:
 *  <code>restricted => true</code>
 *  as a part of their definition
 *  @see Dispatcher.class.php
 */
//make sure the class we're trying to extend is loaded
load_class('dispatcher');

class SimpleAuthDispatcher extends Dispatcher {
  /**
   *  Override the getHandler function to check if the user is logged in
   *  if the handler specifies restricted.  If they aren't logged in, this will
   *  return false for restricted handlers, forcing a 404
   */
  protected function getHandler($request) {
    $handler = parent::getHandler($request);
    if (!empty($handler['restricted'])) {
      $class = load_class('loginController');
      $login = $class::instance();
      if($login->checkLoggedIn()) {
        return FALSE;
      }
    }
    return $handler;
  }
}
