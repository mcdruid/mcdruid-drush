<?php

$siteenv = isset($argv[1]) ? $argv[1] : FALSE;
if (empty($siteenv) || !strpos($siteenv, '.')) {
  print "usage php -f ${argv[0]} site.env [/path/to/docroot]\n";
  exit;
}
// remove @ prefix
if (strpos($siteenv, '@') === 0) {
  $siteenv = substr($siteenv, 1);
}
$docroot = isset($argv[2]) ? $argv[2] : "/var/www/html/$siteenv/docroot";
$parts = explode('.', $siteenv);
$sitegroup = $parts[0];
$siteenv   = $parts[1];

// need to set these for the magic include
$_SERVER['AH_SITE_GROUP']        = $sitegroup;
$_SERVER['AH_SITE_ENVIRONMENT']  = $siteenv;

// avoid a PHP Notice if this is not set
$_SERVER['REMOTE_ADDR']          = NULL;

print "#####################\n";
print "docroot: $docroot\n";
print "sitegroup: $sitegroup\n";
print "siteenv: $siteenv\n";
print 'testing bootstrap on ' . date('r') . "\n";
print "#####################\n";

 /**
 * In order to bootstrap Drupal from another PHP script, you can use this code:
 * @code
 *   define('DRUPAL_ROOT', '/path/to/drupal');
 *   require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
 *   drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
 * @endcode
 */
 
define('DRUPAL_ROOT', $docroot);

require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
require_once DRUPAL_ROOT . '/includes/common.inc';

if ((defined('DRUPAL_CORE_COMPATIBILITY') && DRUPAL_CORE_COMPATIBILITY == '6.x') || function_exists('drupal_init_language')) {
  drupal_bootstrap(DRUPAL_BOOTSTRAP_PATH);
  as_debug_d6_bootstrap_full();
}
else {
  // assume D7 for now
  drupal_bootstrap(DRUPAL_BOOTSTRAP_LANGUAGE);
  as_debug_d7_bootstrap_full();
}

/**
 * based on https://api.drupal.org/api/drupal/includes%21common.inc/function/_drupal_bootstrap_full/6
 */
function as_debug_d6_bootstrap_full() {
  
  timer_start('acquia_support');
 
  as_debug('starting bootstrap_full');

  static $called;

  if ($called) {
    return;
  }
  $called = 1;
  require_once './includes/theme.inc';
  require_once './includes/pager.inc';
  require_once './includes/menu.inc';
  require_once './includes/tablesort.inc';
  require_once './includes/file.inc';
  require_once './includes/unicode.inc';
  require_once './includes/image.inc';
  require_once './includes/form.inc';
  require_once './includes/mail.inc';
  require_once './includes/actions.inc';
  
  as_debug('finished includes');
  
  // Set the Drupal custom error handler.
  set_error_handler('drupal_error_handler');
  
  // Emit the correct charset HTTP header.
  drupal_set_header('Content-Type: text/html; charset=utf-8');
  
  // Detect string handling method
  unicode_check();
  
  // Undo magic quotes
  fix_gpc_magic();
  
  as_debug('loading enabled modules');
  
  // Load all enabled modules
  as_debug_d6_module_load_all();
  
  as_debug('modules loaded');

  // out-of-date copies of D6 might not have this function
  //  it's probably not necessary for what we're trying to do anyway
  if (function_exists('drupal_random_bytes')) {
    // Ensure mt_rand is reseeded, to prevent random values from one page load
    // being exploited to predict random values in subsequent page loads.
    $seed = unpack("L", drupal_random_bytes(4));
    mt_srand($seed[1]);
  }
  
  // Let all modules take action before menu system handles the request
  // We do not want this while running update.php.
  if (!defined('MAINTENANCE_MODE') || MAINTENANCE_MODE != 'update') {
    as_debug('starting to invoke hook_init');
    as_debug_d6_module_invoke_all('init');
    as_debug('finished hook_init');
  }
  
  as_debug('end of bootstrap_full');
}

/**
 * based on https://api.drupal.org/api/drupal/includes%21common.inc/function/_drupal_bootstrap_full/7
 */ 
