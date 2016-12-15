<?php
require '../vendor/autoload.php';

$f3= \Base::instance();
$f3->config('../config.ini');

$f3->set('DBH', new DB\SQL($f3->get('db.dsn'),
                           $f3->get('db.user'),
                           $f3->get('db.password'),
                           array(
                             \PDO::MYSQL_ATTR_LOCAL_INFILE => true,
                             \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                           )));

// Add @markdown() function for templates
$f3->set('markdown', function($text) {
  $text= Template::instance()->resolve($text);
  return Markdown::instance()->convert($text);
});

$f3->set('item_style_color', function($text) {
  if (preg_match('/^color:(.+)/', $text, $m)) {
    $r= hexdec(substr($m[1], 0, 2));
    $g= hexdec(substr($m[1], 2, 2));
    $b= hexdec(substr($m[1], 4, 2));
    // Calculate whether to use black or white text for bgcolor
    return 'background: #' . $m[1] . '; color: #' .
      ((($r * 0.2126 + $g * 0.7152 + $b * 0.0722) > 179) ? '000' : 'fff');
  } else {
    return '';
  }
});

// if DEBUG, allow access to /info
if ($f3->get('DEBUG')) {
  $f3->route('GET|HEAD /info', function ($f3) {
    phpinfo();
  });
}

$f3->set('ONERROR', function ($f3) {
  if ($f3->get('AJAX')) {
    echo json_encode($f3->get('ERROR'));
  } else {
    $db= $f3->get('DBH');

    $redir= new DB\SQL\Mapper($db, 'redirect');

    $path= $f3->get('PATH');
    if ($redir->load(array('source LIKE ?', $path . '%'))) {
      $q= $f3->get('QUERY');
      $f3->reroute($redir->dest . ($q ? "?$q" : "")); 
    }

    // XXX There is some sort of bug in calling a template within ONERROR
    // related to escaping. Running fast and loose for now.
    $f3->set('ESCAPE', false);
    echo Template::instance()->render('404.html');
  }
});

class Page {

  function getPage($f3, $args) {
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

  }

}

$f3->route('GET|HEAD /*', 'Page->getPage');
$f3->route('GET|HEAD /', 'Page->getPage');

$f3->route('GET|HEAD /sales-tax-policy', function($f3, $args) {

  $db= $f3->get('DBH');

  $result= \Web::instance()->request($f3->get('TAXCLOUD_POLICY_URL'));

  $page = array(
    'title' => 'Sales Tax Policy @ Raw Materials Art Supplies',
    'content' => $result['body'],
  );

  $f3->set('PAGE', $page);

  echo Template::instance()->render("page.html");

});

$f3->route('POST /contact', function ($f3, $args) {

  $headers= array();
  $headers[]= "From: " . $f3->get('CONTACT');
  $headers[]= "Reply-To: " . $f3->get('REQUEST.email');

  $template= preg_replace('/[^a-z]/', '', $f3->get('REQUEST.template'));

  @mail($f3->get('CONTACT'),
        $f3->get('REQUEST.subject'),
        Template::instance()->render('email-' . $template . '.txt',
                                     'text/plain'),
        implode("\r\n", $headers));

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
          (retail_price, @discount_type, @discount, @stock,
           code, minimum_quantity, purchase_quantity)
             SET discount_type = IF(@discount_type = 'NULL', NULL,
                                    @discount_type),
                 discount = IF(@discount = 'NULL', NULL, @discount),
                 stock = IF(@stock = 'NULL', NULL, @stock)
      ";

  $rows= $db->exec($q, $fn);

  echo "Loaded $rows prices.";
});

$f3->route('GET /track/ups/@code', function ($f3, $args) {
  $f3->reroute('http://wwwapps.ups.com/WebTracking/processInputRequest?AgreeToTermsAndConditions=yes&track.x=38&track.y=9&InquiryNumber1=' . $f3->get('PARAMS.code'));
});

$f3->route('GET /track/usps/@code', function ($f3, $args) {
  $f3->reroute('https://tools.usps.com/go/TrackConfirmAction.action?tLabels=' . $f3->get('PARAMS.code'));
});

$f3->route('GET /track/ontrac/@code', function ($f3, $args) {
  $f3->reroute('http://www.ontrac.com/trackingres.asp?tracking_number=' . $f3->get('PARAMS.code'));
});

$f3->route('GET /track/fedex/@code', function ($f3, $args) {
  $f3->reroute('https://www.fedex.com/apps/fedextrack/?cntry_code=us&tracknumbers=' . $f3->get('PARAMS.code'));
});

/* Handle authentication */
require '../lib/auth.php';
Auth::addRoutes($f3);

/* Handle catalog URLs */
require '../lib/catalog.php';
Catalog::addRoutes($f3);

/* Handle buying gift card */
require '../lib/gift-card.php';
GiftCard::addRoutes($f3);

/* Handle sale URLs (not live yet) */
if ($f3->get('DEBUG')) {
require '../lib/sale.php';
Sale::addRoutes($f3);
}

/* Handle API calls */
if ($f3->get('ADMIN')) {
  require '../lib/api.php';
  $f3->route('GET|POST /api/@action [json]', 'API->@action');
}

$f3->run();
