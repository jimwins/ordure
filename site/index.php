<?php
require '../vendor/autoload.php';

$f3= \Base::instance();
$f3->config($_ENV['ORDURE_CONFIG'] ?: '../config.ini');

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

$f3->set('includeTemplate', function($template) {
  echo Template::instance()->render("$template.html");
});

$f3->set('includeFragment', function($template) {
  $db= \Base::instance()->get('DBH');

  $page= new DB\SQL\Mapper($db, 'page');

  if ($page->load(array('slug=?', $template))) {
    $text= Template::instance()->resolve($page->content);
    echo Markdown::instance()->convert($text);
  } else {
    echo "Couldn't find '$template'";
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

    $code= $f3->get('ERROR.code');

    if ($code == "404") {
      $path= $f3->get('PATH');

      if ($path == '/') die("Confused. Hang on.");

      $catalog= $f3->get('CATALOG');
      if (!strncmp($path, '/' . $catalog . '/', strlen($catalog)+2)) {
        $path= substr($path, strlen($catalog)+2);
        $redir= new DB\SQL\Mapper($db, 'catalog_redirect');
        if ($redir->load(array('? LIKE source', $path))) {
          $q= $f3->get('QUERY');
          if (($pos= strpos($redir->source, '%'))) {
            $dest= $redir->dest . substr($path, $pos);
          } else {
            $dest= $redir->dest;
          }
          $f3->reroute('/' . $catalog . '/' . $dest . ($q ? "?$q" : ""));
        }
      } else {
        $redir= new DB\SQL\Mapper($db, 'redirect');

        if ($redir->load(array('source LIKE ?', $path . '%'))) {
          $q= $f3->get('QUERY');
          $f3->reroute($redir->dest . ($q ? "?$q" : ""));
        }
      }
    }

    // XXX There is some sort of bug in calling a template within ONERROR
    // related to escaping. Running fast and loose for now.
    $f3->set('ESCAPE', false);
    echo Template::instance()->render("$code.html");
  }
});

class Page {

  function getPage($f3, $args) {
    $db= $f3->get('DBH');

    $page= new DB\SQL\Mapper($db, 'page');
    $item= new DB\SQL\Mapper($db, 'item');

    $path= $args['*'];

    if ($page->load(array('slug=?', empty($path) ? '//' : $path))) {
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
    } elseif (is_file($path)) {
      \Web::instance()->send($path, NULL, 0, false);
    } else {
      $f3->error(404);
    }

  }

}

class Helper extends \Prefab {
  function json($val) {
    return json_encode($val);
  }
}

\Template::instance()->filter('json','\Helper::instance()->json');

$f3->route('GET|HEAD /*', 'Page->getPage');
$f3->route('GET|HEAD /', 'Page->getPage');

$f3->route('POST /contact', function ($f3, $args) {

  if (preg_match('/(seowriters|goo\\.gl)/i', $f3->get('REQUEST.comment'))) {
    $f3->error(500, "Sorry, your comment looks like spam.");
  }

  $template= preg_replace('/[^a-z]/', '', $f3->get('REQUEST.template'));

  $httpClient= new \Http\Adapter\Guzzle6\Client(new \GuzzleHttp\Client());
  $sparky= new \SparkPost\SparkPost($httpClient,
                         [ 'key' => $f3->get('SPARKPOST_KEY') ]);

  $text= Template::instance()->render('email-' . $template . '.txt');

  $promise= $sparky->transmissions->post([
    'content' => [
      'text' => $text,
      'subject' => $f3->get('REQUEST.subject'),
      'from' => array('name' => 'Raw Materials Art Supplies',
                      'email' => $f3->get('CONTACT_SALES')),
      'reply_to' => $f3->get('REQUEST.email')
    ],
    'recipients' => [
      [
        'address' => [
          'name' => '',
          'email' => $f3->get('CONTACT'),
        ],
      ],
    ],
    'options' => [
      'inlineCss' => true,
      'transactional' => true,
    ],
  ]);

  try {
    $response= $promise->wait();
    // XXX handle response
  } catch (\Exception $e) {
    error_log(sprintf("SparkPost failure: %s (%s)",
                      $e->getMessage(), $e->getCode()));
  }

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

  touch('/tmp/last-loaded-prices');

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

$f3->route('GET /track/gso/@code', function ($f3, $args) {
  $f3->reroute('https://www.gso.com/Tracking/PackageDetail?TrackingNumber=' . $f3->get('PARAMS.code'));
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

/* Handle sale URLs */
if ($f3->get('FEATURE_sale')) {
require '../lib/sale.php';
Sale::addRoutes($f3);
}

/* Handle rewards URLs */
require '../lib/rewards.php';
Rewards::addRoutes($f3);

/* Handle API calls */
if ($f3->get('ADMIN')) {
  require '../lib/api.php';
  $f3->route('GET|POST /api/@action [json]', 'API->@action');
}

$f3->run();
