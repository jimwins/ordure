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

$f3->set('ONERROR', function ($f3) {
  if ($f3->get('AJAX')) {
    echo json_encode($f3->get('ERROR'));
  } else {
    // XXX There is some sort of bug in calling a template within ONERROR
    // related to escaping. Running fast and loose for now.
    $f3->set('ESCAPE', false);
    echo Template::instance()->render('404.html');
  }
});

$f3->route('GET /*', function ($f3, $args) {
  $db= $f3->get('DBH');

  $page= new DB\SQL\Mapper($db, 'page');

  $page->load(array('slug=?', $args[1]))
    or $f3->error(404);

  $f3->set('PAGE', $page);

  $template= empty($args[1]) ? 'home.html' : 'page.html';
  echo Template::instance()->render($template);
});

$f3->route('POST /contact', function ($f3, $args) {

  @mail($f3->get('CONTACT'),
        $f3->get('REQUEST.subject'),
        Template::instance()->render('contact-email.txt', 'text/plain'),
        "From: " . $f3->get('CONTACT') . "\r\n");

  $db= $f3->get('DBH');

  $page= new DB\SQL\Mapper($db, 'page');

  $page->load(array('slug=?', 'contact-thanks'))
    or $f3->error(404);

  $f3->set('PAGE', $page);

  echo Template::instance()->render('page.html');
});

/* Handle catalog URLs */
require '../lib/catalog.php';
Catalog::addRoutes($f3);

/* Handle API calls */
if ($f3->get('ADMIN')) {
  require '../lib/api.php';
  $f3->route('GET|POST /api/@action [json]', 'API->@action');
}

$f3->run();
