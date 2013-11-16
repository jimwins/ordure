<?php

error_reporting(E_ALL & ~E_NOTICE);
bcscale(2);

if (get_magic_quotes_gpc())
  die("Sorry, you need to disable magic quotes for Ordure to work.");

/* Start page timer */
$start_time= microtime();

/* $DEBUG can be set by config.php, not in request/ */
$DEBUG= false;
require dirname(dirname(__FILE__)).'/config.php';
require dirname(__FILE__).'/db.php';
require dirname(__FILE__).'/layout.php';
require dirname(dirname(__FILE__)).'/externals/php-markdown-extra/markdown.php';

/** Basic functions */

function ashtml($t) {
  return htmlspecialchars($t);
}

function href() {
  $ret= BASE;
  foreach (func_get_args() as $arg) {
    if (!empty($arg)) {
      $ret.= $arg;
    }
  }
  return ashtml($ret);
}

function img($name, $width) {
  return '<img src="' . href('images/', $name) . ' " width="' . $width . '">';
}

function render_page_contents($db, $slug) {
  $q= "SELECT * FROM page WHERE slug = '" . $db->escape($slug) . "'";
  $page= $db->get_one_assoc($q) or die($db->error);
  if ($page['format'] == 'markdown')
    $page['rendered']= markdown($page['content']);
  else
    $page['rendered']= $page['content'];
  return $page;
}

/** Set up database connection */
if (!defined('DB_SERVER') ||
    !defined('DB_USER') ||
    !defined('DB_PASSWORD') ||
    !defined('DB_SCHEMA')) {
  head("Ordure Configuration");
  $msg= <<<CONFIG
<p>You must configure Ordure to connect to your database. Create
<code>config.php</code> and add the following code, configured as appropriate
for your setup:
<pre>
&lt;?
/* Database configuration */
define('DB_SERVER', 'localhost');
define('DB_USER', 'ordure');
define('DB_PASSWORD', 'ordure');
define('DB_SCHEMA', 'ordure');
</pre>
CONFIG;
  die($msg);
}

$db= new ScatDB();
if (!$db) die("mysqli_init failed");

if (!$db->real_connect(DB_SERVER,DB_USER,DB_PASSWORD,DB_SCHEMA))
  die('connect failed: ' . mysqli_connect_error());
$db->set_charset('utf8')
  or die("set charset failed");
