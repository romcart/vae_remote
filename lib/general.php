<?php


function _vae_absolute_data_url($path = "") {
  global $_VAE;
  if (substr($_VAE['config']['data_url'], 0, 4) == "http") return $_VAE['config']['data_url'] . $path;
  return _vae_proto() . $_SERVER['HTTP_HOST'] . $_VAE['config']['data_url'] . $path;
}

function _vae_akismet($a) {
  if (!strlen($a['akismet'])) return false;
  $comment['comment_author'] = $_REQUEST[$a['akismet_name_field']];
  $comment['comment_author_email'] = $_REQUEST[$a['akismet_email_field']];
  $comment['comment_author_url'] = $_REQUEST[$a['akismet_url_field']];
  $comment['comment_content'] = $_REQUEST[$a['akismet_comment_field']];
	$comment['user_ip']    = preg_replace( '/[^0-9., ]/', '', _vae_remote_addr());
	$comment['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
	$comment['referrer']   = $_SERVER['HTTP_REFERER'];
	$comment['blog']       = "http://" . $_SERVER['HTTP_HOST'];
	$ignore = array('HTTP_COOKIE','PHP_SESS_ID','PATH_TRANSLATED','SCRIPT_NAME','SCRIPT_FILENAME','SERVER_ADMIN','DOCUMENT_ROOT','PHPSESSID','PATH');
	foreach ($_SERVER as $key => $value) {
		if (!in_array($key, $ignore)) {
			$comment[$key] = $value;
		}
  }
	$query_string = '';
	foreach ($comment as $k => $v) {
	  if (is_string($v) && strlen($v)) $query_string .= $k . '=' . urlencode($v) . '&';
  }
  $host = $a['akismet'] . '.rest.akismet.com';
  $http_request  = "POST /1.1/comment-check HTTP/1.0\r\n";
	$http_request .= "Host: $host\r\n";
	$http_request .= "Content-Type: application/x-www-form-urlencoded; charset=utf-8\r\n";
	$http_request .= "Content-Length: " . strlen($query_string) . "\r\n";
	$http_request .= "User-Agent: Vae/0.4.0 | Akismet/2.0\r\n";
	$http_request .= "\r\n";
	$http_request .= $query_string;
	$response = '';
	if ($_ENV['TEST']) return true;
	if (false != ($fs = @fsockopen($host, 80, $errno, $errstr, 10))) {
		fwrite($fs, $http_request);
		while (!feof($fs)) $response .= fgets($fs, 1160);
		fclose($fs);
		$response = explode("\r\n\r\n", $response, 2);
	}
	return ($response[1] == "true");
}

function _vae_append_js($old, $new) {
  $new = str_replace(array("\n", "\r"), "", $new);
  if (strlen($old)) {
    $old = trim($old);
    if (substr($old, strlen($old)-1, 1) != ";") $old .= ";";
  }
  return $old . " " . $new;
}

function _vae_asset_html($type, $src) {
  if ($type == "js") {
    return '<script type="text/javascript" src="' . $src . '"></script>' . "\n";
  } else {
    return '<link rel="stylesheet" type="text/css" media="' . $type . '" href="' . $src . '" />' . "\n";
  }
}

function _vae_attrs($attrs, $tagname = "") {
  global $_VAE;
  $out = "";
  if (count($attrs)) {
    foreach ($attrs as $a => $v) {
      if (strstr($tagname, ":") || !strlen($tagname) || strstr($a, "data-") || in_array($a, $_VAE['attributes']['standard']) || (isset($_VAE['attributes'][$tagname]) && in_array($a, $_VAE['attributes'][$tagname])) && !in_array($a, array("ajax","default","validateinline"))) {
        if (!is_array($v)) $out .= " " . $a . "=\"" . htmlspecialchars($v) . "\"";
      }
    }
  }
  return $out;
}

function _vae_cdn_origin_pull() {
  return ($_SERVER['HTTP_X_VAE_CDN'] ? true : false);
}

function _vae_cdn_timestamp_url($url) {
  global $_VAE;
  if  (strstr($url, "://") || $_VAE['local_full_stack']) return $url;
  $timestamp = @filemtime($_SERVER['DOCUMENT_ROOT'] . $url);
  if ($timestamp < 1) $timestamp = time();
  return "/__cache/a" . $timestamp . $url;
}

function _vae_clear_login() {
  $_SESSION['__v:logged_in'] = false;
  if (!$_ENV['TEST']) echo "203 Logged Out";
  _vae_die();
}

function _vae_combine_array_keys($array, $keys) {
  $out = "";
  foreach ($keys as $key) {
    if (strlen($array[$key])) {
      if (strlen($out)) $out .= ", ";
      $out .= $array[$key];
    }
  }
  return $out;
}

function _vae_conf_path() {
  global $_VAE;
  return str_replace("/data", "/conf", $_VAE['config']['data_path']);
}

function _vae_configure_php() {
  global $_VAE;
  error_reporting(E_ALL & ~(E_NOTICE | E_DEPRECATED | E_WARNING | E_STRICT));
  set_exception_handler("_vae_exception_handler");
  date_default_timezone_set("America/New_York");
  if (!$_ENV['TEST']) ini_set('display_errors', (isset($_REQUEST['__debug']) || isset($_VAE['local_full_stack'])));
  if ($_REQUEST['__router']) {
    session_id($_REQUEST['__router']);
    session_start();
    $uri = str_replace("__router=" . $_REQUEST['__router'], "", $_SERVER['REQUEST_URI']);
    $s = substr($uri, -1, 1);
    if ($s == "?" || $s == "&") $uri = substr($uri, 0, strlen($uri) - 1);
    @header("Location: " . $uri);
    _vae_die();
  }
  if ($_REQUEST['__skip_pdf']) $_VAE['skip_pdf'] = true;
  _vae_set_default_config();
  _vae_load_settings();
  if ($_REQUEST['__proxy']) {
    session_id($_REQUEST['__proxy']);
    if ($_REQUEST['__get_yield']) {
      $_VAE['yield'] = _vae_long_term_cache_get("_proxy_yield_" . $_REQUEST['__proxy']);
    }
    if ($_REQUEST['__get_request_data']) {
      $_POST = unserialize(_vae_long_term_cache_get("_proxy_post_" . $_REQUEST['__proxy']));
      $_REQUEST = unserialize(_vae_long_term_cache_get("_proxy_request_" . $_REQUEST['__proxy']));
    }
    $_VAE['from_proxy'] = true;
  }
  if ($_REQUEST['__host']) $_SERVER['HTTP_HOST'] = $_REQUEST['__host'];
  if (!$_REQUEST['__v:store_payment_method_ipn'] && !($_REQUEST['set_login'] && $_REQUEST['session_id'])) {
    session_start();
  }
  _vae_store_marketing_data_in_session();
  $key = "fg" . $_VAE['feed_generation'];
  $vae_yml = $_SERVER['DOCUMENT_ROOT'] . '/__vae.yml';
  if (file_exists($vae_yml)) $key .= filemtime($vae_yml);
  $verb_yml = $_SERVER['DOCUMENT_ROOT'] . '/__verb.yml';
  if (file_exists($verb_yml)) $key .= filemtime($verb_yml);
  $_VAE['global_cache_key'] = $key;
}

function _vae_debug($msg) {
  global $_VAE;
  if (!is_string($msg)) $msg = serialize($msg);
  $_VAE['debug'] .= $msg . "\n";
}

function _vae_decimalize($amount, $decimal_places = 2) {
  return number_format($amount, $decimal_places, ".", "");
}

function _vae_dependency_add($filename, $md5 = null) {
  global $_VAE;
  $filename = preg_replace('/^\//', "", str_replace($_SERVER['DOCUMENT_ROOT'], "", $filename));
  if ($md5 == null) $md5 = @md5_file($_SERVER['DOCUMENT_ROOT'] . "/" . $filename);
  if (!isset($_VAE['dependencies'])) $_VAE['dependencies'] = array();
  $_VAE['dependencies'][$filename] = $md5;
}

function _vae_die() {
  if ($_ENV['TEST']) return;
  die();
}

function _vae_ele($a, $b, $c = null) {
  if (isset($c)) return $a[$b][$c];
  return $a[$b];
}

function _vae_escape_for_js($html) {
  return str_replace('"', "\\'", $html);
}

function _vae_error($msg, $debugging_info = "", $filename = null) {
  global $_VAE;
  if (_vae_in_ob() || $_REQUEST['__v:store_payment_method_ipn']) {
    if ($_ENV['TEST'] && !$_VAE['expected_exception']) echo $msg . "\n" . $debugging_info;
    throw new VaeException($msg, $debugging_info, $filename);
  } else {
    echo _vae_render_error(new VaeException($msg, $debugging_info, $filename));
  }
  _vae_die();
}

function _vae_error_handler($errno, $errstr) {
  _vae_error($errstr);
}

function _vae_exception_handler($e) {
  if ($_ENV['TEST']) return;
  ob_end_clean();
  echo _vae_render_error($e);
  _vae_die();
}

function _vae_fetch_multiple($path = "*", $context = null) {
  global $_VAE;
  $out = "";
  if ($options == null) $options = array();
  if ($options['asset_width'] == null) $options['asset_width'] = 500;
  if ($options['asset_height'] == null) $options['asset_height'] = $options['asset_width'];
  foreach (explode(",", $path) as $p) {
    if (substr($p, 0, 1) != "@") $p = "@" . $p;
    $value = _vae_fetch($p, $context);
    if (strlen($value) && strlen($out)) $out .= " - ";
    if ($value->type == "ImageItem") {
      $out .= '<img src="' . _vae_absolute_data_url(vae_image($value, $options['asset_width'], $options['asset_height'])) . '" />';
    } elseif ($value->type == "HtmlAreaItem") {
      $out .= _vae_htmlarea($value, $options, true);
    } else {
      $out .= $value;
    }
  }
  return $out;
}

function _vae_file($iden, $id, $path, $qs = "", $preserve_filename = false) {
  global $_VAE;
  if (!strlen($id)) return "";
  if ($_ENV['TEST']) return array($iden, $id, $path, $qs, $preserve_filename);
  $filename = null;
  if ($preserve_filename) $iden .= ($preserve_filename === true ? "-p" : "-" . $preserve_filename);
  _vae_tick("Fetching $iden from the file cache");
  if ($cache = _vae_long_term_cache_get($iden, 90)) return $cache;
  _vae_tick("Failed - looking for $id $path $qs from root machine");
  _vae_sitewide_lock();
  $url = $_VAE['config']['backlot_url'] . "/"  . $path . "?secret_key=" . $_VAE['config']['secret_key'] . $qs;
  $fp = @fopen($url, 'rb');
  if ($fp) {
    $meta_data = stream_get_meta_data($fp);
    foreach($meta_data['wrapper_data'] as $response) {
      if (strstr($response, "Content-Disposition: attachment; filename=")) {
        $sep = explode(".", str_replace(array("Content-Disposition: attachment; filename=", "\""), "", $response));
        $ext = array_pop($sep);
        if ($preserve_filename) {
          $filename = ($preserve_filename === true ? implode(".", $sep) : $preserve_filename);
        }
      }
    }
    while (!feof($fp)) $file .= fread($fp, 8192);
    fclose($fp);
  }
  _vae_sitewide_unlock();
  if ($file == "691 File not available") return _vae_debug("Couldn't fetch remote file " . $id);
  if ($file == "692 Image not available") return _vae_debug("Couldn't fetch remote file " . $id);
  if ($file == "693 Not yet encoded") return "tryagain.flv";
  if (!strlen($file)) return _vae_debug("Couldn't fetch remote file " . $id);
  return _vae_store_file($iden, $file, $ext, $filename);
}

function _vae_final($out) {
  throw new VaeFragment($out);
}

function _vae_find_dividers($tag) {
  $dividers = array();
  foreach ($tag['tags'] as $itag) {
    if ($itag['type'] == "divider" || $itag['type'] == "nested_divider") {
      $divider = array('type' => $itag['type']);
      $divider['every'] = (is_numeric($itag['attrs']['every']) ? $itag['attrs']['every'] : 1);
      if (_vae_contains_yield($itag)) {
        $divider['to_merge'] = $itag;
      } else {
        $divider['out'] = _vae_render_tags($itag, $context, $render_context);
      }
      $dividers[] = $divider;
    }
  }
  return $dividers;
}

function _vae_find_source($file, $ext = "") {
  foreach (array(".html", ".haml", ".haml.php", ".php", "") as $ext) {
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . "/" . $file . $ext)) return $file . $ext;
  }
  return false;
}

