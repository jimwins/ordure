<?php
require '../vendor/autoload.php';

use Respect\Validation\Validator as v;

$f3= \Base::instance();
$f3->config($_ENV['ORDURE_CONFIG'] ?: '../config.ini');

$log= new \Monolog\Logger("ordure");

$log_server= $f3->get('GRAYLOG_SERVER');
if ($log_server) {
  $transport= new \Gelf\Transport\UdpTransport($log_server, 12201);
  $publisher= new \Gelf\Publisher($transport);
  $gelfHandler= new \Monolog\Handler\GelfHandler($publisher);
  $log->pushHandler($gelfHandler);
} else {
  $phpHandler= new \Monolog\Handler\ErrorLogHandler();
  $log->pushHandler($phpHandler);
}

$f3->set('log', $log);

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
// Add @first_paragraph() function for templates
$f3->set('first_paragraph', function($text) {
  $split= preg_split('!</p>!i', $text, 2);
  return $split[0]. '</p>';
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
        } else {
          if ($redir->load(['? LIKE CONCAT(source, "/%")', $path])) {
            $f3->reroute('/' . $catalog . '/' .
                         preg_replace("!^({$redir->source})/!",
                                      $redir->dest . '/', $path));
          }
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
    } elseif ($item->load(array('code=? AND active', $path))) {
      $slug= Catalog::getProductSlug($f3, $item->product);
      $f3->reroute('/' . $f3->get('CATALOG') . '/' . $slug .
                   '/' . $item->code);
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
  $email= trim($f3->get('REQUEST.email'));

  if (!v::email()->validate($email)) {
    $f3->error(500, "Sorry, you must provide a valid email address.");
  }

  // Use Cleantalk to check for spam
  $key= $f3->get('CLEANTALK_ACCESS_KEY');
  if ($key) {
    $req= new \Cleantalk\CleantalkRequest();
    $req->auth_key= $key;
    $req->agent= 'php-api';
    $req->sender_email= $email;
    $req->sender_ip= $f3->get('IP');
    $req->sender_nickname= $f3->get('REQUEST.name');
    $req->js_on= $f3->get('REQUEST.scriptable');
    $req->message= $f3->get('REQUEST.comment');
    // Calculate how long they took to fill out the form
    $when= $f3->get('REQUEST.when');
    $now= $f3->get('TIME');
    $req->submit_time= (int)($now - $when);

    $ct= new \Cleantalk\Cleantalk();
    $ct->server_url= 'http://moderate.cleantalk.org/api2.0/';

    $res= $ct->isAllowMessage($req);

    if ($res->allow == 1) {
      $f3->get('log')->info("Message allowed. Reason = " . $res->comment);
    } else {
      $f3->get('log')->info("Message forbidden. Reason = " . $res->comment);
      $f3->error(500, "Sorry, your message looks like spam.");
    }
  }

  if (preg_match('/(bitcoin|cryptocurrency|sexy?.*girl|seowriters|goo\\.gl)/i', $f3->get('REQUEST.comment'))) {
    $f3->error(500, "Sorry, your comment looks like spam.");
  }

  $template= preg_replace('/[^a-z]/', '', $f3->get('REQUEST.template'));

  $postmark= new \Postmark\PostmarkClient($f3->get('POSTMARK_TOKEN'));

  $text= Template::instance()->render('email-' . $template . '.txt');

  $from= "Raw Materials Art Supplies " . $f3->get('CONTACT_SALES');

  $postmark->sendEmail(
    $from, $f3->get('CONTACT'), $f3->get('REQUEST.subject'),
    NULL, $text, NULL, NULL,
    $f3->get('REQUEST.email'), NULL, NULL, NULL, NULL, NULL
  );

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

  $version= (int)$_REQUEST['version'] ?: 1;

  if ($version == 2) {
    $q= "LOAD DATA LOCAL INFILE ?
           REPLACE
              INTO TABLE scat_item
            FIELDS TERMINATED BY '\t'
            IGNORE 1 LINES
            (@id, retail_price, @discount_type, @discount,
             minimum_quantity, purchase_quantity,
             @stock, @active,
             code, is_dropshippable)
               SET discount_type = IF(@discount_type = 'NULL', NULL,
                                      @discount_type),
                   discount = IF(@discount = 'NULL', NULL, @discount),
                   stock = IF(@stock = 'NULL', NULL, @stock)
        ";
  } else {
    $q= "LOAD DATA LOCAL INFILE ?
           REPLACE
              INTO TABLE scat_item
            FIELDS TERMINATED BY '\t'
            IGNORE 1 LINES
            (retail_price, @discount_type, @discount, @stock,
             code, minimum_quantity, purchase_quantity, is_dropshippable)
               SET discount_type = IF(@discount_type = 'NULL', NULL,
                                      @discount_type),
                   discount = IF(@discount = 'NULL', NULL, @discount),
                   stock = IF(@stock = 'NULL', NULL, @stock)
        ";
  }

  $rows= $db->exec($q, $fn);

  touch('/tmp/last-loaded-prices');

  echo "Loaded $rows prices.";
});

