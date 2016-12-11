<?php
require_once '../vendor/stripe/stripe-php/init.php';

$f3= require_once('../vendor/bcosca/fatfree/lib/base.php');
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

$f3->route('GET|HEAD /2015-black-sale', function ($f3, $args) {
  $colors= array();

  $f= fopen($f3->get('UI').'mxb-colors.txt', 'r');
  while (($line= fgets($f)) !== false) {
    $details= explode("\t", trim($line));
    $r= hexdec(substr($details[0], 0, 2));
    $g= hexdec(substr($details[0], 2, 2));
    $b= hexdec(substr($details[0], 4, 2));
    // Calculate whether to use white or black text.
    $details[]= (($r * 0.2126 + $g * 0.7152 + $b * 0.0722) > 179)
                ? '000' : 'fff';
    $colors[]= $details;
  }
  fclose($f);

  $f3->set('COLORS', $colors);

  echo Template::instance()->render('2015-black-sale.html');
});

// Handle 2015 sale
$f3->route('POST /saveOrder', function ($f3, $args) {
  $stripe= array( 'secret_key' => $f3->get('STRIPE_SECRET_KEY'),
                  'publishable_key' => $f3->get('STRIPE_KEY'));

  $token= json_decode($_REQUEST['token']);
  $amount= (int)$_REQUEST['amount'];

  $db= $f3->get('DBH');

  $sale= new DB\SQL\Mapper($db, 'sale');
  $sale->email= $token->email;
  $sale->amount= $amount;
  $sale->token= $_REQUEST['token'];
  $sale->save();

  $item= new DB\SQL\Mapper($db, 'item');

  $line= new DB\SQL\Mapper($db, 'sale_item');

  $cans= 0;
  $report= "";

  foreach ($_REQUEST['item'] as $code => $qty) {
    if ($qty == 0) continue;
    $cans+= $qty;
    $item->load(array('code = ?', "MXB-" . $code));
    $line->sale= $sale->id;
    $line->item= $item->id;
    $line->quantity= $qty;
    $line->save();
    $line->reset();

    $report.= sprintf("% 4d", $qty) . " " . $item->code . " " . $item->short_name . "\n";
  }

  \Stripe\Stripe::setApiKey($stripe['secret_key']);

  $customer= \Stripe\Customer::create(array(
    'email' => $token->email,
    'card' => $token->id
  ));

  $charge= \Stripe\Charge::create(array(
    'customer' => $customer->id,
    'amount' => (int)$_REQUEST['amount'],
    'currency' => 'usd',
  ));

  $sale->customer_id= $customer->id;
  $sale->charge_id= $charge->id;
  $sale->save();

  $f3->set('REPORT', $report);
  $f3->set('cans', $cans);
  $f3->set('total', (int)$_REQUEST['amount'] / 100);

  @mail($f3->get('CONTACT_SALES'),
        "Black Sale: $cans cans, " . $customer->email,
        Template::instance()->render('sale-email.txt', 'text/plain'),
        "From: " . $f3->get('CONTACT_SALES') . "\r\n");

  echo json_encode(array('message' => "Order has been placed."));
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

/* Handle API calls */
if ($f3->get('ADMIN')) {
  require '../lib/api.php';
  $f3->route('GET|POST /api/@action [json]', 'API->@action');
}

$f3->run();