function _vae_flash($what, $type = 'msg', $which = "") {
  if (isset($_SESSION['__v:flash_new']) && isset($_SESSION['__v:flash_new']['messages']) && count($_SESSION['__v:flash_new']['messages'])) {
    foreach ($_SESSION['__v:flash_new']['messages'] as $msg) {
      if ($msg['msg'] == $what) return;
    }
  }
  $_SESSION['__v:flash_new']['messages'][] = array('msg' => $what, 'type' => $type, 'which' => $which);
  return true;
}

function _vae_flash_are_errors() {
  if (count($_SESSION['__v:flash']['messages'])) {
    foreach ($_SESSION['__v:flash']['messages'] as $msg) {
      if ($msg['type'] == 'err') return true;
    }
  }
  return false;
}

function _vae_flash_errors($errors, $which = "") {
  if (count($errors)) {
    foreach ($errors as $e) {
      $errstr .= "<li>$e</li>";
    }
    _vae_flash("We found the following errors with your submission.  Please correct them and try again:<ul>$errstr</ul>", 'err', $which);
    return true;
  }
  return false;
}

function _vae_form_prepare($a, &$tag, $context, $render_context) {
  global $_VAE;
  if ($a['_vae_form_prepared']) return $a;
  if ($a['path']) {
    $find_path =  ((substr($a['path'], 0, 8) == "confirm_") ? substr($a['path'], 8) : $a['path']);
    if (($value = _vae_request_param($a['path'], true)) && !is_array($value)) {
      $a['value'] = $value;
    } elseif (!$render_context->get("form_create_mode")) {
      $a['value'] = _vae_fetch_without_errors($find_path, $context);
    }
    if (!isset($a['name'])) {
      $a['name'] = $a['path'];
      if ($context) {
        $id = $context->formId();
        if (($id > 0) && ($render_context->get("form_context") != $context)) {
          if (!isset($a['id'])) $a['id'] = $a['name'] . "_" . $id;
          $a['name'] .= "[" . $id . "]";
        }
      }
    }
    if (!isset($a['id'])) $a['id'] = $a['name'];
  } else {
    if (($value = _vae_request_param($a['name'], true)) && _vae_flash_are_errors() && !is_array($value)) {
      $a['value'] = $value;
    }
  }
  if ($a['required']) {
    $special_requires = array('email','url','date','name','number','digits','nodigits','creditcard');
    $class = (in_array($a['required'], $special_requires) ? "required " . $a['required'] : "required");
    $a['class'] .= " " . $class;
  }
  if ($a['default'] && !strlen($a['value'])) $a['value'] = $a['default'];
  $tag['callback']['_form_prepared'] = true;
  $a['_vae_form_prepared'] = true;
  return $a;
}

function _vae_format_for_rss($input) {
  return htmlspecialchars(str_replace(array("\r", "\n"), " ", $input));
}

function _vae_gd_handle($d) {
  global $_VAE;
  $ll = $_VAE['config']['data_path'] . $d;
  if (!file_exists($ll) || is_dir($ll)) return null;
  if (strstr(strtolower($d), ".gif")) $tk = @imagecreatefromgif($ll);
  elseif (strstr(strtolower($d), ".png")) $tk = @imagecreatefrompng($ll);
  else $tk = @imagecreatefromjpeg($ll);
  return $tk;
}

function _vae_generate_relative_links() {
  global $_VAE;
  if ($_REQUEST['__vae_local'] || $_VAE['local_full_stack'] || isset($_VAE['settings']['generate_relative_links'])) {
    return true;
  } else {
    return false;
  }
}

function _vae_get_else(&$tag, $context, $render_context, $message = "") {
  global $_VAE;
  if (is_object($render_context)) {
    $render_context->set_in_place("else");
    $render_context->set_in_place("else2");
    if (!isset($_VAE['settings']['child_v_else'])) $render_context->set_in_place("else_message", $message);
  }
  if (count($tag['tags']) && isset($_VAE['settings']['child_v_else'])) {
    for ($i = 0; $i < count($tag['tags']); $i++) {
      if ($tag['tags'][$i]['type'] == "else") {
        return _vae_render_tags($tag['tags'][$i], $context, $render_context);
      }
    }
  }
  return (isset($_VAE['settings']['child_v_else']) ? $message : "");
}

function _vae_global_id($index = "") {
  global $_VAE;
  if (!strlen($index)) {
    if ($_ENV['TEST']) $index = "TESTGLOBID";
    else $index = md5(rand().microtime());
  }
  if (!isset($_VAE['globalid'])) $_VAE['globalid'] = "vae_generated_might_change";
  return $_VAE['globalid'] . "_" . $index;
}

function _vae_h($text, $charset = null) {
  if (is_array($text)) return array_map('h', $text);
  if (empty($charset)) $charset = 'UTF-8';
  return htmlspecialchars($text, ENT_QUOTES, $charset);
}

function _vae_handleob($vaeml) {
  global $_VAE;
  try {
    _vae_tick("Parse PHP/HTML code and execute PHP Code", true);
    if ($_REQUEST['__debug']) {
      unset($_SESSION['__v:store']['shipping']);
    }
    $out = _vae_interpret_vaeml($vaeml);
    if ((strlen($_VAE['debug']) || $_REQUEST['__force']) && $_REQUEST['__debug']) _vae_error("Debugging Traces Available");
    if (isset($_VAE['run_hooks'])) {
      foreach ($_VAE['run_hooks'] as $to_run) {
        _vae_run_hooks($to_run[0], $to_run[1]);
      }
    }
    if (isset($_VAE['ticks'])) return _vae_render_timer();
    _vae_statsd_timing("render_time", ceil((microtime(true)-$_VAE['start_tick'])*1000));
    if (!$_REQUEST['__proxy'] && $_SESSION['__v:pre_ssl_host'] && ($_SESSION['__v:pre_ssl_host'] != $_SERVER['HTTP_HOST']) && _vae_ssl() && !$_VAE['ssl_required'] && !$_VAE['local_full_stack'] && !$_REQUEST['__vae_local'] && !$_REQUEST['__verb_local'] && !$_REQUEST['__xhr'] && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])!='xmlhttprequest') {
      $_VAE['force_redirect'] = "http://" . $_SESSION['__v:pre_ssl_host'] . $_SERVER['REQUEST_URI'];
    }
    if (isset($_VAE['force_redirect']) && $_SESSION['__v:flash']['redirected']) {
      if (isset($_SESSION['__v:flash']) && isset($_SESSION['__v:flash']['messages']) && count($_SESSION['__v:flash']['messages'])) {
        foreach ($_SESSION['__v:flash']['messages'] as $m) {
          $_SESSION['__v:flash_new']['messages'][] = $m;
        }
      }
    } elseif (isset($_SESSION['__v:flash_new']) && isset($_SESSION['__v:flash_new']['messages']) && count($_SESSION['__v:flash_new']['messages'])) {
      if (!isset($_VAE['force_redirect']) && $_SERVER['REQUEST_METHOD'] != "GET") $_VAE['force_redirect'] = $_SERVER['PHP_SELF'];
      if (_vae_is_xhr() && ($_VAE['force_redirect'] == $_SERVER['PHP_SELF'])) {
        foreach ($_SESSION['__v:flash_new']['messages'] as $m) {
          if ($m['type'] == "err") {
            return "__err=" . strip_tags(str_replace("<li>", "\\n - ", $m['msg']));
          }
        }
      }
    }
    if (isset($_VAE['force_redirect'])) $_SESSION['__v:flash_new']['redirected'] = 1;
    if (count($_POST) && !$_VAE['trash_post_data']) $_SESSION['__v:flash_new']['post'] = $_POST;
    if (isset($_SESSION['__v:flash_new'])) {
      $_SESSION['__v:flash'] = $_SESSION['__v:flash_new'];
    } else {
      unset($_SESSION['__v:flash']);
    }
    unset($_SESSION['__v:error_handling']);
    if (isset($_VAE['session_cookies'])) {
      foreach ($_VAE['session_cookies'] as $k => $v) {
        $_SESSION[$k] = $v;
      }
    }
    unset($_SESSION['__v:flash_new']);
    if (isset($_VAE['final'])) return $_VAE['final'];
    if (isset($_VAE['force_redirect'])) {
      $url = $_VAE['force_redirect'];
      if (_vae_is_xhr()) $url .= (strstr($url, "?") ? "&" : "?") . "__xhr=1";
      if ($_REQUEST['__debug']) $url .= (strstr($url, "?") ? "&" : "?") . "__debug=" . $_REQUEST['__debug'];
      if ($_REQUEST['__host']) $url .= (strstr($url, "?") ? "&" : "?") . "__host=" . $_REQUEST['__host'];
      if (strstr($url, "<script>")) $url = "/";
      if ((_vae_is_xhr() && strstr($url, "www.paypal.com")) || $_REQUEST['__full_redirect']) {
        return "<script type='text/javascript'>window.location.href='" . $url . "'; window.vRedirected = true;</script>";
      }
      @header("Location: " . $url);
      return "Redirecting to " . _vae_h($url);
    }
    if (strtolower(substr($_SERVER['SCRIPT_FILENAME'], -4)) == ".xml") @header("Content-Type: application/xml");
    elseif (strtolower(substr($_SERVER['SCRIPT_FILENAME'], -4)) == ".rss" || $_VAE['serve_rss']) @header("Content-Type: application/rss+xml");
    if ($out == "__STREAM__") return file_get_contents($_VAE['stream']);
    $out = _vae_merge_session_data($out);
  } catch (Exception $e) {
    if ((substr(get_class($e), 0, 1) == "T") && !isset($_SESSION['__v:error_handling']['recover_from_thrift_exception'])) {
      $_SESSION['__v:error_handling']['recover_from_thrift_exception'] = true;
      sleep(5);
      Header("Location: " . $_SERVER['PHP_SELF']);
      return "";
    }
    return _vae_render_error($e);
  }
  return $out;
}

