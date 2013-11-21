<?php
$f3= require('../externals/fatfree/lib/base.php');
$f3->config('../config.ini');

$f3->set('DBH', new DB\SQL($f3->get('db.dsn'),
                           $f3->get('db.user'),
                           $f3->get('db.password')));

// Add @markdown() function for templates
$f3->set('markdown', function($text) {
  return Markdown::instance()->convert($text);
});

// if DEBUG, allow access to /info
if ($f3->get('DEBUG')) {
  $f3->route('GET /info', function ($f3) {
    phpinfo();
  });
}

// Index is a special page
$f3->route('GET /', function ($f3, $args) {
  $db= $f3->get('DBH');
  $page= new DB\SQL\Mapper($db, 'page');
  $page->load(array('slug=?', '@home'))
    or $f3->error(404);

  $f3->set('PAGE', $page);

  echo Template::instance()->render('home.html');
});

$f3->route('GET /@page', function ($f3, $args) {
  $db= $f3->get('DBH');
  $page= new DB\SQL\Mapper($db, 'page');
  $page->load(array('slug=?', $f3->get('PARAMS.page')))
    or $f3->error(404);

  $f3->set('PAGE', $page);

  echo Template::instance()->render('page.html');
});

/* Handle catalog URLs */
require '../lib/catalog.php';
Catalog::addRoutes($f3);

/* Handle API calls */
require '../lib/api.php';
$f3->route('GET /api/@action [json]', 'API->@action');

/* Handle externals */
$f3->route('GET /externals/*', function ($f3, $args) {
  if (preg_match('/\.(css|js|eot|svg|ttf|woff)$/', $args[1]) &&
      file_exists("../externals/" . $args[1])) {
    $type= 'text/css';
    if (preg_match('/\.js$/', $args[1])) {
      $type= 'application/javascript';
    }
    if (preg_match('/\.svg$/', $args[1])) {
      $type= 'application/xml+svg';
    }
    if (preg_match('/\.woff$/', $args[1])) {
      $type= 'application/font-woff';
    }
    if (preg_match('/\.ttf$/', $args[1])) {
      $type= 'application/x-font-ttf';
    }
    if (preg_match('/\.eot$/', $args[1])) {
      $type= 'application/vnd.ms-fontobject';
    }
    Web::instance()->send("../externals/" . $args[1], $type, 0, false);
  } else {
    $f3->error(404);
  }
});

$f3->run();
