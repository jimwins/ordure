<?php

class GiftCard {

  static function addRoutes($f3) {
    $f3->route("POST /gift-card/process-order", 'GiftCard->process_order');
  }

  function process_order($f3, $args) {
    $stripe= array( 'secret_key' => $f3->get('STRIPE_SECRET_KEY'),
                    'publishable_key' => $f3->get('STRIPE_KEY'));

    $kit= $f3->get('REQUEST.kit');

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

    /* Create sale */
    $db= $f3->get('DBH');

    $sale= (new Sale())->create($f3, 'paid');

    $sale->name= trim($f3->get('REQUEST.name'));
    $sale->email= trim($f3->get('REQUEST.email'));

    $sale->save();

    if ($f3->get('REQUEST.address1')) {
      $address= new DB\SQL\Mapper($db, 'sale_address');

      /* Split ZIP+4, might all be in zip */
      $zip5= trim($f3->get('REQUEST.zip'));
      if (preg_match('/^(\d{5})-(\d{4})$/', $zip5, $m)) {
        $zip5= $m[1];
        $zip4= $m[2];
      } else {
        $zip4= trim($f3->get('REQUEST.zip4'));
      }

      $name= trim($f3->get('REQUEST.recipient_name'));
      $address->name= $name ? $name : trim($f3->get('REQUEST.name'));
      $address->company= trim($f3->get('REQUEST.company'));
      $address->address1= trim($f3->get('REQUEST.address1'));
      $address->address2= trim($f3->get('REQUEST.address2'));
      $address->city= trim($f3->get('REQUEST.city'));
      $address->state= trim($f3->get('REQUEST.state'));
      $address->zip5= $zip5;
      $address->zip4= $zip4;
      $address->phone= trim($f3->get('REQUEST.phone'));
      $address->verified= 0;

      $address->insert();

      $sale->shipping_address_id= $address->id;
      $sale->save();
    }

    $line= new DB\SQL\Mapper($db, 'sale_item');
    $line->sale_id= $sale->id;
    $line->item_id= $kit ? 89883 : 11212; // TODO don't hard-code
    if ($kit) {
      $line->override_name= "AzLotusArt Kit";
    }
    $line->quantity= 1;
    $line->retail_price= $charge->amount / 100;
    $line->tic= $kit ? '00000' : '10005';
    $line->tax= 0.00;

    $line->save();

    $payment= new DB\SQL\Mapper($db, 'sale_payment');
    $payment->sale_id= $sale->id;
    $payment->method= 'credit';
    $payment->amount= $charge->amount / 100;
    $payment->data= json_encode(array(
      'charge_id' => $charge->id,
      'cc_brand' => $charge->source->brand,
      'cc_last4' => $charge->source->last4,
    ));
    $payment->save();

    // save comment
    $comment= $f3->get('REQUEST.comment');

    if (trim($comment) != '') {
      $note= new DB\SQL\Mapper($db, 'sale_note');
      $note->sale_id= $sale->id;
      $note->person_id= $sale->person_id;
      $note->content= $comment;
      $note->save();
    }

    $f3->reroute($kit ? '/azlotusart-kit-thanks' : '/gift-card/thanks');
  }
}