function _vae_hide_dir($filename) {
  return str_replace($_SERVER['DOCUMENT_ROOT'], "", str_replace("/ebs/vhosts", "/var/www/vhosts", $filename));
}

function _vae_log_error($message, $class_name, $backtrace) {
  global $_VAE;
  if ($_ENV['TEST']) {
    $_VAE['vae_error_logging_sent']++;
    return;
  }
  $safe_request = array();
  foreach ($_REQUEST as $k => $v) {
    if (!in_array($k, $_VAE['unsafe_params'])) {
      $safe_request[$k] = $v;
    }
  }
  $safe_session = array();
  foreach ($_SESSION as $k => $v) {
    if (!in_array($k, $_VAE['unsafe_params'])) {
      $safe_session[$k] = $v;
    }
  }
  $data = array(
    'class_name' => $class_name, 'message' => $message, 'backtrace' => json_encode(_vae_render_backtrace($backtrace, 'vae_report')),
    'url' => _vae_proto() . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
    'params' => json_encode($safe_request), 'session' => json_encode($safe_session),
    'server' => json_encode($_SERVER), 'hostname' => gethostname()
  );
  _vae_local_log("[ERROR] $class_name: $message");
  if ($_REQUEST['__vae_local'] || $_VAE['local_full_stack']) return;
  _vae_rest($data, "api/site/v1/report_error", "error_report", null, null, true);
}

function _vae_html2rgb($color) {
  if ($color[0] == '#') $color = substr($color, 1);
  if (strlen($color) == 6) list($r, $g, $b) = array($color[0].$color[1], $color[2].$color[3], $color[4].$color[5]);
  elseif (strlen($color) == 3) list($r, $g, $b) = array($color[0].$color[0], $color[1].$color[1], $color[2].$color[2]);
  else return array(0, 0, 0);
  $r = hexdec($r);
  $g = hexdec($g);
  $b = hexdec($b);
  return array($r, $g, $b);
}

function _vae_htmlarea($text, $a, $offsite = false) {
  global $_VAE;
  if (!$a['nohtml']) $text = _vae_urlize($text);
  $section = $a['section'];
  $width = $a['asset_width'];
  $height = $a['asset_height'];
  $quality = $a['asset_quality'];
	$preserve_filename = ($a['asset_filename'] ? '"' . $a['asset_filename'] . '"' : "false");
	if (strstr($text, "<v")) {
	  list($parse_tree, $render_context) = _vae_parse_vaeml($text, "[Rich Text Structure]");
    $text = _vae_render_tags($parse_tree, null, $render_context);
	}
  if (strlen($section)) {
    if (strstr($section, "+")) {
      $section = str_replace("+", "", $section);
      if (is_numeric($section)) {
        $e = explode("<hr />", $text, $section + 1);
        $text = $e[$section];
      }
    } elseif (is_numeric($section)) {
      $e = explode("<hr />", $text);
      $text = $e[$section];
    }
  }
  if ($a['nohtml'] || $a['maxlength']) {
    $text = str_replace(array("\r", "\n"), "", $text);
    $text = str_replace(array("</p>", "<br />", "<br>", "<br/>"), "\n", $text);
    $text = preg_replace("/\n\n(\n*)/", "\n\n", $text);
    $text = strip_tags(html_entity_decode($text));
    if ($a['entities']) $text = htmlspecialchars($text);
    if ($a['maxlength'] && (strlen($text) > $a['maxlength'])) $text = mb_substr($text, 0, $a['maxlength']) . "...";
    $text = $a['before'] . $text . $a['after'];
    return $text;
  }
  if ($a['links_to_new_window'] == "external") {
    $text = preg_replace('/(<a([^>]*))(href=(|"|\')http(|s):)/', '$1target="_blank" $3', $text);
  } elseif ($a['links_to_new_window']) {
    $text = str_replace('<a ', '<a target="_blank" ', $text);
  }
  if (strlen($a['audio_player_vars'])) $audio_player_vars = htmlspecialchars("&" . str_replace("'", "", $a['audio_player_vars']));
  $text = str_replace("<hr />", "", $text);
  $size_video = (strlen($width) && strlen($height) && ($width < 400 || $height < 300));
  $player_width = ($size_video ? $width : 400);
  $player_height = ($size_video ? $height : 300);
  $text = preg_replace_callback("/<img([^>]*)\/(VAE|VERB)_HOSTED_AUDIO\/([0-9]*)([^>]*)>/", create_function(
    '$matches', ($offsite ? "return '';" :
    '$id = _vae_global_id();
     _vae_needs_javascript("audio-player");
     $file = "' . _vae_absolute_data_url() . '" . vae_asset($matches[3]);
     return \'<object type="application/x-shockwave-flash" data="' . $_VAE['config']['asset_url'] . 'audioplayer.swf" id="audioplayer\' . $id . \'" height="24" width="290">
      <param name="movie" value="' . $_VAE['config']['asset_url'] . 'audioplayer.swf">
      <param name="FlashVars" value="playerID=\' . $id . \'&amp;soundFile=\' . $file . \'' . $audio_player_vars . '">
      <param name="quality" value="high">
      <param name="menu" value="false">
      <param name="wmode" value="transparent">
      </object>\';')), $text);
  $text = preg_replace_callback("/<img([^>]*)src=(\"|'|)([^>]*)\/(VAE|VERB)_HOSTED_IMAGE\/([0-9]*)(\"|'|)/", create_function(
    '$matches',
    'return "<img" . $matches[1] . "src=\"' . _vae_absolute_data_url() . '" . vae_asset($matches[5], "' . $width . '","' . $height . '", "' . $quality . '", ' . $preserve_filename . ') . "\"";'), $text);
  $text = preg_replace_callback("/<img([^>]*)\/(VAE|VERB)_HOSTED_VIDEO\/([0-9]*)([^>]*)>/", create_function(
    '$matches', ($offsite ? "return '';" :
    '$id = _vae_global_id();
     $file = vae_asset($matches[3]);
     if ($file == "tryagain.flv") $file = "' . $_VAE['config']['backlot_url'] . '/videos/" . $file;
     else $file = "' . _vae_absolute_data_url() . '" . $file;
     _vae_needs_javascript("jwplayer");
     return \'<div id="\' . $id . \'_container">You need to <a href="http://www.macromedia.com/go/getflashplayer">get the Flash Player</a> to see this video.</div>
     <script type="text/javascript">
       jwplayer("\' . $id . \'_container").setup({
         flashplayer: "' . $_VAE['config']['asset_url'] . 'player.swf",
         file: "\' . $file . \'",
         height: ' . $player_height . ',
         width: ' . $player_width . '
       });
     </script>\';')), $text);
  $text = $a['before'] . $text . $a['after'];
  return $text;
}

function _vae_humanize($a) {
  return ucwords(str_replace("_", " ", $a));
}

function _vae_in_ob() {
  if ($_ENV['TEST']) return true;
  $handlers = ob_list_handlers();
  return !(!count($handlers) || (count($handlers) == 1 && $handlers[0] == "default output handler"));
}

function _vae_inject_assets($out) {
  global $_VAE;
  $html = array();
  if (isset($_VAE['javascripts']['jquery']) && !preg_match('/<script[^>]*src=[^\n]*jquery([-0-9a-z.])*(.min.js|.js)/', $out) && !_vae_is_xhr()) {
    $bottom .= '<script type="text/javascript" src="' . $_VAE['config']['asset_url'] . 'jquery.js"></script>';
  }
  if (is_array($_VAE['javascripts']) && (count($_VAE['javascripts']) > 0)) {
    foreach ($_VAE['javascripts'] as $s => $garbage) {
      if ($s == "jquery") continue;
      foreach ($_VAE['assets'] as $group => $assets) {
        foreach ($assets as $asset) {
          if (strstr($asset, str_replace(".min", "", $s))) $s = null;
        }
      }
      if (strlen($s)) {
        if (preg_match('/^http(s|):\/\//', $s)) {
          $bottom .= '<script type="text/javascript" src="' . $s . '"></script>';
        } else {
          $bottom .= '<script type="text/javascript" src="' . $_VAE['config']['asset_url'] . $s . '.js"></script>';
        }
      }
    }
  }
  if (count($_VAE['assets'])) {
    foreach ($_VAE['assets'] as $group => $assets) {
      $iden = "";
      foreach ($assets as $asset) {
        $md5 = @md5_file($_SERVER['DOCUMENT_ROOT'] . "/" . $asset);
        _vae_dependency_add($asset, $md5);
        $iden .= $md5;
        if (strstr($asset, ".sass") || strstr($asset, ".scss")) {
          $content = file_get_contents($_SERVER['DOCUMENT_ROOT'] . "/" . $asset);
          require_once(dirname(__FILE__)."/haml.php");
          $deps = _vae_sass_deps_check($content,dirname($_SERVER['DOCUMENT_ROOT'] . "/" . $asset), true);
          foreach($deps as $filename => $hash){
            _vae_dependency_add($filename, $hash);
            $iden .= $hash;
          }
        }
      }
      $iden = "asset" . md5($iden);
      if ($cache = _vae_long_term_cache_get($iden)) {
        $html[$group] = _vae_asset_html($_VAE['asset_types'][$group], _vae_absolute_data_url() . $cache);
      } else {
        $raw = "";
        foreach ($assets as $asset) {
          $content = file_get_contents($_SERVER['DOCUMENT_ROOT'] . "/" . $asset);
          if (strstr($asset, ".sass") || strstr($asset, ".scss")) {
            require_once(dirname(__FILE__)."/haml.php");
            $content = _vae_sass($content, false, dirname($_SERVER['DOCUMENT_ROOT'] . "/" . $asset), strstr($asset, ".scss"));
          }
          if ($_VAE['asset_types'][$group] != "js") {
            $_VAE['assets_css_callback'] = (substr($asset, 0, 1) == "/" ? "" : "/") . dirname($asset);
            $content = preg_replace_callback("/url\\((\"|'|)([^\"')]*)(\"|'|)\\)/", "_vae_inject_assets_css_callback", $content);
          }
          $raw .= $content . "\n";
        }
        if ($_VAE['asset_types'][$group] == "js") {
          $js_out = _vae_rest(array('raw' => $raw), "api/site/v1/compress", "js");
          if ($js_out != "BAD" && $js_out != "") {
            $raw = $js_out;
          }
        } elseif (!$_VAE['settings']['dont_minify_css_assets']) {
          require_once(dirname(__FILE__) . "/../vendor_old/csstidy/class.csstidy.php");
          $css = new csstidy();
          $css->parse($raw);
          $raw = $css->print->plain();
        }
        $html[$group] = _vae_asset_html($_VAE['asset_types'][$group], _vae_absolute_data_url() . _vae_store_file($iden, $raw, ($_VAE['asset_types'][$group] == "js" ? "js" : "css")));
      }
    }
  }
  if (isset($_VAE['on_dom_ready'])) {
    if (_vae_is_xhr()) {
      $out .= _vae_script_tag(implode("\n", $_VAE['on_dom_ready']));
    } else {
      $bottom .= _vae_script_tag('jQuery(function() { ' . implode("\n", $_VAE['on_dom_ready']) . ' });');
    }
  }
  if (isset($_VAE['asset_inject_points'])) {
    foreach ($_VAE['asset_inject_points'] as $group => $points) {
      for ($i = 1; $i < $points; $i++) {
        $out = str_replace("<_VAE_ASSET_" . $group . $i . ">", "", $out);
      }
      $out = str_replace("<_VAE_ASSET_" . $group . $i . ">", $html[$group], $out);
    }
  }
  $out = _vae_inject_at_bottom_of_head($out, $bottom);
  return $out;
}