function as_debug_d7_bootstrap_full() {

  timer_start('acquia_support');

  as_debug('starting bootstrap_full');

  static $called = FALSE;

  if ($called) {
    return;
  }
  $called = TRUE;
  require_once DRUPAL_ROOT . '/' . variable_get('path_inc', 'includes/path.inc');
  require_once DRUPAL_ROOT . '/includes/theme.inc';
  require_once DRUPAL_ROOT . '/includes/pager.inc';
  require_once DRUPAL_ROOT . '/' . variable_get('menu_inc', 'includes/menu.inc');
  require_once DRUPAL_ROOT . '/includes/tablesort.inc';
  require_once DRUPAL_ROOT . '/includes/file.inc';
  require_once DRUPAL_ROOT . '/includes/unicode.inc';
  require_once DRUPAL_ROOT . '/includes/image.inc';
  require_once DRUPAL_ROOT . '/includes/form.inc';
  require_once DRUPAL_ROOT . '/includes/mail.inc';
  require_once DRUPAL_ROOT . '/includes/actions.inc';
  require_once DRUPAL_ROOT . '/includes/ajax.inc';
  require_once DRUPAL_ROOT . '/includes/token.inc';
  require_once DRUPAL_ROOT . '/includes/errors.inc';

  as_debug('finished includes');
  
  // Detect string handling method
  unicode_check();

  // Undo magic quotes
  fix_gpc_magic();

  as_debug('loading enabled modules');

  // Load all enabled modules
  as_debug_d7_module_load_all();
  
  as_debug('modules loaded');

  as_debug('getting stream_wrappers');
  
  // Make sure all stream wrappers are registered.
  file_get_stream_wrappers();
  
  as_debug('got stream_wrappers');

  $test_info = &$GLOBALS['drupal_test_info'];
  if (!empty($test_info['in_child_site'])) {
    // Running inside the simpletest child site, log fatal errors to test
    // specific file directory.
    ini_set('log_errors', 1);
    ini_set('error_log', 'public://error.log');
  }
  
  as_debug('initialising path');

  // Initialize $_GET['q'] prior to invoking hook_init().
  drupal_path_initialize();
  
  as_debug('initialised path');

  // Let all modules take action before the menu system handles the request.
  // We do not want this while running update.php.
  if (!defined('MAINTENANCE_MODE') || MAINTENANCE_MODE != 'update') {
    // Prior to invoking hook_init(), initialize the theme (potentially a custom
    // one for this page), so that:
    // - Modules with hook_init() implementations that call theme() or
    //   theme_get_registry() don't initialize the incorrect theme.
    // - The theme can have hook_*_alter() implementations affect page building
    //   (e.g., hook_form_alter(), hook_node_view_alter(), hook_page_alter()),
    //   ahead of when rendering starts.
    as_debug('setting custom theme');
    menu_set_custom_theme();
    as_debug('set custom theme');
    as_debug('initialising theme');
    drupal_theme_initialize();
    as_debug('initialised theme');
    as_debug('starting to invoke hook_init');
    as_debug_d7_module_invoke_all('init');
    as_debug('finished hook_init');
  }
  as_debug('end of bootstrap_full');
}

/**
 * based on https://api.drupal.org/api/drupal/includes%21module.inc/function/module_invoke_all/6
 */ 
function as_debug_d6_module_invoke_all() {
  $args = func_get_args();
  $hook = $args[0];
  unset($args[0]);
  $return = array();
  foreach (module_implements($hook) as $module) {
    as_debug("start hook_$hook in $module");
    $function = $module . '_' . $hook;
    $result = call_user_func_array($function, $args);
    if (isset($result) && is_array($result)) {
      $return = array_merge_recursive($return, $result);
    }
    else if (isset($result)) {
      $return[] = $result;
    }
    as_debug("end hook_$hook in $module");
  }

  return $return;
}

/**
 * based on https://api.drupal.org/api/drupal/includes!module.inc/function/module_load_all/7
 */
function as_debug_d7_module_load_all($bootstrap = FALSE) {
  static $has_run = FALSE;

  if (isset($bootstrap)) {
    foreach (module_list(TRUE, $bootstrap) as $module) {
      as_debug("loading module $module");
      drupal_load('module', $module);
      as_debug("loaded module $module");
    }
    // $has_run will be TRUE if $bootstrap is FALSE.
    $has_run = !$bootstrap;
  }
  return $has_run;
}

/**
 * based on https://api.drupal.org/api/drupal/includes!module.inc/function/module_load_all/6
 */
function as_debug_d6_module_load_all() {
  foreach (module_list(TRUE, FALSE) as $module) {
    as_debug("loading module $module");
    drupal_load('module', $module);
    as_debug("loaded module $module");
  }
}

/**
 * based on https://api.drupal.org/api/drupal/includes%21module.inc/function/module_invoke_all/7
 */ 
function as_debug_d7_module_invoke_all($hook) {
  $args = func_get_args();
  // Remove $hook from the arguments.
  unset($args[0]);
  $return = array();
  foreach (module_implements($hook) as $module) {
    as_debug("start hook_$hook in $module");
    $function = $module . '_' . $hook;
    if (function_exists($function)) {
      $result = call_user_func_array($function, $args);
      if (isset($result) && is_array($result)) {
        $return = array_merge_recursive($return, $result);
      }
      elseif (isset($result)) {
        $return[] = $result;
      }
    }
    as_debug("end hook_$hook in $module");
  }

  return $return;
}

function as_debug($mark) {
  static $old_time, $old_memory;
  $time = timer_read('acquia_support');
  $time_delta = round($time - $old_time, 2);
  $old_time = $time;
  $memory = round(memory_get_peak_usage(TRUE) / 1024 / 1024, 2) . 'MB';
  $memory_delta = $memory - $old_memory;
  $old_memory = $memory;
  print "$mark | t: $time | t^: $time_delta | m: $memory | m^: $memory_delta\n";
}
