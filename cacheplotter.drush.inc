<?php
 
/**
* @file
* Drush command to check for overly large items in DB cache tables that will
* not work with memcache.
*/
 
/**
* Implements hook_drush_command().
*/
function cacheplotter_drush_command() {
  $items['cacheplotter'] = array(
    'description' => dt('Displays cache objects larger than a limit to determine if they can be stored in memcache.'),
    'aliases' => array('cachep'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
    'arguments' => array(
      'limit' => '(Optional) Alter the limit from the default of 1000000 (1MB).',
    ),
    'examples' => array(
      'drush cacheplotter' => dt('Run the cacheplotter command against the current site with 1MB default limit'),
      'drush @example.prod cachep 100000' => dt('Run the cacheplotter command against example.prod with a custom limit of 100000 bytes'),
    ),
    'core' => array('6.x', '7.x'),
  );
 
  return $items;
}
 
/**
 * Detect which version of Drupal we're using and then route to
 * the correct function as the Database APIs differ.
 */
function drush_cacheplotter($limit = 1000000) {
  if (defined('DRUPAL_CORE_COMPATIBILITY')) {
    switch (DRUPAL_CORE_COMPATIBILITY) {
      case '7.x':
        cacheplotter_plot_d7($limit);
        break;
      case '6.x':
        cacheplotter_plot_d6($limit);
        break;
    }
  }
  elseif (function_exists('drupal_init_language')) {
    cacheplotter_plot_d6($limit);
  }
  else {
    drush_log('Incompatible API version', 'error');
  }
}
 
/**
 * Callback function used with Drupal 7.x
 */
function cacheplotter_plot_d7($limit) {
  if (class_exists('Database')) {
    $prefix = Database::getConnection()->tablePrefix();
  }
 
  $caches = array();
  $cache_query = isset($prefix) ? $prefix . 'cache%' : 'cache%';
 
  $result = db_query("SHOW TABLES LIKE :cache", array(':cache' => $cache_query));
  if ($result) {
    while ($row = $result->fetchAssoc()) {
      $caches[] = isset($prefix) ? substr(array_pop($row), strlen($prefix)) : array_pop($row);
    }
  }
  $caches += module_invoke_all('flush_caches');
 
  foreach ($caches as $cache) {
    print "Checking cache: " . $cache . PHP_EOL;
    $result = db_query("SELECT cid, length(data) length FROM {" . $cache . "} WHERE LENGTH(data) > :limit", array(':limit' => $limit));
    if ($result) {
      while ($row = $result->fetchAssoc()) {
        print "cid: " . $row['cid'] . PHP_EOL;
        print "length: " . $row['length'] . " bytes" . PHP_EOL;
      }
    }
    print PHP_EOL;
  }
}
 
/**
 * Callback function used with Drupal 6.x
 */
function cacheplotter_plot_d6($limit) {
  global $db_prefix;
  $prefix = $db_prefix;
 
  $caches = array();
  $cache_query = isset($prefix) ? $prefix . 'cache%' : 'cache%';
  $result = db_query("SHOW TABLES LIKE '%s'", $cache_query);
  if ($result) {
    while ($row = db_fetch_array($result)) {
      $caches[] = isset($prefix) ? substr(array_pop($row), strlen($prefix)) : array_pop($row);
    }
  }
  $caches += module_invoke_all('flush_caches');
 
  foreach ($caches as $cache) {
    print "Checking cache: " . $cache . PHP_EOL;
    $result = db_query("SELECT cid, length(data) length FROM {" . $cache . "} WHERE LENGTH(data) > '%d'", $limit);
    if ($result) {
      while ($row = db_fetch_array($result)) {
        print "cid: " . $row['cid'] . PHP_EOL;
        print "length: " . $row['length'] . " bytes" . PHP_EOL;
      }
    }
    print PHP_EOL;
  }
}