function _vae_inject_at_bottom_of_head($out, $html) {
  if (strstr($out, "</head>")) {
    return str_replace("</head>", $html . "</head>", $out);
  } else {
    return $html . $out;
  }
}

function _vae_inject_assets_css_callback($a) {
  global $_VAE;
  $url = $a[2];
  if ((substr($url, 0, 1) != "/") && !strstr($url, "://")) $url = $_VAE['assets_css_callback'] . "/" . $url;
  $url = _vae_cdn_timestamp_url($url);
  return "url(" . $a[1] . $url . $a[3] . ")";
}

function _vae_interpret_vaeml($vaeml) {
  global $_VAE;
  $out = "";
  $callbacks = array();
  $_VAE['callback_stack'] = array();
  $old_session = $_SESSION;
  if (!strstr($vaeml, "<v") && !strstr($_VAE['filename'], ".haml")) return _vae_post_process($vaeml);
  $cache_key = $_VAE['cache_key'] . "3" . md5($vaeml);
  if (count($_VAE['callbacks'])) {
    foreach ($_VAE['callbacks'] as $name => $func) {
      if (isset($_REQUEST['__v:' . $name])) {
        $callbacks[] = $name;
      }
    }
  }
  if (count($callbacks)) {
    _vae_tick("can't use cached version because there are callbacks");
  } elseif (isset($_SESSION['__v:flash'])) {
    _vae_tick("can't use cached version because there is data in the flash bucket");
  } elseif (!isset($_REQUEST['__vae_local']) && !isset($_REQUEST['__verb_local'])) {
    $cached = _vae_short_term_cache_get($cache_key);
    if (is_array($cached) && $cached[0] == "c") {
      $out = $cached[1];
      if (is_array($cached[2]) && count($cached[2])) {
        foreach ($cached[2] as $filename => $hash) {
          if ($hash == "s") {
            if (isset($_SESSION[$filename])) {
              _vae_tick("can't use cached version because $filename is in my session");
              unset($out);
              break;
            }
          } else {
            if (@md5_file($_SERVER['DOCUMENT_ROOT'] . "/" . $filename) != $hash) {
              _vae_tick("can't use cached version because $filename has changed");
              unset($out);
              break;
            }
          }
        }
      }
    } else {
      _vae_tick("no cached version");
    }
  }
  if (strlen($out) && !$_REQUEST['__debug']) {
    $from_cache = true;
    $_VAE['session_cookies'] = $cached[3];
    _vae_tick("read HTML from cache");
  } else {
    _vae_set_initial_context();
    _vae_tick("set initial context");
    list($parse_tree, $render_context) = _vae_parse_vaeml($vaeml, $_VAE['filename']);
    _vae_tick("parse VaeML");
    try {
      $out = _vae_render_tags($parse_tree, $_VAE['context'], $render_context);
    } catch (VaeFragment $e) {
      $out = $e->getMessage();
    }
    if (isset($_VAE['assets']) || isset($_VAE['javascripts'])) $out = _vae_inject_assets($out);
    if (isset($_VAE['prepend'])) $out = $_VAE['prepend'] . $out;
    $out = _vae_post_process($out);
    _vae_tick("render HTML (no cache)");
  }
  foreach ($_VAE['callback_stack'] as $name => $tag) {
    if (_vae_run_hooks($name) != false) {
      $func = $_VAE['callbacks'][$name];
      if (isset($func['filename'])) require_once(dirname(__FILE__)."/".$func['filename']);
      $ret = call_user_func($func['callback'], $tag);
      _vae_run_hooks("after:" . $name);
      return $ret;
    }
  }
  if ($_SESSION != $old_session) {
    _vae_tick("can't cache because the session changed");
  } elseif (isset($_SESSION['__v:flash'])) {
    _vae_tick("can't cache because some data got flashed");
  } elseif (headers_sent()) {
    _vae_tick("can't cache because headers have been sent");
  } elseif (isset($from_cache)) {
    _vae_tick("can't cache because this one already came from the cache");
  } elseif (isset($_VAE['cant_cache'])) {
    _vae_tick("can't cache because my var says so: " . $_VAE['cant_cache']);
  } elseif (_vae_ssl()) {
    _vae_tick("can't cache because we be ssl");
  } elseif (($out != false) && !isset($_VAE['force_redirect'])) {
    $dependencies = (isset($_VAE['dependencies']) ? $_VAE['dependencies'] : "");
    _vae_short_term_cache_set($cache_key, array("c", $out, $_VAE['dependencies'], $_VAE['session_cookies']), 0, 1800);
    _vae_tick("cached page");
  }
  return $out;
}

function _vae_is_xhr() {
  return strstr($_SERVER['HTTP_X_REQUESTED_WITH'], "XML") || ($_REQUEST['__xhr']);
}

function _vae_jsesc($a) {
  return str_replace(array("\n", "\"", "'"), array("\\n", "\\\"", "&#39;"), trim($a));
}


function _vae_load_settings() {
  global $_VAE, $_VERB;
  if (isset($_VAE['settings'])) return;
  if (!file_exists($_VAE['config']['data_path'] . "settings.php")) {
    _vae_update_settings_feed();
  }
  require_once($_VAE['config']['data_path'] . "settings.php");
  if (isset($_VERB['settings'])) $_VAE['settings'] = $_VERB['settings'];
  @date_default_timezone_set($_VAE['settings']['timezone']);
}


function _vae_local($filename = "") {
  global $_VAE;
  $base_key = "__vae_local" . $_SERVER['DOCUMENT_ROOT'] . $_REQUEST['__vae_local'] . $_REQUEST['__verb_local'];
  if ($_REQUEST['__local_username']) {
    echo _vae_local_authenticate($base_key);
    return _vae_die();
  }
  $authorized = _vae_long_term_cache_get($base_key . "auth");
  if ($authorized != "GOOD") {
    _vae_error("Your Local Development Session expired.  Please restart the Local Preview server and try again.");
  }
  ini_set('display_errors', true);
  $base_key .= "f";
  if ($_REQUEST['__verb_local_files']) $_REQUEST['__vae_local_files'] = $_REQUEST['__verb_local_files'];
  if (count($_REQUEST['__vae_local_files'])) {
    foreach ($_REQUEST['__vae_local_files'] as $fname => $file) {
      _vae_long_term_cache_set($base_key . $fname, $file, 1);
    }
  }
  $_VAE['local'] = $base_key;
  if (!strlen($filename)) $filename = $_SERVER['SCRIPT_NAME'];
  list($filename, $script) = _vae_src($filename);
  _vae_set_cache_key();
  $_VAE['filename'] = $filename;
  $vae_php = _vae_long_term_cache_get($base_key . "/__vae.php");
  if (strlen($vae_php)) _vae_local_exec($vae_php);
  $verb_php = _vae_long_term_cache_get($base_key . "/__verb.php");
  if (strlen($verb_php)) _vae_local_exec($verb_php);
  if (strstr($filename, ".sass") || strstr($filename, ".scss")) {
    require_once(dirname(__FILE__)."/haml.php");
    echo _vae_sass($script, true, dirname($filename), strstr($filename, ".scss"));
  } else {
    ob_start(_vae_handleob);
    _vae_local_exec($script);
  }
  _vae_die();
}

function _vae_local_authenticate($base_key) {
  global $_VAE;
  $out = _vae_rest(array(), "api/site/v1/authorize?username=" . urlencode($_REQUEST['__local_username']) . "&password=" . urlencode($_REQUEST['__local_password']), "subversion");
  if ($out == "GOOD") {
    _vae_long_term_cache_set($base_key . "auth", $out, 1);
    if ($_REQUEST['__local_version'] != $_VAE['local_newest_version']) {
      if (str_replace(".", "", $_REQUEST['__local_version']) < 60) {
        return "MSG\n*****\nYour copy of the Vae Local Development Environment is out of date.\n\nWe have changed distribution of the Vae Local Development Environment to use Ruby gems.\n\nThis will make installation of future updates much easier!\n\nTo get the new version, you should first remove your old version by doing the following:\n\nrm -rf /usr/local/bin/vae\nrm -rf /usr/local/vae\n\nThen, install the new version by following the instructions at:\nhttp://docs.vaeplatform.com/vae_local\n\n*****\n\n";
      } else {
        return "MSG\n*****\nYour copy of the Vae Local Development Environment is out of date.\n\nPlease exit and run:\n\ngem update vae\n\n*****\n\n";
      }
    } else {
      return $out;
    }
  }
  return "BAD";
}

function _vae_local_exec($script) {
  global $_VAE;
  preg_match_all("/\\$([a-zA-Z_\\x7f-\\xff][a-zA-Z0-9_\\x7f-\\xff]*)/", $script, $matches);
  if (is_array($matches) && is_array($matches[0])) {
    foreach ($matches[0] as $key) {
      if ($key != '$this') $glbls .= (strlen($glbls) ? ", " : "") . $key;
    }
    if (strlen($glbls)) $script = "<?php global " . $glbls . "; ?>" . $script;
  }
  $temp = tempnam("/tmp", "VLOCAL");
  file_put_contents($temp, $script);
  require_once($temp);
  unlink($temp);
}

function _vae_local_needs($filename) {
  if ($_REQUEST['__vae_local']) {
    return _vae_render_final("__vae_local_needs=" . $filename);
  } elseif ($_REQUEST['__verb_local']) {
    return _vae_render_final("__verb_local_needs=" . $filename);
  }
}

function _vae_log($msg) {
  global $_VAE;
  if (!is_string($msg)) $msg = serialize($msg);
  $_VAE['log'] .= $msg . "\n";
}

function _vae_logstash_send($log_msg) {
  global $_VAE;
  if ($_ENV['TEST'] || $_VAE['local_full_stack']) return;
  $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
  @socket_sendto($sock, $log_msg, strlen($log_msg), 0, "public.logs.av", 5000);
}

function _vae_mail($to, $subj, $body, $headers) {
  global $_VAE;
  if ($_ENV['TEST']) {
    $_VAE['mail_sent']++;
  } else {
    mail($to, $subj, $body, $headers);
  }
}

function _vae_make_filename($ext, $filename = null) {
  global $_VAE;
  if ($filename) {
    $filename = substr(preg_replace("/[^a-z0-9_\-]/", "", preg_replace('/\s/', "-", preg_replace('/\s\s+/', ' ', strtolower($filename)))), 0, 55);
    $i = 0;
  }
  do {
    if ($filename) {
      $newname = $filename . ($i == 0 ? "" : "." . $i) . "." . $ext;
      $i++;
    } else {
      $newname = md5(mt_rand()) . "." . $ext;
    }
  } while (file_exists($_VAE['config']['data_path'] . $newname) || _vae_long_term_cache_exists($newname));
  return $newname;
}