$f3->route('GET /track/ups/@code', function ($f3, $args) {
  $f3->reroute('http://wwwapps.ups.com/WebTracking/processInputRequest?AgreeToTermsAndConditions=yes&track.x=38&track.y=9&InquiryNumber1=' . $f3->get('PARAMS.code'));
});

$f3->route('GET /track/upsdap/@code', function ($f3, $args) {
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

$f3->route('GET /track/yrc/@code', function ($f3, $args) {
  $f3->reroute('http://my.yrc.com/dynamic/national/servlet?CONTROLLER=com.rdwy.ec.rextracking.http.controller.ProcessPublicTrackingController&PRONumber=' . $f3->get('PARAMS.code'));
});

/* Handle authentication */
require '../lib/auth.php';
Auth::addRoutes($f3);
Auth::addFunctions($f3);

require '../lib/shipping.php';

/* Handle catalog URLs */
require '../lib/catalog.php';
Catalog::addRoutes($f3);
Catalog::addFunctions($f3);

/* Handle buying gift card */
require '../lib/gift-card.php';
GiftCard::addRoutes($f3);

/* Handle sale URLs */
require '../lib/sale.php';
Sale::addRoutes($f3);

$f3->set('CAN_ORDER', \Sale::can_order($f3));
$f3->set('CAN_PICKUP', \Sale::can_pickup($f3));
$f3->set('CAN_SHIP', \Sale::can_ship($f3));
$f3->set('CAN_DROPSHIP', \Sale::can_dropship($f3));
$f3->set('CAN_DELIVER', \Sale::can_deliver($f3));
$f3->set('CAN_TRUCK', \Sale::can_truck($f3));

/* Handle rewards URLs */
require '../lib/rewards.php';
Rewards::addRoutes($f3);

/* Handle events URLs */
require '../lib/events.php';
Events::addRoutes($f3);

/* Handle uploader URLs */
require '../lib/uploader.php';
Uploader::addRoutes($f3);

$f3->route('GET|POST /~webhook/paypal', function ($f3) {
  $body= $f3->get('BODY');

  $webhook_id= $f3->get('PAYPAL_WEBHOOK_ID');

  $headers= $f3->get('HEADERS');

  // adapted from https://stackoverflow.com/a/62870569
  if ($webhook_id) {
    $data= join('|', [
                $headers['Paypal-Transmission-Id'],
                $headers['Paypal-Transmission-Time'],
                $webhook_id,
                crc32($body) ]);

    $cert= file_get_contents($headers['Paypal-Cert-Url']);
    $pubkey= openssl_pkey_get_public($cert);

    $sig= base64_decode($headers['Paypal-Transmission-Sig']);

    $res= openssl_verify($data, $sig, $pubkey, 'sha256WithRSAEncryption');

    if ($res == 0) {
      $f3->error(500, "Webhook signature validation failed.");
    } elseif ($res < 0) {
      $f3->error(500, "Error validating signature: " . openssl_error_string());
    }
  }

  $data= json_decode($body);

  if ($data->event_type == 'PAYMENT.CAPTURE.COMPLETED') {
    $sale= new Sale();

    if (($uuid= $data->resource->custom_id)) {
      $sale_obj= $sale->load($f3, $uuid, 'uuid');
      $order_id= $sale_obj->paypal_order_id;

      error_log("Loading order_id {$order_id} by custom_id {$uuid}\n");

    } else { // an old payment where we didn't set custom_id
      // this is so dumb.
      foreach ($data->resource->links as $link) {
        if ($link->rel == 'up') {
          $order_href= $link->href;
        }
      }

      $order_id= basename(parse_url($order_href, PHP_URL_PATH));
      error_log("Loading order by parsed up link {$order_id}\n");

      $client= $sale->get_paypal_client($f3);

      $response= $client->execute(
        new \PayPalCheckoutSdk\Orders\OrdersGetRequest($order_id)
      );

      $uuid= $response->result->purchase_units[0]->reference_id;
    }

    return $sale->handle_paypal_payment($f3, $uuid, $order_id);
  }

});

$f3->route('GET|POST /~webhook/sandbox-paypal', function ($f3) {
  $client= new \GuzzleHttp\Client();
  $url= $f3->get('SANDBOX') . '/~webhook/paypal';

  $headers= $f3->get('HEADERS');
  $res= $client->request($f3->get('SERVER.REQUEST_METHOD'), $url, [
    'headers' => [
      'Content-type' => $f3->get('SERVER.HTTP_CONTENT_TYPE'),
      'Paypal-Transmission-Id' => $headers['Paypal-Transmission-Id'],
      'Paypal-Transmission-Time' => $headers['Paypal-Transmission-Time'],
      'Paypal-Cert-Url' => $headers['Paypal-Cert-Url'],
      'Paypal-Transmission-Sig' => $headers['Paypal-Transmission-Sig'],
    ],
    'body' => $f3->get('BODY'),
  ]);

  // TODO pass through headers
  echo $res->getBody();

});

$f3->route('GET|POST /~webhook/stripe', function ($f3) {
  \Stripe\Stripe::setApiKey($f3->get('STRIPE_SECRET_KEY'));

  try {
    $event= \Stripe\Webhook::constructEvent(
      $f3->get('BODY'),
      $f3->get('HEADERS')['Stripe-Signature'],
      $f3->get('STRIPE_WEBHOOK_SECRET')
    );
  } catch(\UnexpectedValueException $e) {
    // Invalid payload
    $f3->error(400, "Invalid payload");
  } catch(\Stripe\Exception\SignatureVerificationException $e) {
    $f3->error(400, "Signature exception");
  }

  // Handle the event
  switch ($event->type) {
    case 'payment_intent.succeeded':
      $paymentIntent= $event->data->object; // contains a StripePaymentIntent
      $sale= new Sale();
      $uuid= $paymentIntent->charges->data[0]->metadata->sale_uuid;
      if (!$uuid) {
        error_log("No uuid on payment_intent, probably a gift card");
        break;
      }
      $sale->handle_stripe_payment($f3, $uuid);
      break;
    case 'payment_intent.payment_failed':
      /* Don't do anything with these yet. */
      echo json_encode(array('message' => 'Success!'));
      break;
  }
});

$f3->route('GET|POST /~webhook/sandbox-stripe', function ($f3) {
  $client= new \GuzzleHttp\Client();
  $url= $f3->get('SANDBOX') . '/~webhook/stripe';

  $headers= $f3->get('HEADERS');
  $res= $client->request($f3->get('SERVER.REQUEST_METHOD'), $url, [
    'headers' => [
      'Content-type' => $f3->get('SERVER.HTTP_CONTENT_TYPE'),
      'Stripe-Signature' => $headers['Stripe-Signature'],
    ],
    'body' => $f3->get('BODY'),
  ]);

  echo $res->getBody();

});

/* Amazon webhooks */
$f3->route('GET|POST /~webhook/amazon', function ($f3) {
  $headers= $f3->get('HEADERS');
  $body= $f3->get('BODY');

  $handler= new \AmazonPay\IpnHandler($headers, $body);

  $data= $handler->toArray();

  error_log(json_encode($data)."\n");

  if ($data['NotificationType'] == 'PaymentCapture') {

  }
});

$f3->route('GET|POST /~webhook/sandbox-amazon', function ($f3) {
  $client= new \GuzzleHttp\Client();
  $url= $f3->get('SANDBOX') . '/~webhook/amazon';

  $headers= $f3->get('HEADERS');
  $res= $client->request($f3->get('SERVER.REQUEST_METHOD'), $url, [
    'headers' => $headers,
    'body' => $f3->get('BODY'),
  ]);

  echo $res->getBody();
});

/* Pass through test/staging webhooks */
$f3->route('GET|POST /~webhook/test/@name', function ($f3) {
  $key= $f3->get('REQUEST.key');

  if ($key != $f3->get('WEBHOOK_KEY')) {
    $f3->error(500, 'Wrong key.');
  }

  $client= new \GuzzleHttp\Client();
  $request_uri= preg_replace('!/test/!', '/', $f3->get('SERVER.REQUEST_URI'));
  $url= $f3->get('SANDBOX_BACKEND') . $request_uri;

  // TODO pass along headers
  $res= $client->request($f3->get('SERVER.REQUEST_METHOD'), $url, [
    'headers' => [
      'Content-type' => $f3->get('SERVER.HTTP_CONTENT_TYPE'),
    ],
    'body' => $f3->get('BODY'),
  ]);

  // TODO pass through headers
  echo $res->getBody();

});


/* Pass through webhooks to Scat */
$f3->route('GET|POST /~webhook/@name', function ($f3) {
  $key= $f3->get('REQUEST.key');

  if ($key != $f3->get('WEBHOOK_KEY')) {
    $f3->error(500, 'Wrong key.');
  }

  $client= new \GuzzleHttp\Client();
  $url= $f3->get('GIFT_BACKEND') . $f3->get('SERVER.REQUEST_URI');

  // TODO pass along headers
  $res= $client->request($f3->get('SERVER.REQUEST_METHOD'), $url, [
    'headers' => [
      'Content-type' => $f3->get('SERVER.HTTP_CONTENT_TYPE'),
    ],
    'body' => $f3->get('BODY'),
  ]);

  // TODO pass through headers
  echo $res->getBody();

});

/* Handle API calls */
if ($f3->get('ADMIN')) {
  require '../lib/api.php';
  $f3->route('GET|POST /api/@action [json]', 'API->@action');
}

$f3->run();
