<?php

class API {

  function pageLoad($f3) {
    $db= $f3->get('DBH');
    $page= new DB\SQL\Mapper($db, 'page');
    if (!$page->load(array('slug=?', $_REQUEST['slug']))) {
      $page->slug= $_REQUEST['slug'];
      $page->format= 'markdown';
    }
    $ret= $page->cast();
    $text= Template::instance()->resolve($ret['content']);
    $ret['rendered']= Markdown::instance()->convert($text);
    echo jsonp($f3, $ret);
  }

  function pageSave($f3) {
    $db= $f3->get('DBH');
    $page= new DB\SQL\Mapper($db, 'page');
    $page->load(array('slug=?', $_REQUEST['slug']));
    foreach ($_REQUEST as $k => $v) {
      if ($page->exists($k)) $page->set($k, $v);
    }
    $page->save();
    $ret= $page->cast();
    $content= Template::instance()->resolve($ret['content']);
    $ret['rendered']= Markdown::instance()->convert($content);
    echo jsonp($f3, $ret);
  }

}

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