function _vae_merge_data_from_tags(&$tag, &$data, &$errors, $nested = false) {
	global $_VAE;
  if (_vae_akismet($tag['attrs'])) {
    $errors[] = "This post looks spammy. Please do something to make it not hit our spam filters.";
    return;
  }
  if ($tag['attrs']['nested']) $nested = true;
  if ($nested) {
    foreach ($_POST as $k => $v) {
      if (!in_array($k, array("VaeSession", "id", "locale", "page", "recaptcha_challenge_field", "recaptcha_response_field")) && (substr($k, 0, 3) != "__v") && (substr($k, 0, 3) != "utm")) {
        if (is_array($v)) $v = implode(", ", $v);
        $data[$k] = $v;
      }
    }
  }
	$tags = $tag['tags'];
	if (count($tags) && is_array($tags)) {
  	foreach ($tags as $itag) {
      $err = "";
  	  if (isset($_VAE['form_items'][$itag['type']]) && isset($itag['callback']['_form_prepared'])) {
  	    $name = str_replace("[]", "", $itag['attrs']['name']);
  	    $value = "";
  	    if (!strlen($name)) $name = $itag['attrs']['path'];
  	    if ($itag['type'] == "captcha") {
  	      if (isset($_VAE['recaptcha']['private'])) {
            $resp = recaptcha_check_answer($_VAE['recaptcha']['private'], _vae_remote_addr(), $_POST["recaptcha_challenge_field"], $_POST["recaptcha_response_field"]);
            if (!$resp->is_valid) $errors[] = "You entered the wrong word(s) in the reCAPTCHA window.  Please try again.";
            unset($_VAE['recaptcha']['private']);
          }
          continue;
  	    } elseif ($itag['type'] == "date_select") {
  	      $time = strtotime(_vae_request_param($name . "_month") . "/" . _vae_request_param($name . "_day") . _vae_request_param($name . "_year"));
  	      $value = ($time > 0 ? strftime("%Y-%m-%d", $time) : "");
  	    } elseif ($itag['type'] == "file_field") {
  	      if ($_FILES[$name] && $_FILES[$name]['name']) {
  	        $sep = explode(".", $_FILES[$name]['name']);
  	        $ext = array_pop($sep);
  	        $value = _vae_absolute_data_url() . _vae_store_file(null, $_FILES[$name]['tmp_name'], $ext, "upload_" . implode("_", $sep), "uploaded");
  	      }
  	    } else {
  	      $value = _vae_request_param($name);
  	      if (is_array($value)) {
  	        $value = implode(", ", $value);
  	      }
  	    }
  	    if ($itag['attrs']['required'] && !$nested) {
  	      if ($itag['attrs']['required'] == "creditcard") {
  	        if (!_vae_valid_creditcard($value)) $err = "must be a valid credit card number.";
  	      } elseif ($itag['attrs']['required'] == "date") {
  	        if (!_vae_valid_date($value)) $err = "must be a valid date.";
  	      } elseif ($itag['attrs']['required'] == "digits") {
  	        if (!_vae_valid_digits($value)) $err = "must only contain numeric digits.";
  	      } elseif ($itag['attrs']['required'] == "nodigits") {
  	        if (!_vae_valid_nodigits($value)) $err = "must not contain any numeric digits.";
  	      } elseif ($itag['attrs']['required'] == "email") {
  	        if (!_vae_valid_email($value)) $err = "must be a valid E-Mail address.";
  	      } elseif ($itag['attrs']['required'] == "name") {
  	        if ((strlen($value) < 3)  || !strstr($value, " ")) $err = "must contain a first and last name.";
  	      } elseif ($itag['attrs']['required'] == "number") {
  	        if (!is_numeric($value)) $err = "must be a valid number.";
  	      } elseif ($itag['attrs']['required'] == "url") {
  	        if (!_vae_valid_url($value)) $err = "must be a valid URL.";
          } elseif (!strlen(trim($value))) {
            $country = $_REQUEST[str_replace(array("state","zip"), "country", $name)];
            if ($itag['attrs']['required'] == "uscanada") $itag['attrs']['required'] = "state";
            if ($itag['attrs']['required'] != "state" || (isset($_VAE['states'][$country]))) {
              $err = "can't be blank.";
            }
  	      }
  	    }
  	    if (!strlen($err) && (substr($name, 0, 8) == "confirm_") && !$nested) {
  	      if ($value != $_REQUEST[substr($name, 8)]) {
  	        $name = substr($name, 8);
  	        $err = "doesn't match confirmation.";
  	      }
  	    }
  	    if (strlen($err)) {
  	      $errors[] = _vae_humanize($name) . " " . $err;
  	    } else {
  	      if (substr($name, 0, 8) != "confirm_") $data[$name] = $value;
  	    }
  	  } elseif (is_array($itag['tags'])) {
  	    _vae_merge_data_from_tags($itag, $data, $errors, $nested);
  	  }
  	}
	}
}

function _vae_merge_divider($data, $divider, $rendered, $context, $render_context, $reverse = false) {
  if (($rendered % $divider['every']) == 0) {
    if ($divider['to_merge']) {
      $outer = $divider['to_merge'];
      $inner = array('tags' => array(array('innerhtml' => $data)));
      _vae_merge_yield($outer, $inner, $render_context);
      $data = _vae_render_tags($outer, $context, $render_context);
    } elseif ($rendered > 0) {
      if ($reverse) $data .= $divider['out'];
      else $data = $divider['out'] . $data;
    }
  }
  return $data;
}

function _vae_merge_dividers($data, $dividers, $rendered, $context, $render_context, $reverse = false, $type = "divider") {
  foreach ($dividers as $divider) {
    if ($divider['type'] == $type) {
      $data = _vae_merge_divider($data, $divider, $rendered, $context, $render_context, $reverse);
    }
  }
  return $data;
}

function _vae_merge_session_data($out) {
  while (preg_match("/<__VAE_SESSION_DUMP=([^>]*)>/", $out, $matches)) {
    $out = str_replace($matches[0], $_SESSION[$matches[1]], $out);
  }
  return $out;
}

function _vae_minify_js($js) {
  return trim(str_replace(array("\r", "\n"), "", $js));
}

function _vae_multipart_mail($from, $to, $subject, $text, $html, $reply_to = false, $headers = null) {
  if ($headers == null) $headers = "";
  $headers .= 'From: ' . $from . "\n";
  if ($reply_to) {
    $headers .= 'Reply-To: ' . $reply_to . "\n";
  }
  $headers .= 'Return-Path: ' . $from . "\n";
  if (!strlen($html)) {
    return _vae_mail($to, $subject, $text, $headers);
  }
  $boundary = md5(uniqid(time()));
  $headers .= 'MIME-Version: 1.0' ."\n";
  $headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
  $body = $text . "\n";
  $body .= '--' . $boundary . "\n";
  $body .= 'Content-Type: text/plain; charset=ISO-8859-1' ."\n";
  $body .= 'Content-Transfer-Encoding: 8bit'. "\n\n";
  $body .= $text . "\n";
  $body .= '--' . $boundary . "\n";
  $body .= 'Content-Type: text/HTML; charset=ISO-8859-1' ."\n";
  $body .= 'Content-Transfer-Encoding: 8bit'. "\n\n";
  $body .= $html . "\n";
  $body .= '--' . $boundary . "--\n";
  return _vae_mail($to, $subject, $body, $headers);
}

function _vae_natural_time($time) {
  $diff = time() - $time;
  if ($diff < 45) return "less than a minute ago";
  if ($diff < 90) return "about a minute ago";
  $diff /= 60;
  if ($diff < 45) return ceil($diff) . " minutes ago";
  $diff /= 60;
  if ($diff < 22) return ceil($diff) . " hours ago";
  $diff /= 24;
  if ($diff < 2) return "1 day ago";
  return ceil($diff) . " days ago";
}

function _vae_needs_javascript() {
  global $_VAE;
  if (!is_array($_VAE['javascripts'])) $_VAE['javascripts'] = array();
  foreach (func_get_args() as $arg) {
    $_VAE['javascripts'][$arg] = true;
  }
}

function _vae_needs_jquery() {
  global $_VAE;
  if (!is_array($_VAE['javascripts'])) $_VAE['javascripts'] = array();
  $_VAE['javascripts']['jquery'] = true;
  foreach (func_get_args() as $arg) {
    if (strlen($arg)) $_VAE['javascripts']['jquery.' . $arg . '.min'] = true;
  }
}

function _vae_newsletter_subscribe($code, $email, $confirm_field = null) {
  $codes = explode(",", $code);
  if ($confirm_field) {
    if (!$_REQUEST[$confirm_field]) return;
    if (is_array($_REQUEST[$confirm_field])) {
      $codes = array();
      foreach ($_REQUEST[$confirm_field] as $code) {
        $codes[] = $code;
      }
    }
  }
  foreach ($codes as $code) {
    $out = _vae_simple_rest('http://r.newsletter-agent.com/' . $code, "email=" . $email . "&customer_id=" . $_SESSION['__v:store']['customer_id']);
  }
  return $out;
}

function _vae_on_dom_ready($js) {
  global $_VAE;
  _vae_needs_jquery();
  if (!isset($_VAE['on_dom_ready'])) $_VAE['on_dom_ready'] = array();
  $_VAE['on_dom_ready'][] = $js;
}

function _vae_oneline($a, $context, $attribute_type = false) {
  global $_VAE;
  if (preg_match('/SIZE\(([^)]*)\)/i', $a, $regs)) {
    $a = $regs[1];
    $getsize = true;
  }
  if (preg_match('/JOIN\(([^)]*)\)/i', $a, $regs)) {
    $out = array();
    $values = _vae_fetch($regs[1], $context, array('assume_numbers' => true));
    if (is_object($values)) {
      foreach ($values as $value) {
        $out[] = _vae_oneline_get($value, $getsize, $params);
      }
      return implode(",", $out);
    }
    return $values;
  } elseif (preg_match('/SUM\(([^)]*)\)/i', $a, $regs)) {
    $out = 0;
    $values = _vae_fetch($regs[1], $context, array('assume_numbers' => true));
    if (is_object($values)) {
      foreach ($values as $value) {
        $out += _vae_oneline_get($value, $getsize, $params);
      }
      return $out;
    }
    return $values;
  } elseif (preg_match('/PARAM\(([^)]*)\)/i', $a, $regs)) {
    $out = $_REQUEST[$regs[1]];
  } else {
    $params = explode(",", $a);
    $last_paren = 0;
    for ($i = 0; $i < count($params); $i++) {
      if (strstr($params[$i], "(") || strstr($params[$i], ")")) $last_paren = $i;
    }
    if ($last_paren > 0) {
      for ($i = 0; $i <= $last_paren; $i++) {
        if ($i > 0) $query .= ",";
        $query .= array_shift($params);
      }
      array_unshift($params, $query);
    }
    $query = $params[0];
    $value = _vae_fetch($query, $context, array('assume_numbers' => true));
    $out = _vae_oneline_get($value, $getsize, $params);
  }
  if ($attribute_type == "href") $out = urlencode($out);
  if ($attribute_type == "path_with_string_literals") $out = htmlspecialchars($out, ENT_QUOTES);
  return $out;
}

