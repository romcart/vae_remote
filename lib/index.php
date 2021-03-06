<?php

if (!function_exists('_vaeql_query_internal')) {
  die("VaeQL PHP Extension not found.  Please make sure it is installed.\n\nTo install VaeQL on macOS via Homebrew, run the following commands:\n  brew tap actionverb/tap\n  brew install vaeql");
}

if ($_VERB['config']) {
  foreach ($_VERB['config'] as $k => $v) {
    $_VAE['config'][$k] = $v;
  }
}

$_VAE['version'] = 100;

require_once(dirname(__FILE__) . "/general.php");
require_once(dirname(__FILE__) . "/pages.php");
require_once(dirname(__FILE__) . "/vaedata.php");

session_set_save_handler("_vae_session_handler_open", "_vae_session_handler_close", "_vae_session_handler_read", "_vae_session_handler_write", "_vae_session_handler_destroy", "_vae_session_handler_gc");

@(include(realpath($_SERVER['DOCUMENT_ROOT'].'/../../../vae-config/fs-settings.php')));
@(include(realpath($_SERVER['DOCUMENT_ROOT'].'/../../../../vae-config/fs-settings.php')));

function _vae_local_log($message) {
  file_put_contents("php://stdout", sprintf("[%s] %s:%s %s\n", date("D M j H:i:s Y"), $_SERVER["REMOTE_ADDR"], $_SERVER["REMOTE_PORT"], $message));
}

function _vae_local_log_access($status = 200) {
  return _vae_local_log(sprintf("[%s]: %s", $status, $_SERVER["REQUEST_URI"]));
}

if ($data_path = getenv("VAE_LOCAL_DATA_PATH")) {
  set_time_limit(60);
  $_VAE['config']['backlot_url'] = "http://" . getenv("VAE_LOCAL_BACKSTAGE");
  $_VAE['config']['secret_key'] = getenv("VAE_LOCAL_SECRET_KEY");
  $_VAE['vaedb_port'] = getenv("VAE_LOCAL_VAEDB_PORT");
  $_VAE['config']['data_path'] = $data_path;
  $_VAE['config']['content_subdomain'] = $data_path . "feed.xml";
  $_VAE['config']['asset_url'] = "/.vae/data/assets/";
  $_VAE['config']['data_url'] = "/.vae/data/";
  $_VAE['vaedbd_backends'] = array('127.0.0.1');
  $_VAE['local_full_stack'] = true;
  $local_script = false;
  $script_name = preg_replace('/\?.*$/', "", $_SERVER['REQUEST_URI']);
  $script_name = preg_replace('/^\/__cache\/[a0-9]*\//', "/", $script_name);
  $server_parsed = array('.html','.haml','.php','.xml','.rss','.sass','.scss');
  foreach ($server_parsed as $ext) {
    foreach (array('', '/index') as $file) {
      $path = str_replace($ext . $ext, $ext, str_replace("//", "/", $_SERVER['DOCUMENT_ROOT'] . $script_name . $file . $ext));
      if (file_exists($path) && !is_dir($path)) {
        $_SERVER['SCRIPT_FILENAME'] = $local_script = $path;
        break;
      }
    }
    if ($local_script) break;
  }
  if (!$local_script && file_exists($_SERVER['DOCUMENT_ROOT'] . $script_name)) return false;
}

if (_vae_should_load()) {

  /* Store start times */
  $_VAE['start_tick'] = microtime(true);
  if ($_REQUEST['__time']) {
    $_VAE['tick'] = microtime(true);
    $_VAE['ticks'] = array();
  }

  /* Phpinfo */
  if ($_REQUEST['__phpinfo']) {
    phpinfo();
    die();
  }

  /* Bring in the rest of Vae */
  require_once(dirname(__FILE__) . "/vae_exception.php");
  require_once(dirname(__FILE__) . "/callback.php");
  require_once(dirname(__FILE__) . "/compat.php");
  require_once(dirname(__FILE__) . "/constants.php");
  require_once(dirname(__FILE__) . "/context.php");
  require_once(dirname(__FILE__) . "/func.php");
  require_once(dirname(__FILE__) . "/parse.php");
  require_once(dirname(__FILE__) . "/phpapi.php");
  require_once(dirname(__FILE__) . "/render.php");
  require_once(dirname(__FILE__) . "/rest.php");
  require_once(dirname(__FILE__) . "/store.php");
  require_once(dirname(__FILE__) . "/thrift.php");
  require_once(dirname(__FILE__) . "/users.php");

  /* Configure PHP */
  _vae_configure_php();
  _vae_tick("session startup");

  /* Initialize */
  unset($_SESSION['__v:flash_new']);

  /* Perform remote actions */
  if ($_REQUEST['clear_login']) _vae_clear_login();
  if ($_REQUEST['set_login']) _vae_set_login();
  if ($_REQUEST['__v:customer_key']) _vae_store_load_customer_from_key();
  _vae_tick("Vae Startup", true);

  /* Dispatch request */
  if ($_REQUEST['__status']) {
    require_once(dirname(__FILE__) . "/status.php");
    _vae_status();
  }
  if ($_REQUEST['__sweep']) {
    _vae_sweep_data_dir();
  }

  if ($_REQUEST['__v:store_payment_method_ipn']) _vae_store_ipn();
  if (file_exists($_SERVER['DOCUMENT_ROOT']."/__vae.php") && !$_REQUEST['__vae_local'] && !$_REQUEST['__verb_local']) require_once($_SERVER['DOCUMENT_ROOT']."/__vae.php");
  if (file_exists($_SERVER['DOCUMENT_ROOT']."/__verb.php") && !$_REQUEST['__vae_local'] && !$_REQUEST['__verb_local']) require_once($_SERVER['DOCUMENT_ROOT']."/__verb.php");

  if ($_REQUEST['secret_key']) _vae_remote();

  if (substr($_SERVER['SCRIPT_FILENAME'], -5) == ".sass" || substr($_SERVER['SCRIPT_FILENAME'], -5) == ".scss") {
    require_once(dirname(__FILE__) . "/haml.php");
    ob_start('_vae_sass_ob');

  } else {

    _vae_page_check_domain();
    if ($_REQUEST['__page'] || (strstr($_SERVER['SCRIPT_FILENAME'], "lib/pages.php") && strstr($_SERVER['SCRIPT_FILENAME'], "vae"))) _vae_page();
    _vae_page_check_redirects();
    _vae_parse_path();
    _vae_set_cdn_url();
    if ($_REQUEST['__vae_local'] || $_REQUEST['__verb_local']) _vae_local();

    if (strstr($_SERVER['SCRIPT_FILENAME'], ".pdf") && !isset($_VAE['skip_pdf'])) {
      require_once(dirname(__FILE__) . "/pdf.php");
      _vae_pdf();
    } elseif (!$_ENV['TEST']) {
      /* Normal Request */
      if (!isset($_VAE['filename'])) $_VAE['filename'] = str_replace($_SERVER['DOCUMENT_ROOT'], "", $_SERVER['SCRIPT_FILENAME']);
      _vae_set_cache_key();
      _vae_start_ob();
      if ($_VAE['local_full_stack'] && $local_script) {
        require($local_script);
        _vae_local_log_access(200);
      }
    }
  }
}

if ($_VAE['local_full_stack'] && !$local_script) {
  _vae_local_log_access(404);
  _vae_page_404("Could not match URL.");
}
