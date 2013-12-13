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
    $ret['rendered']= Markdown::instance()->convert($ret['content']);
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
    $ret['rendered']= Markdown::instance()->convert($ret['content']);
    echo jsonp($f3, $ret);
  }

  function productLoad($f3) {
    $db= $f3->get('DBH');
    $obj= new DB\SQL\Mapper($db, 'product');
    if (!$obj->load(array('id=?', $f3->get('REQUEST.id')))) {
      $obj->slug= $_REQUEST['slug'];
    }
    $ret= $obj->cast();
    echo jsonp($f3, $ret);
  }

  function productSave($f3) {
    $db= $f3->get('DBH');
    $obj= new DB\SQL\Mapper($db, 'product');
    $obj->load(array('id=?', $f3->get('REQUEST.id')));
    foreach ($_REQUEST as $k => $v) {
      if ($obj->exists($k)) $obj->set($k, $v);
    }
    $obj->modified= date('Y-m-d H:i:s', time());
    $obj->save();
    $ret= $obj->cast();
    echo jsonp($f3, $ret);
  }

  function productToggle($f3) {
    $db= $f3->get('DBH');
    $page= new DB\SQL\Mapper($db, 'product');
    $page->load(array('id=?', $_REQUEST['product']));
    $page->inactive= ($page->inactive + 1) % 3;
    $page->save();
    $ret= $page->cast();
    echo jsonp($f3, $ret);
  }

  function itemToggle($f3) {
    $db= $f3->get('DBH');
    $page= new DB\SQL\Mapper($db, 'item');
    $page->load(array('id=?', $_REQUEST['item']));
    $page->inactive= ($page->inactive + 1) % 3;
    $page->save();
    $ret= $page->cast();
    echo jsonp($f3, $ret);
  }

  function deptFind($f3) {
    $db= $f3->get('DBH');
    $page= new DB\SQL\Mapper($db, 'department');

    $departments= $page->find(array('parent = 0'),
                              array('order' => 'name'));

    $cast= function($arg) {
      return $arg->cast();
    };

    $ret= array_map($cast, $departments);

    if ($f3->get('REQUEST.levels') == 2) {
      foreach ($ret as $i => $dept) {
        $departments= $page->find(array('parent = ?', $dept['id']),
                                  array('order' => 'name'));
        $ret[$i]['sub']= array_map($cast, $departments);
      }
    }

    echo jsonp($f3, $ret);
  }

  function deptLoad($f3) {
    $db= $f3->get('DBH');
    $page= new DB\SQL\Mapper($db, 'department');
    if (!$page->load(array('id=?', $_REQUEST['id']))) {
      $page->slug= $_REQUEST['id'];
    }
    $ret= $page->cast();
    echo jsonp($f3, $ret);
  }

  function deptSave($f3) {
    $db= $f3->get('DBH');
    $page= new DB\SQL\Mapper($db, 'department');
    if ($_REQUEST['id']) {
      $page->load(array('id=?', $_REQUEST['id']));
      // XXX error handling
    }
    foreach ($_REQUEST as $k => $v) {
      if ($page->exists($k)) $page->set($k, $v);
    }
    $page->save();
    $ret= $page->cast();
    echo jsonp($f3, $ret);
  }

  function brandFind($f3) {
    $db= $f3->get('DBH');
    $page= new DB\SQL\Mapper($db, 'brand');

    $brands= $page->find(array('1 = 1'),
                         array('order' => 'name'));

    $cast= function($arg) {
      return $arg->cast();
    };

    $ret= array_map($cast, $brands);

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
