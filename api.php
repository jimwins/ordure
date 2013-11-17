<?php
$f3= require('externals/fatfree/lib/base.php');
$f3->config('config.ini');

$f3->set('DBH', new DB\SQL($f3->get('db.dsn'),
                           $f3->get('db.user'),
                           $f3->get('db.password')));

$f3->route('GET /api/page-load', function($f3) {
  $db= $f3->get('DBH');
  $page= new DB\SQL\Mapper($db, 'page');
  if (!$page->load(array('slug=?', $_REQUEST['slug']))) {
    $page->slug= $_REQUEST['slug'];
    $page->format= 'markdown';
  }
  $ret= $page->cast();
  $ret['rendered']= Markdown::instance()->convert($ret['content']);
  echo jsonp($f3, $ret);
});

$f3->route('GET /api/page-save [json]', function($f3) {
  $db= $f3->get('DBH');
  $page= new DB\SQL\Mapper($db, 'page');
  $page->load(array('slug=?', $_REQUEST['slug']));
  foreach ($_REQUEST as $k => $v) {
    if ($page->exists($k)) $page->set($k, $v);
  }
  $page->save();
  $ret= $page->cast();
  $ret['rendered']= Markdown::instance()->convert($ret['content']);
  echo jsonp($f3, $ret);
});

$f3->run();

function jsonp($f3, $data) {
  if (preg_match('/\W/', @$_GET['callback'])) {
    // if $_GET['callback'] contains a non-word character,
    // this could be an XSS attack.
    $f3->status(400);
    exit();
  }
  header('Content-type: application/json; charset=utf-8');
  if (@$_GET['callback']) {
    return sprintf('%s(%s);', $_GET['callback'], json_encode($data));
  }
  return json_encode($data);
}
