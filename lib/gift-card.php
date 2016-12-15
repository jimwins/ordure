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
        "description" => "Gift Card",
        "statement_descriptor" => "Raw Materials Gift"
      ));
    } catch (\Stripe\Error\Card $e) {
      // The card has been declined!
      $f3->error(500);
    }

    $headers= array();
    $headers[]= "From: " . $f3->get('CONTACT');
    $headers[]= "Reply-To: " . $f3->get('REQUEST.email');

    @mail($f3->get('CONTACT'),
          "Sale: Gift Card",
          Template::instance()->render('email-gift-card-sale.txt',
                                       'text/plain'),
          implode("\r\n", $headers));

    $f3->reroute('thanks');
  }
}