function _vae_oneline_get($value, $getsize, $params) {
  global $_VAE;
  $type = $value->type;
  if ($type == "ImageItem" || ($type == "VideoItem" && strlen($params[2]))) {
    if (strlen($params[1]) && !is_numeric($params[1])) {
      $src = vae_sizedimage($value, $params[1], $params[2]);
    } else {
      $src = vae_image($value, $params[1], $params[2], $params[3], $params[4], $params[5]);
    }
    if (strstr($params[count($params)-1], ".png")) $src = vae_watermark($src, $params[count($params)-1]);
    return _vae_oneline_size($src, $getsize);
  } elseif ($type == "VideoItem") {
    $src = vae_video($value, $params[1]);
    if ($src == "tryagain.flv") return $_VAE['config']['backlot_url'] . "/videos/" . $src;
    return _vae_oneline_size($src, $getsize);
  } elseif ($type == "FileItem") {
    $preserve_filename = ($_VAE['settings']['preserve_filenames'] ? true : false);
    $src = vae_file($value, $preserve_filename);
    return _vae_oneline_size($src, $getsize);
  } elseif ($type == "DateItem") {
    return strftime("%B %d, %Y", (string)$value);
  } elseif ($type == "OptionsItem") {
    return str_replace("=", ": ", str_replace(",", ", ", (string)$value));
  } elseif ($type == "Collection" || $type == "NestedCollection") {
    return $value->id();
  } else {
    return trim((string)$value);
  }
}

function _vae_oneline_size($src, $getsize = false) {
  global $_VAE;
  if ($getsize) return filesize($_VAE['config']['data_path'] . $src);
  return _vae_absolute_data_url() . $src;
}

function _vae_oneline_url($a, $context) {
  global $_VAE;
  if (strlen($a)) $context = _vae_fetch($a, $context);
  return (is_object($context) ? $context->permalink() : "");
}

function _vae_parse_path() {
  $uri = explode("?", $_SERVER['REQUEST_URI']);
  _vae_page_find(substr($uri[0], 1));
  $prev = "id";
  foreach (explode("-", $_REQUEST['path']) as $part) {
    if (is_numeric($part)) {
      $_REQUEST[$prev] = $part;
    }
    $prev = $part;
  }
}

function _vae_php($code, $context, $ref = null) {
  global $_VAE;
  $hash = md5($code);
  if (!isset($_VAE['phpfns'][$hash])) {
    if (substr($code, 0, 1) == "=") $code = "return " . substr($code, 1) . ";";
    preg_match_all("/\\$([a-zA-Z_\\x7f-\\xff][a-zA-Z0-9_\\x7f-\\xff]*)/", $code, $matches);
    if (is_array($matches) && is_array($matches[0])) {
      foreach ($matches[0] as $key) {
        if ($key != '$context' && $key != '$id') $glbls .= (strlen($glbls) ? ", " : "") . $key;
      }
      if (strlen($glbls)) $code = "global " . $glbls . "; " . $code;
    }
    $_VAE['phpfns'][$hash] = create_function('$context,$id', $code);
    if (!$_VAE['phpfns'][$hash]) return _vae_error("Invalid PHP Code" . ($ref ? " " . $ref : ""));
  }
  return $_VAE['phpfns'][$hash]($context, ($context ? $context->id() : null));
}

function _vae_placeholder($which) {
  global $_VAE;
  if ($_VAE['from_proxy']) return "%" . strtoupper($which) . "%";
  $which = strtolower($which);
  if ($which == "id") return "1234";
  if ($which == "shipment_company") return "UPS";
  if ($which == "shipment_tracking_number") return "1Z8A5E940342201962";
  return "(" . $which . ")";
}

function _vae_post_process($out) {
  global $_VAE;
  if (isset($_VAE['config']['cdn_url'])) {
    $out = preg_replace('/verbsite\.com\.lg1([a-z0-9]*)\.simplecdn\.net/', "vaesite.net", $out);
    $out = str_replace("verbcms.com", "vaeplatform.com", $out);
  }
  return $out;
}

function _vae_proto() {
  return (_vae_ssl() ? "https" : "http") . "://";
}

function _vae_qs($out = "", $keep_current = true, $append_to_end = "") {
  global $_VAE;
  if (!is_array($out)) {
    $nvps = explode("&", $out);
    $out = array();
    foreach ($nvps as $nvp) {
      $a = explode("=", $nvp);
      $out[$a[0]] = $a[1];
    }
  }
  $new = ($keep_current ? array_merge($_GET, $out) : $out);
  if (!$keep_current && $_REQUEST['locale']) $new['locale'] = $_REQUEST['locale'];
  $out = $path = "";
  if (count($new)) {
    foreach ($new as $k => $v) {
      if ($k != "__vae_local" && $k != "__vae_ssl_router" && $k != "__verb_local" && $k != "__page" && strlen($v)) {
        if ((preg_match("/([a-z0-9]*_)?page/", $k) && preg_match("/^([0-9]*|all)$/", $v) && !isset($_VAE['settings']['query_string_pagination'])) || ($k == "locale")) {
          if (($v != 1) && ($v != "en")) $path .= "/" . urlencode($k) . "/" . urlencode($v);
        } else {
          $out .= "&" . urlencode($k) . "=" . urlencode($v);
        }
      }
    }
  }
  if ($append_to_end) $out .= "&" . $append_to_end;
  return $path . ($out ? "?" . substr($out, 1) : "");
}

function _vae_read_file($name, $path = "") {
  global $_VAE;
  if ($path == "") $path = $_VAE['config']['data_path'];
  return @file_get_contents($path . $name);
}

function _vae_register_hook($name, $a) {
  global $_VAE;
  $name = str_replace(":", "_", $name);
  if (!isset($_VAE['hook'][$name])) $_VAE['hook'][$name] = array();
  $_VAE['hook'][$name][] = $a;
  return true;
}

function _vae_register_tag($name, $a) {
  global $_VAE;
  if ($a['callback'] && !$a['html']) $a['html'] = 'form';
  if ($a['html']) {
    $form = array('input','select','textarea');
    if (in_array($a['html'], $form)) $_VAE['form_items'][$name] = 1;
  }
  $_VAE['tags'][$name] = $a;
  if ($a['callback']) {
    $_VAE['callbacks'][$name] =  array('callback' => $a['callback']);
    if (strlen($a['filename'])) $_VAE['callbacks'][$name]['filename'] = $a['filename'];
  }
  return true;
}

function _vae_remote() {
  global $_VAE;
  if ($_REQUEST['secret_key'] == $_VAE['config']['secret_key']) {
    _vae_load_settings();
    if ($_REQUEST['version']) {
      echo "201 Version " . $_VAE['version'];
    } elseif ($_REQUEST['kvstore_empty']) {
      _vae_long_term_cache_empty();
      echo "200 Success";
    } elseif ($_REQUEST['hook']) {
      if ($_REQUEST['hook'] == "settings:updated") {
        _vae_update_settings_feed();
      }
      if ($_REQUEST['hook']) {
        if (strstr($_REQUEST['hook_param'], ",")) {
          foreach (explode(",", $_REQUEST['hook_param']) as $id) {
            _vae_run_hooks($_REQUEST['hook'], $id);
          }
        } else {
          _vae_run_hooks($_REQUEST['hook'], $_REQUEST['hook_param']);
        }
      }
      echo "200 Success";
    } elseif ($_REQUEST['newsletter_yield']) {
      echo _vae_render_newsletter_yield($_REQUEST['newsletter_yield'], null, array());
    } elseif ($_REQUEST['action'] == 'get_feed_generation') {
      _vae_ensure_open_session();
      echo $_VAE['feed_generation'];
    } else {
      _vae_error("","No action specified");
    }
  } else {
    _vae_error("","Secret Key Mismatch");
  }
  _vae_die();
}

