<?php
$version = '0.2.1';

session_start();
require_once 'routes.php';

//	Return the path of the main directory
$path = str_replace('\\', '/', dirname(__FILE__));
if (substr($path, -1, 1) != '/') { $path .= '/'; }
//	Get the URL path (from http://site.com/ onwards)
$urlpath = str_ireplace(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '', $path);
if (substr($urlpath, 0, 1) != '/') { $urlpath = '/' . $urlpath; }
//	Theme path
$themepath = $urlpath . 'themes/' . (isset($theme) ? $theme : 'default') . '/';

require($path . '/config/database.php');
require_once $path . 'lib/ActiveRecord/ActiveRecord.php';

try {
	$db = new PDO("mysql:host=$host;dbname=$name", $user, $pass, array(PDO::ATTR_PERSISTENT => true));
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
	
	$connections = array(
		'development' => "mysql://$user:$pass@$host/$name"
	);
	// initialize ActiveRecord
	ActiveRecord\Config::initialize(function($cfg) use ($connections)
	{
	  global $path;
    $cfg->set_model_directory($path . 'app/models');
    $cfg->set_connections($connections);
	});
} catch(PDOException $e) {
	die($e->getMessage());
}

function throw403() {
  global $path, $urlpath;
  ob_start();
  render('layouts/403');
  $content = ob_get_contents();
  ob_end_clean();
  render('layouts/application');
  exit;
}

function throw404() {
  global $path, $urlpath;
  ob_start();
  render('layouts/404');
  $content = ob_get_contents();
  ob_end_clean();
  render('layouts/application');
  exit;
}

$application_layout = 'application';

function layout($layout) {
  global $application_layout;
  $application_layout = $layout;
}

function render($options = null) {
  global $route, $path, $urlpath, $content;
  if (isset($options) === false || isset($options['view']) === false) {
    $view = implode('/', $route);
  } else if (is_string($options) === true) {
    $view = $options;
  }
  if (is_array($options) === true) {
    foreach ($options as $key => $value) { $$key = $value; }
  }
  include $path . 'app/views/' . str_replace('..', '', $view) . '.php';
}

ob_start();
//include 'loader.php';
$request = str_replace($urlpath, '', $_SERVER['REQUEST_URI']);
if (substr($request, -1) == '/') { $request = substr($request, 0, -1); }
if ($request == '') {
  $route = explode('#', $root);
  require_once $path . 'app/controllers/' . $route[0] . '.php';
  if (is_callable(($requestFunction = implode('_', $route))) === false) {
    throw404();
  }
  call_user_func($requestFunction, (isset($match) ? $match : null));
} else {
  foreach ($routes as $routeFrom => $routeTo) {
    if (preg_match('`^' . $routeFrom . '$`i', $request, $match) == 1) {
      $route = explode('#', $routeTo);
      require_once $path . 'app/controllers/' . $route[0] . '.php';
      if (is_callable(($requestFunction = implode('_', $route))) === false) {
        throw404();
      }
      call_user_func($requestFunction, (isset($match) ? $match : null));
      break;
    }
  }
}
$content = ob_get_contents();
ob_end_clean();
render('layouts/' . $application_layout);