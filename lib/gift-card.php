<?php

class GiftCard {

  static function addRoutes($f3) {
    $f3->route("POST /gift-card/get-stripe-payment-intent [ajax]",
               'GiftCard->get_stripe_payment_intent');
    $f3->route("POST /gift-card/process-stripe-payment",
               'GiftCard->process_stripe_payment');
  }

  function get_stripe_payment_intent($f3, $args) {
    $stripe= new \Stripe\StripeClient($f3->get('STRIPE_SECRET_KEY'));

    bcscale(2);
    $due= $f3->get('REQUEST.amount');
    $amount= (int)bcmul($due, 100);

    if (!$amount) {
      $f3->error(500, "Have to set a valid amount for the card!");
    }

    $customer= $stripe->customers->create([
      'name' => $f3->get('REQUEST.name'),
      'email' => $f3->get('REQUEST.email'),
    ]);
    $payment_intent= $stripe->paymentIntents->create([
      'customer' => $customer->id,
      'amount' => $amount,
      'currency' => 'usd',
    ]);

    echo json_encode([
      'secret' => $payment_intent->client_secret,
    ]);
  }

  function process_stripe_payment($f3, $args) {

    /* Create sale */
    $db= $f3->get('DBH');

    $sale= (new Sale())->create($f3, 'paid');

    $sale->name= trim($f3->get('REQUEST.name'));
    $sale->email= trim($f3->get('REQUEST.email'));

    $sale->save();

    if ($f3->get('REQUEST.recipient_name') ||
        $f3->get('REQUEST.address1'))
    {
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
      $address->email= trim($f3->get('REQUEST.recipient_email'));
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

    $stripe= new \Stripe\StripeClient($f3->get('STRIPE_SECRET_KEY'));

    $payment_intent_id= $f3->get('REQUEST.payment_intent_id');
    $payment_intent= $stripe->paymentIntents->retrieve($payment_intent_id, []);

    if ($payment_intent->status != 'succeeded') {
      $f3->error(500, "Can only handle successful payment attempts here.");
    }

    foreach ($payment_intent->charges->data as $charge) {
      $line= new DB\SQL\Mapper($db, 'sale_item');
      $line->sale_id= $sale->id;
      $line->item_id= 11212; // TODO don't hard-code
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
    }

    // save comment
    $comment= $f3->get('REQUEST.comment');

    if (trim($comment) != '') {
      $note= new DB\SQL\Mapper($db, 'sale_note');
      $note->sale_id= $sale->id;
      $note->person_id= $sale->person_id;
      $note->content= $comment;
      $note->save();
    }

    echo json_encode(array('message' => 'Success!'));
  }
}
