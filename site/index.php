<?php
$f3= require('../vendor/bcosca/fatfree/lib/base.php');
$f3->config('../config.ini');

$f3->set('DBH', new DB\SQL($f3->get('db.dsn'),
                           $f3->get('db.user'),
                           $f3->get('db.password'),
                           array(\PDO::MYSQL_ATTR_LOCAL_INFILE => true)));

// Add @markdown() function for templates
$f3->set('markdown', function($text) {
  $text= Template::instance()->resolve($text);
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
    $db= $f3->get('DBH');

    $redir= new DB\SQL\Mapper($db, 'redirect');

    $path= str_replace($f3->get('BASE'), '', $f3->get('URI'));
    if ($redir->load(array('source LIKE ?', $path . '%'))) {
      $f3->reroute($redir->dest); 
    }

    // XXX There is some sort of bug in calling a template within ONERROR
    // related to escaping. Running fast and loose for now.
    $f3->set('ESCAPE', false);
    echo Template::instance()->render('404.html');
  }
});

$f3->route('GET /*', function ($f3, $args) {
  $db= $f3->get('DBH');

  $page= new DB\SQL\Mapper($db, 'page');
  $item= new DB\SQL\Mapper($db, 'item');

  // f3 includes query string in $args[1], which is an odd choice.
  $path= preg_replace('/\?.+$/', '', $args[1]);

  if ($page->load(array('slug=?', $path))) {
    $f3->set('PAGE', $page);

    $template= empty($path) ? 'home.html' : 'page.html';
    echo Template::instance()->render($template);
  } elseif ($item->load(array('code=?', $path))) {
    $slug= Catalog::getProductSlug($f3, $item->product);
    if ($slug) {
      $f3->reroute('/' . $f3->get('CATALOG') . '/' . $slug); 
    } else {
      $f3->error(404);
    }
  } else {
    $f3->error(404);
  }

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

// Handle updated pricing
$f3->route('POST /update-pricing', function ($f3, $args) {

  $db= $f3->get('DBH');

  $fn= $_FILES['prices']['tmp_name'];
  if (!$fn) {
    $f3->error(500, 'No file specified');
  }

  $key= $f3->get('UPLOAD_KEY');

  if ($key != $_REQUEST['key']) {
    $f3->error(500, 'Wrong key.');
  }

  $q= "LOAD DATA LOCAL INFILE ?
         REPLACE
            INTO TABLE scat_item
          FIELDS TERMINATED BY '\t'
          IGNORE 1 LINES
          (retail_price, @discount_type, @discount, @stock, code)
             SET discount_type = IF(@discount_type = 'NULL', NULL,
                                    @discount_type),
                 discount = IF(@discount = 'NULL', NULL, @discount),
                 stock = IF(@stock = 'NULL', NULL, @stock)
      ";

  $rows= $db->exec($q, $fn);

  echo "Loaded $rows prices.";
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
