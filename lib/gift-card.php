<?php

class GiftCard {

  static function addRoutes($f3) {
    $f3->route("POST /gift-card/process-order", 'GiftCard->process_order');
  }

  function process_order($f3, $args) {
    $stripe= array( 'secret_key' => $f3->get('STRIPE_SECRET_KEY'),
                    'publishable_key' => $f3->get('STRIPE_KEY'));

    $token= json_decode($_REQUEST['token']);
    $amount= (int)$_REQUEST['amount'];

    \Stripe\Stripe::setApiKey($stripe['secret_key']);

    $token= $f3->get('REQUEST.stripeToken');
    $amount= (int)(ltrim($f3->get('REQUEST.amount'), '$') * 100);

    try {
      $charge= \Stripe\Charge::create(array(
        "amount" => $amount,
        "currency" => "usd",
        "source" => $token,
        "receipt_email" => $f3->get('REQUEST.email'),
      ));
    } catch (\Stripe\Error\Card $e) {
      // The card has been declined!
      $body= $e->getJsonBody();
      $err= $body['error'];

      // XXX Send email to admin

      error_log(json_encode($body));

      $f3->error(500, $err['message']);
    }

    $httpClient= new \Http\Adapter\Guzzle6\Client(new \GuzzleHttp\Client());
    $sparky= new \SparkPost\SparkPost($httpClient,
                           [ 'key' => $f3->get('SPARKPOST_KEY') ]);

    $text= Template::instance()->render('email-gift-card-sale.txt');

    $promise= $sparky->transmissions->post([
      'content' => [
        'text' => $text,
        'subject' => "Sale: Gift Card",
        'from' => array('name' => 'Raw Materials Art Supplies',
                        'email' => $f3->get('CONTACT_SALES')),
      ],
      'recipients' => [
        [
          'address' => [
            'name' => '',
            'email' => $f3->get('CONTACT_SALES'),
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

    $f3->reroute('/gift-card/thanks');
  }
}
