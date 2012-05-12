<?php

/**
 *  Dispatcher, handles basic request dispatching based on mappings (singleton)
 */

class Dispatcher {
  private static $instance;
  private $mappings;
  /**
   *  Construction function for Dispatcher - private to enforce singleton
   */
  private function __construct() {
    $this->mappings = array();
  }

  /**
   *  Get the active instance of the singleton
   */
  public static function instance() {
    if (empty(self::$instance)) {
      self::$instance = new Dispatcher();
    }
    return self::$instance;
  }

  /**
   *  Add a mapping, see comment on constructor for format
   *  @param $mapping - a mapping of paths to handlers - for example:
   *  array(
   *    '/api/*.json' =>  array (
   *      'prepare' => (callback) 'checkParameters', // An optional function to prepare the data prior to sending it to the handler (as a php callback)
   *      'handler' => (callback) array('LoginController', 'saveUser')  // A function to handle the request (as a php callback)
   *    )
   *  )
   */
  public function addMapping($mapping = array()) {
    $this->mappings = array_merge($this->mappings, $mapping);
  }

  /**
   *  This is the main function for Dispatcher, it takes a request, parses it, finds the best handler and calls it
   */
  public function handle() {
    //get the request
    $request = empty($_GET['q']) ? '' : $_GET['q'];
    //find the best handler
    $handler = $this->getHandler($request);
    if ($handler) {
      $data = $_REQUEST;
      // add the exploded path as a data item for convience 
      $data['path args'] = explode('/', $request);
      //run the prepare function if it exists
      if ($handler['prepare']) {
        $data = static::run($handler['prepare'], $data);
      }
      //run the handler
      $response = static::run($handler['handler'], $data);
    } else {
      //return a 404 if we couldn't find the handler
      header("HTTP/1.0 404 Not Found");
      $response = 'SadFace, we couldn\'t find <em>' . $request . '</em>.  We\'ll keep looking, but you might want to hit the back button.';
    }

    echo $response;
  }

  /**
   *  Find the best (aka most specific) handler for a request
   *  @param $request - as string path - like /opt/dev or opt/dev
   */
  protected function getHandler($request) {
    $handler = FALSE;
    $best = 0;
    
    $request = explode('/', $request);
    //remove any empty keys
    $request = array_filter($request);

    //loop through the handlers and find the ones that match
    foreach ($this->mappings as $path => $mapping) {
      //find the score for each handler that matches the mapping
      $path = explode('/', $path);
      //remove any empty keys
      $path = array_filter($path);
      $score = 0;
      foreach ($request as $ind => $part) {
        if ($part == $path[$ind]) {
          $score++;
        } else if ($path[$ind] == '*') {
          continue;
        } else {
          $score = -1;
        }
      }

      if ($score >= $best) {
        $best = $score;
        $handler = $mapping;
      }
    }

    if ($best > 0) {
      return $handler;
    }
    return FALSE;
  }

  /**
   *  Simple helper to call a user function
   */
  private static function run ($callback, $data) {
    if (is_callable($callback)) {
      return call_user_func($callback, $data);
    }
  }
}