function _vae_remote_addr() {
  if (substr($_SERVER['REMOTE_ADDR'], 0, 3) == "10." && strlen($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $e = explode(",", $_SERVER['HTTP_X_FORWARDED_FOR']);
    return array_pop($e);
  }
  return $_SERVER['REMOTE_ADDR'];
}

function _vae_remove_file($name) {
  global $_VAE;
  if (strlen($name)) @unlink($_VAE['config']['data_path'] . $name);
}

function _vae_render_backtrace($backtrace, $format = 'html') {
  $calls = array();
  foreach ($backtrace as $bt) {
    $bt['file']  = (isset($bt['file'])) ? $bt['file'] : 'Unknown';
    $bt['line']  = (isset($bt['line'])) ? $bt['line'] : 0;
    $bt['class'] = (isset($bt['class'])) ? $bt['class'] : '';
    $bt['type']  = (isset($bt['type'])) ? $bt['type'] : '';
    $bt['args']  = (isset($bt['args'])) ? $bt['args'] : '';
    $args = '';
    if ($bt['args']) {
      foreach ($bt['args'] as $arg) {
        if (strlen($args)) {
          $args .= ', ';
        }
        switch (gettype($arg)) {
          case 'integer':
          case 'double':
            $args .= $arg;
            break;
          case 'string':
            $arg = str_replace("\n", "", $arg);
            $args .= '"' . $arg . '"';
            break;
          case 'array':
            $args .= 'array(size ' . count($arg) . ')';
            break;
          case 'object':
            $args .= 'object(' . get_class($arg) . ')';
            break;
          case 'resource':
            $args .= 'resource(' . strstr($arg, '#') . ')';
            break;
          case 'boolean':
            $args .= $arg ? 'true' : 'false';
            break;
          case 'NULL':
            $args .= 'null';
            break;
          default:
            $args .= 'unknown';
        }
      }
    }
    $calls[] = array(
      'file'  => _vae_hide_dir($bt['file']),
      'line'  => $bt['line'],
      'class' => $bt['class'],
      'type'  => $bt['type'],
      'func'  => $bt['function'],
      'args'  => $args
    );
  }
  if ($format == 'vae_report') {
    $out = array();
    foreach ($calls as $call) {
      $out[] = array('type' => $call['type'], 'args' => $call['args'], 'number' => $call['line'], 'file' => $call['file'], 'method' => $call['func'], 'class' => $call['class']);
    }
    return $out;
  }
  $htmlmessage = "<pre><ul>";
  foreach ($calls as $call) {
    $textmessage .= "    * {$call['class']}{$call['type']}{$call['func']}({$call['args']}) at {$call['file']}:{$call['line']}\n";
    $htmlmessage .= '<li><span class="bt1">' . htmlspecialchars($call['class'], ENT_COMPAT, 'UTF-8') . '</span><span class="bt2">'
        . htmlspecialchars($call['type'], ENT_COMPAT, 'UTF-8') . '</span><span class="bt3">' . htmlspecialchars($call['func'], ENT_COMPAT, 'UTF-8')
        . '</span><span class="bt4">(</span><span class="bt5">' . htmlspecialchars($call['args'], ENT_COMPAT, 'UTF-8') . '</span><span class="bt4">)</span> at ' . htmlspecialchars($call['file'], ENT_COMPAT, 'UTF-8') . ':' . $call['line'] . '</li>';
  }
  $htmlmessage .= "</pre>\n";
  if ($format == 'text') return $textmessage;
  return $htmlmessage;
}

function _vae_render_error($e) {
  global $_VAE;
  @header("HTTP/1.1 500 Internal Server Error");
  @header("Status: 500 Internal Server Error");
  $e_class = get_class($e);
  $show_debug = $_REQUEST['__debug'] || $_VAE['local_full_stack'] || $_REQUEST['__vae_local'];
  if (!$show_debug && file_exists($_SERVER['DOCUMENT_ROOT'] . "/error_pages/vae_error.html")) {
    return @file_get_contents($_SERVER['DOCUMENT_ROOT'] . "/error_pages/vae_error.html");
  }
  if (strstr($e->getFile(), "/www/vae_thrift") || strstr($e->getFile(), "/usr/local") || strstr($e_class, "Vae") || strstr($e_class, "Thrift")) {
    $error_type = "Vae Error";
    if ($e_class == "VaeException" || $e_class == "VaeSyntaxError" || $show_debug) $msg = $e->getMessage();
    $safe_class = false;
  } else {
    $error_type = "Exception Thrown";
    $msg = $e_class . ($e->getFile() ? " thrown in <span class='c'>" . _vae_hide_dir($e->getFile()) . "</span>" : "") . ($e->getLine() ? " at line <span class='c'>" . $e->getLine() . "</span>" : "") . ": " . $e->getMessage();
    $safe_class = true;
  }
  $out = "<h2>" . $error_type . (($e->filename && !strstr($e->filename, "/vae")) ? " in " . $e->filename : "") . "</h2>";
  if (!strlen($msg)) $msg = "An error has occured on our servers.  Please try again in a few minutes.";
  $out .= "<div class='b'>" . $msg . "</div>";
  if ($show_debug) {
    if (strlen($e->debugging_info)) $out .= "<h3>Debugging Info:</h3><div class='b'>" . $e->debugging_info . "</div>";
    if (strlen($_VAE['debug'])) $out .= "<h3>Debugging Traces:</h3><div class='b'><pre>" . htmlentities($_VAE['debug']) . "</pre></div>";
  }
  foreach (array("_SERVER" => $_SERVER, "_REQUEST" => $_REQUEST) as $name => $r) {
    $log_details .= "  $" . $name . ":\n";
    if ($show_debug) $out .= "<h3>$" . $name . ":</h3><div class='b'><pre>";
    foreach ($r as $k => $v) {
      if ($show_debug) $out .= $k . " => " . $v . "\n";
      $log_details .= "    " . $k . " => " . $v . "\n";
    }
    if ($show_debug) $out .= "</pre></div>";
  }
  if ($e->backtrace) {
    $backtrace = $e->backtrace;
  } else {
    $backtrace = $e->getTrace();
  }
  $log_msg = "[" . $_VAE['settings']['subdomain'] . "] " . $e_class . "\n" . ($e->debugging_info ? "  " . $e->debugging_info . "\n" : "") . ($e->getMessage() ? "  " . $e->getMessage() . "\n" : "") . $log_details;
  if ($backtrace && (count($backtrace) > 1)) {
    if ($show_debug || $safe_class) $out .= "<h3>Call stack (most recent first):</h3><div class='b'>" . _vae_render_backtrace($backtrace) . "</div>";
    $log_msg .= "  Call Stack:\n" . _vae_render_backtrace($backtrace, 'text');
  }
  _vae_logstash_send(str_replace("\n", "; ", $log_msg));
  $vae_error_log_msg = ($e->debugging_info ? "  " . $e->debugging_info . "\n" : "") . ($e->getMessage() ? "  " . $e->getMessage() . "\n" : "");
  $vae_error_log_ignore_msg = array("Call to undefined function ereg");
  $vae_error_log_ignore_classes = array('VaeQLQueryParseException','ParseError');
  if (!in_array($e_class, $vae_error_log_ignore_classes)) {
    foreach ($vae_error_log_ignore_msg as $msg) {
      if (strstr($vae_error_log_msg, $msg)) $vae_error_log_msg = "";
    }
    if (strlen($vae_error_log_msg)) _vae_log_error(trim($vae_error_log_msg), $e_class, $backtrace);
  }
  if ($_REQUEST['secret_key']) {
    return json_encode(array('error' => $msg, 'debug' => $_VAE['debug']));
  }
  return _vae_render_message($error_type, $out);
}

function _vae_render_final($txt) {
  global $_VAE;
  if (!_vae_in_ob()) die($txt);
  $_VAE['final'] = $txt;
  return "";
}

function _vae_render_message($title, $msg) {
  global $_VAE;
  $out .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html>
      <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <title>' . $title . '</title>';
  if (!_vae_is_xhr()) {
    $out .= '
        <link rel="stylesheet" type="text/css" media="all" href="//verb.vaesite.com/stylesheets/reset-min.css" />
        <link rel="stylesheet" type="text/css" media="all" href="//verb.vaesite.com/stylesheets/global.css" />
        <style type="text/css">
         h2 { font-size: 1.5em; font-weight: bold; margin-bottom: 10px; }
         h3 { margin-top: 40px; }
         .b { background: #222; color: #fff; padding: 10px; overflow: auto; font-weight: normal; }
        </style>
    ';
  }
  $out .= '</head>
      <body class="inner">
        <div id="header">
          <a class="vae-logo-top" href="http://vaeplatform.com/">
            <img alt="Vae&trade;" src="//verb.vaesite.com/images-o/logo-top.png" title="Vae&trade;" />
          </a>
          <div id="nav-top">
          </div>
          <div id="inner-heading" style="margin-top: 77px">
            <h1>' . $title . '</h1>
          </div>
        </div>
        <div id="content" class="content-text" style="padding: 50px 0 30px">
          <p>' . $msg . '</p>
        </div>
        <div id="footer-wrap">
        </div>
      </body>
    </html>';
  return $out;
}

function _vae_render_timer() {
  global $_VAE;
  foreach ($_VAE['ticks'] as $r) {
    $sum += $r[1];
  }
  foreach ($_VAE['ticks'] as $r) {
    $ticks .= "<tr><td>" . $r[0] . "</td><td align='right'>" . number_format($r[1]*100/$sum, 3) . "%</td><td align='right'>" . number_format($r[1], 3) . "ms</td></tr>\n";
  }
  $ticks .= "<tr><td>Total</td><td></td><td align='right'>" . number_format($sum, 3) . "ms</td></tr>\n";
  return _vae_render_message("Vae Timer on ".gethostname(), "<h2>Vae Timer</h2><div class='b'><table style='width: 100%;'>" . $ticks . "</table></div>");
}

function _vae_request_param($name, $flash = false) {
  $name = str_replace(" ", "_", $name);
  if ($flash) return $_SESSION['__v:flash']['post'][$name];
  return $_REQUEST[$name];
}

function _vae_require_ssl() {
  global $_VAE;
  $_VAE['ssl_required'] = true;
  $_VAE['cant_cache'] = "ssl_required";
  if (!_vae_ssl() && !$_VAE['local_full_stack'] && !$_REQUEST['__vae_local'] && !$_REQUEST['__verb_local']) {
    if (!strstr($_SERVER['HTTP_HOST'], ".vaesite.com") && !$_SESSION['__v:pre_ssl_host']) $_SESSION['__v:pre_ssl_host'] = $_SERVER['HTTP_HOST'];
    if (strlen($_VAE['settings']['ssl_host']) && strstr($_SERVER['DOCUMENT_ROOT'], ".verb/releases/")) {
      $domain = $_VAE['settings']['ssl_host'];
    } elseif ($_VAE['settings']['domain_ssl'] && strstr($_SERVER['DOCUMENT_ROOT'], ".verb/releases/")) {
      $domain = $_VAE['settings']['subdomain'] . "." . $_VAE['settings']['domain_ssl'];
    } elseif ($_VAE['settings']['domain_ssl']) {
      $domain = $_VAE['settings']['subdomain'] . "-staging." . $_VAE['settings']['domain_ssl'];
    } elseif (strstr($_SERVER['DOCUMENT_ROOT'], ".verb/releases/")) {
      $domain = $_VAE['settings']['subdomain'] . "-secure.vaesite.com";
    } else {
      $domain = $_VAE['settings']['subdomain'] . ".vaesite.com";
    }
    return vae_redirect("https://" . $domain . $_SERVER['REQUEST_URI']);
  }
  return false;
}

function _vae_round_significant_digits($value, $sigFigs) {
  if ($sigFigs < 1) $sigFigs = 1;
  $exponent = floor(log10($value) + 1);
  $significand = $value / pow(10, $exponent);
  $significand = round($significand * pow(10, $sigFigs)) / pow(10, $sigFigs);
  $value = $significand * pow(10, $exponent);
  return (string)$value;
}

function _vae_run_hooks($name, $params = null) {
  global $_VAE;
  $name = str_replace(":", "_", $name);
  if (isset($_VAE['hook'][$name])) {
    foreach ($_VAE['hook'][$name] as $a) {
      try {
        $retval = call_user_func($a['callback'], $params);
        if ($retval == false) return $retval;
      } catch (Exception $e) {
        if (strstr($e->getMessage(), "TSocket")) {
        } else {
          _vae_log_error("A hook registered with Vae for event '$name' failed.\n\nIt raised the following error: " . $e->getMessage(), get_class($e), debug_backtrace());
        }
      }
    }
  }
  return true;
}

function _vae_script_tag($a) {
  return "<script type='text/javascript'>" . $a . "</script>";
}

function _vae_session_deps_add($key, $from = "unknown") {
  global $_VAE;
  if (!isset($_VAE['dependencies'])) $_VAE['dependencies'] = array();
  $_VAE['dependencies'][$key] = "s";
  if (isset($_SESSION[$key])) $_VAE['cant_cache'] = $key . " - " . $from;
}

function _vae_set_cache_key() {
  global $_VAE;
  $key = "p" . $_VAE['global_cache_key'];
  $key .= filemtime($_SERVER['DOCUMENT_ROOT'] . $_VAE['filename']) . "-";
  $vae_php = $_SERVER['DOCUMENT_ROOT'] . '/__vae.php';
  if (file_exists($vae_php)) $key .= filemtime($vae_php);
  $verb_php = $_SERVER['DOCUMENT_ROOT'] . '/__verb.php';
  if (file_exists($verb_php)) $key .= filemtime($verb_php);
  $key = md5($key._vae_proto().$_VAE['filename'].$_SERVER['HTTP_HOST'].$_SERVER['QUERY_STRING'].$_SERVER['HTTP_X_PJAX'].(isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : "").serialize($_POST));
  $_VAE['cache_key'] = $key;
}

function _vae_set_cdn_url() {
  global $_VAE;
  if ($_VAE['local_full_stack']) return;
  if (!$_VAE['cloudflare_active'] && !$_VAE['config']['force_local_assets'] && !_vae_ssl()) {
    if (strlen($_VAE['settings']['cdn_host'])) {
      $_VAE['config']['cdn_url'] = "http://" . $_VAE['settings']['cdn_host'] . "/";
    } else {
      $domain = ($_VAE['settings']['domain_cdn'] ? $_VAE['settings']['domain_cdn'] : "vaesite.net");
      $_VAE['config']['cdn_url'] = "http://" . $_VAE['settings']['subdomain'] . "." . $domain . "/";
    }
    $_VAE['config']['data_url'] = $_VAE['config']['cdn_url'] . "__data/";
  }
  if (!$_VAE['cloudflare_active'] && _vae_ssl() && $_SERVER['HTTP_HOST'] == $_VAE['settings']['ssl_host']) {
    $_VAE['config']['cdn_url'] = "https://" . $_VAE['settings']['subdomain'] . ".vaesite.com/";
    $_VAE['config']['data_url'] = $_VAE['config']['cdn_url'] . "__data/";
  }
}

function _vae_session_cookie($name, $val) {
  global $_VAE;
  if (!isset($_VAE['session_cookies'])) $_VAE['session_cookies'] = array();
  $_VAE['session_cookies'][$name] = $val;
}

function _vae_set_default_config() {
  global $_VAE, $BACKLOTCONFIG;
  if (file_exists(dirname(__FILE__) . "/config.php")) include_once(dirname(__FILE__) . "/config.php");
  if (isset($BACKLOTCONFIG)) $_VAE['config'] = $BACKLOTCONFIG;
  if (!isset($_VAE['config']['data_path'])) $_VAE['config']['data_path'] = dirname(__FILE__) . "/data/";
  if (!isset($_VAE['config']['data_url'])) $_VAE['config']['data_url'] = substr($_VAE['config']['data_path'], 1 + strlen(dirname($_SERVER['SCRIPT_FILENAME'])));
  if (!isset($_VAE['config']['asset_url'])) $_VAE['config']['asset_url'] = $_VAE['config']['data_url'] . "../";
}

function _vae_set_initial_context() {
  global $_VAE;
  if (!isset($_VAE['context'])) {
    if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) && !strstr($_REQUEST['id'], ".")) {
      $id = $_REQUEST['id'];
    } else {
      foreach ($_GET as $k => $v) {
        if (is_numeric($v) && substr($k, 0, 1) != "_" && !strstr($v, ".")) $id = $v;
      }
    }
    $_VAE['context'] =  (isset($id) ? _vae_fetch($id) : null);
  } elseif (!is_object($_VAE['context']) && is_numeric($_VAE['context'])) {
    $_VAE['context'] =  _vae_fetch($_VAE['context']);
  }
}

