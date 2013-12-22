<?php

namespace Drupal\Core;

use Drupal\Bootstrap as BaseBootstrap;

/**
 * Class Bootstrap
 * @package Drupal\Core
 */
class Bootstrap extends BaseBootstrap
{
  /**
   * Instantiate the container.
   *
   * Objects and parameters can be passed as argument to the constructor.
   *
   * @param array $values The parameters or objects.
   */
  public function __construct(array $values = array())
  {

parent::__construct($values);

// The series of bootstrap phases is represented by a
// consecutive series of integer constants. Probably this cannot
// be changed, but maybe new steps can be inserted between other
// steps if the intermediate steps are floats.
$this['phases'] = array(
  DRUPAL_BOOTSTRAP_CONFIGURATION,
  DRUPAL_BOOTSTRAP_PAGE_CACHE,
  DRUPAL_BOOTSTRAP_DATABASE,
  DRUPAL_BOOTSTRAP_VARIABLES,
  DRUPAL_BOOTSTRAP_SESSION,
  DRUPAL_BOOTSTRAP_PAGE_HEADER,
  DRUPAL_BOOTSTRAP_LANGUAGE,
  DRUPAL_BOOTSTRAP_FULL,
);

/**
 * Sets up the script environment and loads settings.php.
 *
 * @see _drupal_bootstrap_configuration()
 */
$this[DRUPAL_BOOTSTRAP_CONFIGURATION] = function () {
  // Set the Drupal custom error handler.
  set_error_handler('_drupal_error_handler');
  set_exception_handler('_drupal_exception_handler');

  $this['drupal_environment_initialize'];
  // Start a page timer:
  timer_start('page');
  // Initialize the configuration, including variables from settings.php.
  $this['drupal_settings_initialize'];
};

/**
 * Initializes the PHP environment.
 *
 * @see drupal_environment_initialize()
 */
$this['drupal_environment_initialize'] = function () {
  if (!isset($_SERVER['HTTP_REFERER'])) {
    $_SERVER['HTTP_REFERER'] = '';
  }
  if (!isset($_SERVER['SERVER_PROTOCOL']) || ($_SERVER['SERVER_PROTOCOL'] != 'HTTP/1.0' && $_SERVER['SERVER_PROTOCOL'] != 'HTTP/1.1')) {
    $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.0';
  }

  if (isset($_SERVER['HTTP_HOST'])) {
    // As HTTP_HOST is user input, ensure it only contains characters allowed
    // in hostnames. See RFC 952 (and RFC 2181).
    // $_SERVER['HTTP_HOST'] is lowercased here per specifications.
    $_SERVER['HTTP_HOST'] = strtolower($_SERVER['HTTP_HOST']);
    if (!drupal_valid_http_host($_SERVER['HTTP_HOST'])) {
      // HTTP_HOST is invalid, e.g. if containing slashes it may be an attack.
      header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
      exit;
    }
  }
  else {
    // Some pre-HTTP/1.1 clients will not send a Host header. Ensure the key is
    // defined for E_ALL compliance.
    $_SERVER['HTTP_HOST'] = '';
  }

  // When clean URLs are enabled, emulate ?q=foo/bar using REQUEST_URI. It is
  // not possible to append the query string using mod_rewrite without the B
  // flag (this was added in Apache 2.2.8), because mod_rewrite unescapes the
  // path before passing it on to PHP. This is a problem when the path contains
  // e.g. "&" or "%" that have special meanings in URLs and must be encoded.
  $_GET['q'] = request_path();

  // Enforce E_ALL, but allow users to set levels not part of E_ALL.
  error_reporting(E_ALL | error_reporting());

  // Override PHP settings required for Drupal to work properly.
  // sites/default/default.settings.php contains more runtime settings.
  // The .htaccess file contains settings that cannot be changed at runtime.

  // Don't escape quotes when reading files from the database, disk, etc.
  ini_set('magic_quotes_runtime', '0');
  // Use session cookies, not transparent sessions that puts the session id in
  // the query string.
  ini_set('session.use_cookies', '1');
  ini_set('session.use_only_cookies', '1');
  ini_set('session.use_trans_sid', '0');
  // Don't send HTTP headers using PHP's session handler.
  ini_set('session.cache_limiter', 'none');
  // Use httponly session cookies.
  ini_set('session.cookie_httponly', '1');

  // Set sane locale settings, to ensure consistent string, dates, times and
  // numbers handling.
  setlocale(LC_ALL, 'C');
};

/**
 * Sets the base URL, cookie domain, and session name from configuration.
 *
 * @see drupal_settings_initialize()
 */
$this['drupal_settings_initialize'] = function () {
  global $base_url, $base_path, $base_root;

  // Export these settings.php variables to the global namespace.
  global $databases, $cookie_domain, $conf, $installed_profile, $update_free_access, $db_url, $db_prefix, $drupal_hash_salt, $is_https, $base_secure_url, $base_insecure_url;
  $conf = array();

  if (file_exists(DRUPAL_ROOT . '/' . conf_path() . '/settings.php')) {
    include_once DRUPAL_ROOT . '/' . conf_path() . '/settings.php';
  }
  $is_https = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on';

  if (isset($base_url)) {
    // Parse fixed base URL from settings.php.
    $parts = parse_url($base_url);
    if (!isset($parts['path'])) {
      $parts['path'] = '';
    }
    $base_path = $parts['path'] . '/';
    // Build $base_root (everything until first slash after "scheme://").
    $base_root = substr($base_url, 0, strlen($base_url) - strlen($parts['path']));
  }
  else {
    // Create base URL.
    $http_protocol = $is_https ? 'https' : 'http';
    $base_root = $http_protocol . '://' . $_SERVER['HTTP_HOST'];

    $base_url = $base_root;

    // $_SERVER['SCRIPT_NAME'] can, in contrast to $_SERVER['PHP_SELF'], not
    // be modified by a visitor.
    if ($dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '\/')) {
      $base_path = $dir;
      $base_url .= $base_path;
      $base_path .= '/';
    }
    else {
      $base_path = '/';
    }
  }
  $base_secure_url = str_replace('http://', 'https://', $base_url);
  $base_insecure_url = str_replace('https://', 'http://', $base_url);

  if ($cookie_domain) {
    // If the user specifies the cookie domain, also use it for session name.
    $session_name = $cookie_domain;
  }
  else {
    // Otherwise use $base_url as session name, without the protocol
    // to use the same session identifiers across HTTP and HTTPS.
    list( , $session_name) = explode('://', $base_url, 2);
    // HTTP_HOST can be modified by a visitor, but we already sanitized it
    // in drupal_settings_initialize().
    if (!empty($_SERVER['HTTP_HOST'])) {
      $cookie_domain = $_SERVER['HTTP_HOST'];
      // Strip leading periods, www., and port numbers from cookie domain.
      $cookie_domain = ltrim($cookie_domain, '.');
      if (strpos($cookie_domain, 'www.') === 0) {
        $cookie_domain = substr($cookie_domain, 4);
      }
      $cookie_domain = explode(':', $cookie_domain);
      $cookie_domain = '.' . $cookie_domain[0];
    }
  }
  // Per RFC 2109, cookie domains must contain at least one dot other than the
  // first. For hosts such as 'localhost' or IP Addresses we don't set a cookie domain.
  if (count(explode('.', $cookie_domain)) > 2 && !is_numeric(str_replace('.', '', $cookie_domain))) {
    ini_set('session.cookie_domain', $cookie_domain);
  }
  // To prevent session cookies from being hijacked, a user can configure the
  // SSL version of their website to only transfer session cookies via SSL by
  // using PHP's session.cookie_secure setting. The browser will then use two
  // separate session cookies for the HTTPS and HTTP versions of the site. So we
  // must use different session identifiers for HTTPS and HTTP to prevent a
  // cookie collision.
  if ($is_https) {
    ini_set('session.cookie_secure', TRUE);
  }
  $prefix = ini_get('session.cookie_secure') ? 'SSESS' : 'SESS';
  session_name($prefix . substr(hash('sha256', $session_name), 0, 32));
};

/**
 * Attempts to serve a page from the cache.
 *
 * @see _drupal_bootstrap_page_cache()
 */
$this[DRUPAL_BOOTSTRAP_PAGE_CACHE] = function () {
  $this['_drupal_bootstrap_page_cache__plugins'];
  drupal_block_denied(ip_address());
  $this['_drupal_bootstrap_page_cache__serve'];
};

/**
 * Include the Drupal cache subsystem and plugins.
 */
$this['_drupal_bootstrap_page_cache__plugins'] = function () {
  // Allow specifying special cache handlers in settings.php, like
  // using memcached or files for storing cache information.
  require_once DRUPAL_ROOT . '/includes/cache.inc';
  foreach (variable_get('cache_backends', array()) as $include) {
    require_once DRUPAL_ROOT . '/' . $include;
  }
};

/**
 * Actually serve the cached page.
 */
$this['_drupal_bootstrap_page_cache__serve'] = function () {
  global $user;

  // Check for a cache mode force from settings.php.
  if (variable_get('page_cache_without_database')) {
    $cache_enabled = TRUE;
  }
  else {
    drupal_bootstrap(DRUPAL_BOOTSTRAP_VARIABLES, FALSE);
    $cache_enabled = variable_get('cache');
  }
  // If there is no session cookie and cache is enabled (or forced), try
  // to serve a cached page.
  if (!isset($_COOKIE[session_name()]) && $cache_enabled) {
    // Make sure there is a user object because its timestamp will be
    // checked, hook_boot might check for anonymous user etc.
    $user = drupal_anonymous_user();
    // Get the page from the cache.
    $cache = drupal_page_get_cache();
    // If there is a cached page, display it.
    if (is_object($cache)) {
      header('X-Drupal-Cache: HIT');
      // Restore the metadata cached with the page.
      $_GET['q'] = $cache->data['path'];
      drupal_set_title($cache->data['title'], PASS_THROUGH);
      date_default_timezone_set(drupal_get_user_timezone());
      // If the skipping of the bootstrap hooks is not enforced, call
      // hook_boot.
      if (variable_get('page_cache_invoke_hooks', TRUE)) {
        bootstrap_invoke_all('boot');
      }
      drupal_serve_page_from_cache($cache);
      // If the skipping of the bootstrap hooks is not enforced, call
      // hook_exit.
      if (variable_get('page_cache_invoke_hooks', TRUE)) {
        bootstrap_invoke_all('exit');
      }
      // We are done.
      exit;
    }
    else {
      header('X-Drupal-Cache: MISS');
    }
  }
};

/**
 * Initializes the database system and registers autoload functions.
 *
 * @see _drupal_bootstrap_database()
 */
$this[DRUPAL_BOOTSTRAP_DATABASE] = function () {
  $this['_drupal_bootstrap_database__install'];

  $this['_drupal_bootstrap_database__testing'];

  // Initialize the database system. Note that the connection
  // won't be initialized until it is actually requested.
  require_once DRUPAL_ROOT . '/includes/database/database.inc';

  $this['_drupal_bootstrap_database__autoload'];
};

$this['_drupal_bootstrap_database__install'] = function () {
  // Redirect the user to the installation script if Drupal has not been
  // installed yet (i.e., if no $databases array has been defined in the
  // settings.php file) and we are not already installing.
  if (empty($GLOBALS['databases']) && !drupal_installation_attempted()) {
    include_once DRUPAL_ROOT . '/includes/install.inc';
    install_goto('install.php');
  }
};

$this['_drupal_bootstrap_database__testing'] = function () {
  // The user agent header is used to pass a database prefix in the request when
  // running tests. However, for security reasons, it is imperative that we
  // validate we ourselves made the request.
  if ($test_prefix = drupal_valid_test_ua()) {
    // Set the test run id for use in other parts of Drupal.
    $test_info = &$GLOBALS['drupal_test_info'];
    $test_info['test_run_id'] = $test_prefix;
    $test_info['in_child_site'] = TRUE;

    foreach ($GLOBALS['databases']['default'] as &$value) {
      // Extract the current default database prefix.
      if (!isset($value['prefix'])) {
        $current_prefix = '';
      }
      elseif (is_array($value['prefix'])) {
        $current_prefix = $value['prefix']['default'];
      }
      else {
        $current_prefix = $value['prefix'];
      }

      // Remove the current database prefix and replace it by our own.
      $value['prefix'] = array(
        'default' => $current_prefix . $test_prefix,
      );
    }
  }
};

$this['_drupal_bootstrap_database__autoload'] = function () {
  // Register autoload functions so that we can access classes and interfaces.
  // The database autoload routine comes first so that we can load the database
  // system without hitting the database. That is especially important during
  // the install or upgrade process.
  spl_autoload_register('drupal_autoload_class');
  spl_autoload_register('drupal_autoload_interface');
};

/**
 * Loads system variables and all enabled bootstrap modules.
 *
 * @see _drupal_bootstrap_variables()
 */
$this[DRUPAL_BOOTSTRAP_VARIABLES] = function () {
  global $conf;

  // Initialize the lock system.
  require_once DRUPAL_ROOT . '/' . variable_get('lock_inc', 'includes/lock.inc');
  lock_initialize();

  // Load variables from the database, but do not overwrite variables set in settings.php.
  $conf = variable_initialize(isset($conf) ? $conf : array());
  // Load bootstrap modules.
  require_once DRUPAL_ROOT . '/includes/module.inc';
  module_load_all(TRUE);
};

/**
 * Initializes session handling
 */
$this[DRUPAL_BOOTSTRAP_SESSION] = function () {
  require_once DRUPAL_ROOT . '/' . variable_get('session_inc', 'includes/session.inc');
  drupal_session_initialize();
};

/**
 * Invokes hook_boot(), initializes locking system, and sends HTTP headers.
 *
 * @see _drupal_bootstrap_page_header()
 */
$this[DRUPAL_BOOTSTRAP_PAGE_HEADER] = function () {
  bootstrap_invoke_all('boot');

  if (!drupal_is_cli()) {
    ob_start();
    drupal_page_header();
  }
};

/**
 * Initializes all the defined language types.
 *
 * @see drupal_language_initialize()
 */
$this[DRUPAL_BOOTSTRAP_LANGUAGE] = function () {
  drupal_language_initialize();
};

/**
 * Fully loads Drupal. Validates and fixes input
 */
$this[DRUPAL_BOOTSTRAP_FULL] = function () {
  require_once DRUPAL_ROOT . '/includes/common.inc';
  _drupal_bootstrap_full();
};

  }
}