function _vae_set_login() {
  global $_VAE;
  $res = _vae_simple_rest("/api/site/v1/authenticate?secret_key=" . $_VAE['config']['secret_key'] . "&remote_access_key=" . $_REQUEST['remote_access_key']);
  if (preg_match('/601 Authorized\. user_id=([0-9]*)/', $res, $output)) {
    if ($_REQUEST['session_id']) {
      session_id($_REQUEST['session_id']);
      session_start();
    } else {
      foreach ($_SESSION as $k => $v) {
        unset($_SESSION[$k]);
      }
      $_SESSION['__v:user_id'] = $output[1];
      if ($_REQUEST['customer_id']) {
        if ($raw = _vae_rest(array(), "api/site/v1/customers/show/" . $_REQUEST['customer_id'], "customer", $tag, null, true)) {
          _vae_store_load_customer($raw);
        }
      }
    }
    if (strlen($_REQUEST['redirect'])) {
      @header("Location: " . $_REQUEST['redirect']);
    } else {
      @header("Location: /");
    }
  } else {
    _vae_error("","Bad key.");
  }
  _vae_die();
}

function _vae_should_load() {
  if (file_exists($_SERVER['DOCUMENT_ROOT'] . "/__novae.php")) return false;
  if (file_exists($_SERVER['DOCUMENT_ROOT'] . "/__noverb.php")) return false;
  if (preg_match('/^\/piwik/', $_SERVER['REQUEST_URI'])) return false;
  return true;
}

function _vae_src($filename) {
  global $_VAE;
  if (substr($filename, 0, 1) != "/") $filename = "/" . $filename;
  if ($filename == "/") $filename = "/index";
  foreach (array("", ".html", ".haml", ".php", ".sass", ".scss", ".xml", ".rss", ".pdf.html", ".pdf.haml", ".pdf.haml.php", ".haml.php") as $ext) {
    if ($_VAE['local']) {
      $vaeml = _vae_long_term_cache_get($_VAE['local'] . $filename . $ext);
      if (strlen($vaeml)) {
        $filename = $filename . $ext;
        break;
      }
    } else {
      if (file_exists($_SERVER['DOCUMENT_ROOT'] . "/" . $filename . $ext)) {
        $vaeml = file_get_contents($_SERVER['DOCUMENT_ROOT'] . "/" . $filename . $ext);
        $filename = $filename . $ext;
        break;
      }
    }
  }
  if ($_VAE['local'] && !strlen($vaeml)) {
    return _vae_local_needs($filename);
  }
  _vae_dependency_add($filename, md5($vaeml));
  return array($filename, $vaeml);
}

function _vae_ssl() {
  if ($_REQUEST['__vae_ssl_router'] || $_SERVER['HTTPS'] || ($_SERVER['HTTP_X_FORWARDED_PROTO'] == "https")) {
  	return true;
  } else {
  	return false;
  }
}

function _vae_start_ob() {
  global $_VAE;
  $avoid = array("load-styles.php", "load-scripts.php", "wp-tinymce.php");
  foreach ($avoid as $a) {
    if (strstr($_VAE['filename'], $a)) return;
  }
  if (strstr($_VAE['filename'], "/p.php") && file_exists($_SERVER['DOCUMENT_ROOT'] . "/" . dirname($_VAE['filename']) . "/config/conf.php")) {
    return;
  }
  ob_start('_vae_handleob');
}

function _vae_statsd_send($value) {
  global $_VAE;
  if (!$_ENV['TEST'] && $fp = @fsockopen("udp://statsd.av", 8125, $errno, $errstr)) {
    fwrite($fp, "vaesite." . $_VAE['settings']['subdomain'] . "." . $value);
    fclose($fp);
  }
}

function _vae_statsd_timing($key, $time) {
  _vae_statsd_send("$key:$time|ms|@1");
}

function _vae_store_file($iden, $file, $ext, $filename = null, $gd_or_uploaded = false) {
  global $_VAE;
  $newname = _vae_make_filename($ext, $filename);
  if ($gd_or_uploaded == "uploaded") {
    move_uploaded_file($file, $_VAE['config']['data_path'] . $newname);
    if ($_ENV['TEST']) $_VAE['files_written'][] = $newname;
  } elseif ($gd_or_uploaded) {
    if ($gd == "jpeg") imagejpeg($file, $_VAE['config']['data_path'] . $newname, 100);
    else imagepng($file, $_VAE['config']['data_path'] . $newname, 9);
    if ($_ENV['TEST']) $_VAE['files_written'][] = $newname;
  } else {
    _vae_write_file($newname, $file);
  }
  if ($iden) {
    _vae_long_term_cache_set($iden, $newname, null, 1);
  }
  return $newname;
}

function _vae_store_marketing_data_in_session() {
  foreach (array('campaign','content','keyword','medium','source') as $q) {
    if ($_REQUEST['utm_'.$q]) {
      $_SESSION['__v:store']['marketing_data']['marketing_' . $q] = $_REQUEST['utm_'.$q];
    }
  }
}

function _vae_stringify_array($array) {
  foreach ($array as $k => $v) {
    if (is_object($v)) $array[$k] = (string)$v;
  }
  return $array;
}


function _vae_tag_unique_id(&$tag, $context) {
  if (!isset($tag['unique_id'])) {
    $tag['unique_id'] = md5(serialize($tag['attrs']) . $tag['type'] . serialize($tag['callback']) . count($tag['tags']) . (($context && $context->id) ? $context->id : 0));
  }
  return $tag['unique_id'];
}

function _vae_tick($desc, $userland = false) {
  global $_VAE;
  if (!isset($_REQUEST['__time'])) return;
  if ($_REQUEST['__time'] != "vae" && !$userland) return;
  $now = microtime(true);
  $_VAE['ticks'][] = array($desc, ($now - $_VAE['tick'])*1000, $userland);
  $_VAE['tick'] = $now;
}

function _vae_update_settings_feed() {
  global $_VAE;
  $retry = 0;
  do {
    $retry++;
    $feed_data = _vae_simple_rest("/api/site/v1/settings?secret_key=" . $_VAE['config']['secret_key']);
    $feed_data = html_entity_decode($feed_data);
  } while (!strstr($feed_data, "?>") && $retry < 3);
  if (strstr($feed_data, "?>")) {
    _vae_write_file("settings.php", $feed_data);
  }
}

function _vae_urlize($r) {
  $r = preg_replace('/(^| )[a-zA-Z]+:\/\/([-]*[.]?[a-zA-Z0-9_\/\?&%=+])*/', '<a href="$0" target="_blank">$0</a>', str_replace("\n", "\n ", $r));
  $r = preg_replace('/(^| |>)www[.](([a-zA-Z0-9.])*[.](com|net|org|au|jp|us|uk|no)([a-zA-Z0-9_\/\?&%=+])*)/', "\\1<a href=\"http://\\2\" target=\"_blank\">\\2</a>", $r);
  $r = preg_replace('/(^| |>)(([a-zA-Z0-9_.])*@([a-zA-Z0-9_\/\?&.%=+])*[.](com|net|org|au|jp|us|uk|no))/', "\\1<a href=\"mailto:\\2\">\\2</a>", $r);
  return $r;
}

function _vae_valid_creditcard($creditcard) {
  if (!is_numeric($creditcard)) return false;
  $cardlength = strlen($creditcard);
  $parity = $cardlength % 2;
  $sum = 0;
  for ($i = 0; $i < $cardlength; $i++) {
    $digit = $creditcard[$i];
    if ($i % 2 == $parity) $digit = $digit * 2;
    if ($digit > 9) $digit = $digit - 9;
    $sum = $sum + $digit;
  }
  return ($sum % 10 == 0);
}

function _vae_valid_date($date) {
  return (strtotime($date) > 0);
}

function _vae_valid_digits($input) {
  return (strlen($input) && !preg_match('/[^0-9]/', $input));
}

function _vae_valid_nodigits($input) {
  return !preg_match('/[0-9]/', $input);
}

function _vae_valid_email($email) {
  return preg_match('/^[-+_.[:alnum:]]+@(?:(?:(?:[[:alnum:]]|[[:alnum:]][[:alnum:]-]*[[:alnum:]])\.)+(?:[a-z]+)$|(([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5])\.){3}([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5]))$/i', $email);
}

function _vae_valid_url($url) {
  return preg_match('/^(https?|ftp|telnet):\/\/((?:[a-z0-9@:.-]|%[0-9A-F]{2}){3,})(?::(\d+))?((?:\/(?:[a-z0-9-._~!$&\'()*+,;=:@]|%[0-9A-F]{2})*)*)(?:\?((?:[a-z0-9-._~!$&\'()*+,;=:\/?@]|%[0-9A-F]{2})*))?(?:#((?:[a-z0-9-._~!$&\'()*+,;=:\/?@]|%[0-9A-F]{2})*))?$/i', $url);
}

function _vae_write_file($name, $data) {
  global $_VAE;
  $f = fopen($_VAE['config']['data_path'] . $name, "wb");
  if (!$f) {
    _vae_error("","Couldn't fopen() local cache file " . _vae_h($name));
  }
  $ret = fwrite($f, $data);
  fclose($f);
  if ($ret != strlen($data)) {
    _vae_error("","Couldn't write enough data to local cache file " . _vae_h($name));
  }
  if ($_ENV['TEST']) {
    $_VAE['files_written'][] = $name;
  }
}
