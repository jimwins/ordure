<?php

$f3->set('amount', function ($d) {
  return ($d < 0 ? '(' : '') . '$' . sprintf("%.2f", abs($d)) . ($d < 0 ? ')' : '');
});

class Sale {

  static function addRoutes($f3) {
    $f3->route("GET|HEAD /sale/new", 'Sale->create');
    $f3->route("GET|HEAD /sale/list", 'Sale->showList');
    $f3->route("GET|HEAD /sale/@sale", 'Sale->dispatch');
    $f3->route("GET|HEAD /sale/@sale/edit", 'Sale->edit');
    $f3->route("GET|HEAD /sale/@sale/pay", 'Sale->pay');
    $f3->route("GET|HEAD /sale/@sale/paid", 'Sale->status'); // XXX thanks
    $f3->route("GET|HEAD /sale/@sale/thanks", 'Sale->status');
    $f3->route("GET|HEAD /sale/@sale/status", 'Sale->status');
    $f3->route("GET|HEAD /sale/@sale/json", 'Sale->json');
    $f3->route("POST /sale/@sale/add-item [ajax]", 'Sale->add_item');
    $f3->route("POST /sale/@sale/calculate-sales-tax [ajax]",
               'Sale->calculate_sales_tax');
    $f3->route("POST /sale/@sale/generate-bitcoin-address [ajax]",
               'Sale->generate_bitcoin_address');
    $f3->route("POST /sale/@sale/get-giftcard-balance [ajax]",
               'Sale->get_giftcard_balance');
    $f3->route("POST /sale/@sale/process-giftcard-payment",
               'Sale->process_giftcard_payment');
    $f3->route("POST /sale/@sale/process-creditcard-payment",
               'Sale->process_creditcard_payment');
    $f3->route("POST /sale/@sale/process-bitcoin-payment [ajax]",
               'Sale->process_bitcoin_payment');
    $f3->route("POST /sale/@sale/process-paypal-payment [ajax]",
               'Sale->process_paypal_payment');
    $f3->route("POST /sale/@sale/remove-item [ajax]", 'Sale->remove_item');
    $f3->route("POST /sale/@sale/update-item [ajax]", 'Sale->update_item');
    $f3->route("POST /sale/@sale/set-address [ajax]", 'Sale->set_address');
    $f3->route("POST /sale/@sale/set-person [ajax]", 'Sale->set_person');
    $f3->route("POST /sale/@sale/set-status [ajax]", 'Sale->set_status');
    $f3->route("POST /sale/@sale/verify-address [ajax]",
               'Sale->verify_address');

    $f3->route("GET|HEAD /shipstation", 'Sale->shipstation_get');
    $f3->route("POST /shipstation", 'Sale->shipstation_post');
  }

  function create($f3, $args) {
    if (\Auth::authenticated_user($f3) != 1)
      $f3->error(403);

    $db= $f3->get('DBH');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->person_id= 0;
    // Could use real UUID() but this is shorter. Hardcoded '1' could be
    // replaced with a server-id to further avoid collisions
    $sale->uuid= sprintf("%08x%02x%s", time(), 1, bin2hex(random_bytes(8)));

    $sale->insert();

    $f3->reroute("./" . $sale->uuid);
  }

  function showList($f3, $args) {
    if (\Auth::authenticated_user($f3) != 1)
      $f3->error(403);

    $db= $f3->get('DBH');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->total= 'CAST(
                     ROUND_TO_EVEN(shipping + shipping_tax +
                                   (SELECT SUM(quantity *
                                               sale_price(retail_price,
                                                          discount_type,
                                                          discount)
                                               + tax)
                                      FROM sale_item WHERE sale_id = sale.id),
                                   2)
                     AS DECIMAL(9,2))';
    $sale->paid= '(SELECT SUM(amount)
                     FROM sale_payment
                    WHERE sale_id = sale.id)';
    $sales= $sale->find(array(),
                        array('order' => 'id'));
    $sales_out= array();
    foreach ($sales as $i) {
      $sales_out[]= $i->cast();
    }
    $f3->set('sales', $sales_out);

    echo Template::instance()->render('sale-list.html');
  }

  function load($f3, $sale_id, $type= 'id') {
    $db= $f3->get('DBH');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->subtotal= '(SELECT SUM(quantity *
                                  sale_price(retail_price,
                                             discount_type,
                                             discount))
                      FROM sale_item WHERE sale_id = sale.id)';
    $sale->tax= 'CAST(
                   ROUND_TO_EVEN(shipping_tax +
                                 (SELECT SUM(tax)
                                    FROM sale_item WHERE sale_id = sale.id),
                                 2)
                   AS DECIMAL(9,2))';
    $sale->total= 'CAST(
                     ROUND_TO_EVEN(shipping + shipping_tax +
                                   (SELECT SUM(quantity *
                                               sale_price(retail_price,
                                                          discount_type,
                                                          discount)
                                               + tax)
                                      FROM sale_item WHERE sale_id = sale.id),
                                   2)
                     AS DECIMAL(9,2))';
    $sale->paid= '(SELECT SUM(amount)
                     FROM sale_payment
                    WHERE sale_id = sale.id)';
    $sale->load(array($type . ' = ?', $sale_id))
      or $f3->error(404);
    $sale->copyTo('sale');

    $person= new DB\SQL\Mapper($db, 'person');
    $person->load(array('id = ?', $sale->person_id));
    $person->copyTo('person');

    $billing_address= new DB\SQL\Mapper($db, 'sale_address');
    $billing_address->load(array('id = ?', $sale->billing_address_id));
    $billing_address->copyTo('billing_address');

    $shipping_address= new DB\SQL\Mapper($db, 'sale_address');
    $shipping_address->load(array('id = ?', $sale->shipping_address_id));
    if (!$shipping_address->dry()) {
      $shipping_address->copyTo('shipping_address');
    } else {
      $billing_address->copyTo('shipping_address');
    }

    $item= new DB\SQL\Mapper($db, 'sale_item');
    $item->code= "(SELECT code FROM item WHERE id = item_id)";
    $item->name= "(SELECT name FROM item WHERE id = item_id)";
    $item->sale_price= "sale_price(retail_price, discount_type, discount)";
    $item->detail= "(SELECT IFNULL(CONCAT(IF(item.retail_price,
                                             'MSRP $', 'List $'),
                                          sale_item.retail_price,
                                          CASE sale_item.discount_type
                                            WHEN 'percentage' THEN
                                              CONCAT(' / Sale: ',
                                                     ROUND(sale_item.discount),
                                                     '% off')
                                            WHEN 'relative' THEN
                                              CONCAT(' / Sale: $',
                                                     sale_item.discount,
                                                     ' off')
                                            WHEN 'fixed' THEN
                                              ''
                                            END), '')
                       FROM item WHERE id = item_id)";

    $items= $item->find(array('sale_id = ?', $sale->id),
                         array('order' => 'id'));
    $items_out= array();
    foreach ($items as $i) {
      $items_out[]= $i->cast();
    }
    $f3->set('items', $items_out);

    $payment= new DB\SQL\Mapper($db, 'sale_payment');
    $payments= $payment->find(array('sale_id = ?', $sale->id),
                         array('order' => 'id'));
    $payments_out= array();
    foreach ($payments as $i) {
      $pay= $i->cast();
      $pay['data']= json_decode($pay['data'], true);
      $payments_out[]= $pay;
    }
    $f3->set('payments', $payments_out);

    return $sale;
  }

  function dispatch($f3, $args) {
    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->load(array('uuid = ?', $sale_uuid))
      or $f3->error(404);

    switch ($sale->status) {
    case 'new':
      return $f3->reroute($sale->uuid . '/edit');
    case 'unpaid':
      return $f3->reroute($sale->uuid . '/pay');
    case 'paid':
    case 'shipped':
    case 'cancelled':
    case 'onhold':
      return $f3->reroute($sale->uuid . '/status');
    default:
      $f3->error(404);
    }
  }

  function edit($f3, $args) {
    if (\Auth::authenticated_user($f3) != 1)
      $f3->error(403);
    $this->load($f3, $f3->get('PARAMS.sale'), 'uuid');
    echo Template::instance()->render('sale-edit.html');
  }

  function pay($f3, $args) {
    $gateway= new Braintree_Gateway(array(
      'accessToken' => $f3->get('VZERO_TOKEN'))
    );
    $f3->set('VZERO_CLIENT_TOKEN', $gateway->clientToken()->generate());

    $this->load($f3, $f3->get('PARAMS.sale'), 'uuid');
    echo Template::instance()->render('sale-pay.html');
  }

  function status($f3, $args) {
    $this->load($f3, $f3->get('PARAMS.sale'), 'uuid');
    echo Template::instance()->render('sale-status.html');
  }

  function add_item($f3, $args) {
    if (\Auth::authenticated_user($f3) != 1)
      $f3->error(403);

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');
    $item_code= $f3->get('REQUEST.item');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->load(array('uuid = ?', $sale_uuid))
      or $f3->error(404);

    $item= new DB\SQL\Mapper($db, 'item');
    $item->nretail_price= "IFNULL((SELECT retail_price FROM scat_item WHERE scat_item.code = item.code), retail_price)";
    $item->discount_type= "(SELECT discount_type FROM scat_item WHERE scat_item.code = item.code)";
    $item->discount= "(SELECT discount FROM scat_item WHERE scat_item.code = item.code)";
    $item->npurchase_quantity= "IFNULL((SELECT scat_item.purchase_quantity FROM scat_item WHERE scat_item.code = item.code), purchase_quantity)";
    $item->load(array('code = ?', $item_code))
      or $f3->error(404);

    $line= new DB\SQL\Mapper($db, 'sale_item');
    $line->sale_id= $sale->id;
    $line->item_id= $item->id;
    $line->quantity= $item->npurchase_quantity;
    $line->retail_price= $item->nretail_price;
    $line->discount_type= $item->discount_type;
    $line->discount= $item->discount;
    $line->discount_manual= 0;
    $line->tic= $item->tic;
    $line->tax= 0.00;

    $line->insert();

    $this->update_shipping($f3, $args);

    $sale->tax_calculated= null;
    $sale->save();

    return $this->json($f3, $args);
  }

  function remove_item($f3, $args) {
    if (\Auth::authenticated_user($f3) != 1)
      $f3->error(403);

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');
    $sale_item_id= $f3->get('REQUEST.item');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->load(array('uuid = ?', $sale_uuid))
      or $f3->error(404);

    $line= new DB\SQL\Mapper($db, 'sale_item');
    $line->load(array('id = ?', $sale_item_id))
      or $f3->error(404);
    $line->erase();

    $this->update_shipping($f3, $args);

    $sale->tax_calculated= null;
    $sale->save();

    return $this->json($f3, $args);
  }

  function update_item($f3, $args) {
    if (\Auth::authenticated_user($f3) != 1)
      $f3->error(403);

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');
    $sale_item_id= $f3->get('REQUEST.item');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->load(array('uuid = ?', $sale_uuid))
      or $f3->error(404);

    $line= new DB\SQL\Mapper($db, 'sale_item');
    $line->load(array('id = ?', $sale_item_id))
      or $f3->error(404);

    if ($f3->exists('REQUEST.quantity')) {
      $line->quantity= (int)$f3->get('REQUEST.quantity');
    }

    $line->save();

    $this->update_shipping($f3, $args);

    $sale->tax_calculated= null;
    $sale->save();

    return $this->json($f3, $args);
  }

  function update_shipping($f3, $args) {
    if (\Auth::authenticated_user($f3) != 1)
      $f3->error(403);

    $sale= $this->load($f3, $f3->get('PARAMS.sale'), 'uuid');

    if ($sale->shipping_manual)
      return;

    if ($sale->subtotal >= 150.00) {
      $sale->shipping= 0.00;
    } else if ($sale->subtotal >= 100.00) {
      $sale->shipping= 13.95;
    } else if ($sale->subtotal >= 50.00) {
      $sale->shipping= 11.95;
    } else {
      $sale->shipping= 7.95;
    }
    $sale->shipping_tax= 0;

    $sale->save();
  }

  function set_address($f3, $args) {
    if (\Auth::authenticated_user($f3) != 1)
      $f3->error(403);

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->load(array('uuid = ?', $sale_uuid))
      or $f3->error(404);

    $type= $f3->get('REQUEST.type');

    $address= new DB\SQL\Mapper($db, 'sale_address');
    if (($address_id= $f3->get('REQUEST.id'))) {
      $address->load(array('id = ?', $address_id))
        or $f3->error(404);
    }
    
    $address->name= $f3->get('REQUEST.name');
    $address->address1= $f3->get('REQUEST.address1');
    $address->address2= $f3->get('REQUEST.address2');
    $address->city= $f3->get('REQUEST.city');
    $address->state= $f3->get('REQUEST.state');
    $address->zip5= $f3->get('REQUEST.zip5');
    $address->zip4= $f3->get('REQUEST.zip4');
    $address->verified= 0;

    $address->save();

    if ($type == 'shipping') {
      $sale->shipping_address_id= $address->id;
    } else {
      $sale->billing_address_id= $address->id;
    }

    $sale->save();

    return $this->json($f3, $args);
  }

  function verify_address($f3, $args) {
    if (\Auth::authenticated_user($f3) != 1)
      $f3->error(403);

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->load(array('uuid = ?', $sale_uuid))
      or $f3->error(404);

    $type= $f3->get('REQUEST.type');

    $address= new DB\SQL\Mapper($db, 'sale_address');
    $address_id= $f3->get('REQUEST.id');
    $address->load(array('id = ?', $address_id))
      or $f3->error(404);

    $data= array(
      'Zip4' => $address->zip4,
      'Zip5' => $address->zip5,
      'State' => $address->state,
      'City' => $address->city,
      'Address2' => $address->address2,
      'Address1' => $address->address1,
      'apiLoginID' => $f3->get("TAXCLOUD_ID")
    );

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://api.taxcloud.net/1.0/taxcloud/VerifyAddress?apiKey=" . $f3->get('TAXCLOUD_KEY'),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => json_encode($data),
      CURLOPT_HTTPHEADER => array(
        "accept: application/json",
        "content-type: application/json"
      ),
    ));

    $response= curl_exec($curl);
    $err= curl_error($curl);

    curl_close($curl);

    if ($err) {
      // XXX blah
      echo "cURL Error #:" . $err;
      return;
    }

    $data= json_decode($response);

    if ($data->ErrNumber == "0") {
      $address->zip4= $data->Zip4;
      $address->zip5= $data->Zip5;
      $address->state= $data->State;
      $address->city= $data->City;
      $address->address2= $data->Address2;
      $address->address1= $data->Address1;
      $address->verified= 1;
      $address->save();
    }

    return $this->json($f3, $args);
  }

  function set_person($f3, $args) {
    if (\Auth::authenticated_user($f3) != 1)
      $f3->error(403);

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->load(array('uuid = ?', $sale_uuid))
      or $f3->error(404);

    $sale->name= $f3->get('REQUEST.name');
    $sale->email= $f3->get('REQUEST.email');
    $sale->save();

    return $this->json($f3, $args);
  }

  function set_status($f3, $args) {
    if (\Auth::authenticated_user($f3) != 1)
      $f3->error(403);

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->load(array('uuid = ?', $sale_uuid))
      or $f3->error(404);

    $status= $f3->get('REQUEST.status');

    if (!in_array($status, array('new','unpaid','paid','shipped',
                                 'cancelled','onhold'))) {
      // XXX better error handling
      $f3->error(500);
    }

    $sale->status= $status;

    $sale->save();

    return $this->json($f3, $args);
  }

  function calculate_sales_tax($f3, $args) {
    if (\Auth::authenticated_user($f3) != 1)
      $f3->error(403);

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->load(array('uuid = ?', $sale_uuid))
      or $f3->error(404);

    $address= new DB\SQL\Mapper($db, 'sale_address');
    $address->load(array('id = ?',
                         $sale->shipping_address_id ?
                         $sale->shipping_address_id :
                         $sale->billing_address_id))
      or $f3->error(404);

    $data= array(
      'apiLoginID' => $f3->get("TAXCLOUD_ID"),
      'customerID' => $sale->person_id,
      'cartID' => $sale->uuid,
      'deliveredBySeller' => false,
      'origin' => array(
        'Zip4' => '1320',
        'Zip5' => '90013',
        'State' => 'CA',
        'City' => 'Los Angeles',
        'Address2' => '',
        'Address1' => '436 S Main St',
      ),
      'destination' => array(
        'Zip4' => $address->zip4,
        'Zip5' => $address->zip5,
        'State' => $address->state,
        'City' => $address->city,
        'Address2' => $address->address2,
        'Address1' => $address->address1,
      ),
      'cartItems' => array(),
    );

    $item= new DB\SQL\Mapper($db, 'sale_item');
    $item->sale_price= "sale_price(retail_price, discount_type, discount)";
    $items= $item->find(array('sale_id = ?', $sale->id),
                         array('order' => 'id'));
    foreach ($items as $i) {
      $data['cartItems'][]= array(
        'Index' => $i->id,
        'ItemID' => $i->item_id,
        'TIC' => $i->tic,
        'Price' => $i->sale_price,
        'Qty' => $i->quantity,
      );
    }

    if ($sale->shipping) {
      $data['cartItems'][]= array(
        'Index' => 0,
        'ItemID' => 'shipping',
        'TIC' => '11000',
        'Price' => $sale->shipping,
        'Qty' => 1,
      );
    }

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://api.taxcloud.net/1.0/taxcloud/Lookup?apiKey=" .
                     $f3->get('TAXCLOUD_KEY'),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => json_encode($data),
      CURLOPT_HTTPHEADER => array(
        "accept: application/json",
        "content-type: application/json"
      ),
    ));

    $response= curl_exec($curl);
    $err= curl_error($curl);

    curl_close($curl);

    if ($err) {
      $f3->error(500, "cURL Error #:" . $err);
    }

    error_log($response);
    $data= json_decode($response);

    foreach ($data->CartItemsResponse as $response) {
      if ($response->CartItemIndex == 0) {
        $sale->shipping_tax= $response->TaxAmount;
        $sale->save();
        continue;
      }

      $item->load(array('id = ?', $response->CartItemIndex))
        or $f3->error(404);
      $item->tax= $response->TaxAmount;
      $item->save();
    }

    $sale->tax_calculated= date('Y-m-d H:i:s');
    $sale->save();

    return $this->json($f3, $args);
  }

  function capture_sales_tax($f3, $sale) {
    $curl= curl_init();

    $data= array(
      'apiLoginID' => $f3->get("TAXCLOUD_ID"),
      'customerID' => $sale->person_id,
      'cartID' => $sale->uuid,
      'orderID' => $sale->uuid,
      'dateAuthorized' => date("Y-m-d"),
      'dateCaptured' => date("Y-m-d"),
    );

    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://api.taxcloud.net/1.0/taxcloud/AuthorizedWithCapture?apiKey=" . $f3->get('TAXCLOUD_KEY'),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => json_encode($data),
      CURLOPT_HTTPHEADER => array(
        "accept: application/json",
        "content-type: application/json"
      ),
    ));

    $response= curl_exec($curl);
    $err= curl_error($curl);

    curl_close($curl);

    if ($err) {
      // XXX do something
      error_log("cURL Error #:" . $err);
    }
  }

  function json($f3, $args) {
    $this->load($f3, $f3->get('PARAMS.sale'), 'uuid');

    echo json_encode(array( 'sale' => $f3->get('sale'),
                            'person' => $f3->get('person'),
                            'billing_address' => $f3->get('billing_address'),
                            'shipping_address' => $f3->get('shipping_address'),
                            'items' => $f3->get('items'),
                            'payments' => $f3->get('payments')));
  }

  function generate_bitcoin_address($f3, $args) {
    $sale= $this->load($f3, $f3->get('PARAMS.sale'), 'uuid');

    $amount= (int)(($sale->total - $sale->paid) * 100);

    \Stripe\Stripe::setApiKey($f3->get('STRIPE_SECRET_KEY'));

    $source= \Stripe\Source::create(array(
      "type" => "bitcoin",
      "amount" => $amount,
      "currency" => "usd",
      "owner" => array(
        "email" => $sale->email
      )
    ));

    echo json_encode(array(
      'bitcoin_amount' => $source->bitcoin->amount,
      'receiver_address' => $source->receiver->address,
      'bitcoin_uri' => $source->bitcoin->uri,
      'source_id' => $source->id,
      'source_client_secret' => $source->client_secret,
    ));
  }

  function process_creditcard_payment($f3, $args) {
    $stripe= array( 'secret_key' => $f3->get('STRIPE_SECRET_KEY'),
                    'publishable_key' => $f3->get('STRIPE_KEY'));

    $db= $f3->get('DBH');

    $sale= $this->load($f3, $f3->get('PARAMS.sale'), 'uuid');

    $amount= (int)(($sale->total - $sale->paid) * 100);

    \Stripe\Stripe::setApiKey($stripe['secret_key']);

    $token= $f3->get('REQUEST.stripeToken');

    try {
      $charge= \Stripe\Charge::create(array(
        "amount" => $amount,
        "currency" => "usd",
        "source" => $token,
        "receipt_email" => $sale->email,
      ));
    } catch (\Stripe\Error\Card $e) {
      // The card has been declined!
      $body= $e->getJsonBody();
      $err= $body['error'];

      // XXX Send email to admin

      $f3->error(500, $err['message']);
    }

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

    self::capture_sales_tax($f3, $sale);

    $sale->status= 'paid';
    $sale->save();

    /*
    $headers= array();
    $headers[]= "From: " . $f3->get('CONTACT');
    $headers[]= "Reply-To: " . $f3->get('REQUEST.email');

    @mail($f3->get('CONTACT'),
          "Sale: Gift Card",
          Template::instance()->render('email-gift-card-sale.txt',
                                       'text/plain'),
          implode("\r\n", $headers));
    */

    if ($f3->get('AJAX')) {
      echo json_encode(array('message' => 'Success!'));
    } else {
      $f3->reroute('thanks');
    }
  }

  function process_bitcoin_payment($f3, $args) {
    $stripe= array( 'secret_key' => $f3->get('STRIPE_SECRET_KEY'),
                    'publishable_key' => $f3->get('STRIPE_KEY'));

    $token= json_decode($_REQUEST['token']);

    $db= $f3->get('DBH');

    $sale= $this->load($f3, $f3->get('PARAMS.sale'), 'uuid');

    $amount= (int)($sale->total * 100);

    \Stripe\Stripe::setApiKey($stripe['secret_key']);

    $token= $f3->get('REQUEST.stripeToken');

    $source= \Stripe\Source::create(array(
      "type" => "bitcoin",
      "amount" => $amount,
      "currency" => "usd",
      "owner" => array(
        "email" => $sale->email
      )
    ));

    try {
      $charge= \Stripe\Charge::create(array(
        "amount" => $source->amount,
        "currency" => $source->currency,
        "source" => $source->id,
        "receipt_email" => $person->email,
      ));
    } catch (\Stripe\Error\Base $e) {
      $body= $e->getJsonBody();
      $err= $body['error'];

      // XXX Send email to admin

      $f3->error(500, $err['message']);
    }

    $payment= new DB\SQL\Mapper($db, 'sale_payment');
    $payment->sale_id= $sale->id;
    $payment->method= 'bitcoin';
    $payment->amount= $charge->amount / 100;
    $payment->data= json_encode(array(
      'charge_id' => $charge->id,
    ));
    $payment->save();

    self::capture_sales_tax($f3, $sale);

    $sale->status= 'paid';
    $sale->save();

    /*
    $headers= array();
    $headers[]= "From: " . $f3->get('CONTACT');
    $headers[]= "Reply-To: " . $f3->get('REQUEST.email');

    @mail($f3->get('CONTACT'),
          "Sale: Gift Card",
          Template::instance()->render('email-gift-card-sale.txt',
                                       'text/plain'),
          implode("\r\n", $headers));
    */

    echo json_encode(array());
  }

  function process_paypal_payment($f3, $args) {
    $gateway= new Braintree_Gateway(array(
      'accessToken' => $f3->get('VZERO_TOKEN'))
    );

    $db= $f3->get('DBH');

    $sale= $this->load($f3, $f3->get('PARAMS.sale'), 'uuid');

    $shipping_address= new DB\SQL\Mapper($db, 'sale_address');
    $shipping_address->load(array('id = ?',
                                  $order->shipping_address_id ? 
                                  $order->shipping_address_id :
                                  $order->billing_address_id));

    $nonce= $f3->get('REQUEST.nonce');

    list($firstName, $lastName)= explode(' ', $sale->name, 2);

    $result= $gateway->transaction()->sale([
      "amount" => $sale->total,
      'merchantAccountId' => 'USD',
      "paymentMethodNonce" => $nonce,
      "orderId" => sprintf("%07d", $sale->id),
      "shipping" => [
        "firstName" => $firstName,
        "lastName" => $lastName,
        "company" => "",
        "streetAddress" => $shipping_address->address1,
        "extendedAddress" => $shipping_address->address2,
        "locality" => $shipping_address->city,
        "region" => $shipping_address->state,
        "postalCode" => $shipping_address->zip5,
        "countryCodeAlpha2" => "US"
      ],
      "options" => [
        "submitForSettlement" => true,
      ],
    ]);

    error_log($result);

    if (!$result->success) {
      $f3->error(500, $result->message);
    }

    $payment= new DB\SQL\Mapper($db, 'sale_payment');
    $payment->sale_id= $sale->id;
    $payment->method= 'paypal';
    $payment->amount= $result->transaction->amount;
    $payment->data= json_encode(array(
      'id' => $result->transaction->id,
      'sellerProtectionStatus' =>
        $result->paypalDetails->sellerProtectionStatus,
    ));
    $payment->save();

    self::capture_sales_tax($f3, $sale);

    $sale->status= 'paid';
    $sale->save();

    /*
    $headers= array();
    $headers[]= "From: " . $f3->get('CONTACT');
    $headers[]= "Reply-To: " . $f3->get('REQUEST.email');

    @mail($f3->get('CONTACT'),
          "Sale: Gift Card",
          Template::instance()->render('email-gift-card-sale.txt',
                                       'text/plain'),
          implode("\r\n", $headers));
    */

    echo json_encode(array());
  }

  function get_giftcard_balance($f3, $args) {
    $sale= $this->load($f3, $f3->get('PARAMS.sale'), 'uuid');

    $curl = curl_init();

    $data= array('card' => $f3->get('REQUEST.card'));

    curl_setopt_array($curl, array(
      CURLOPT_URL => $f3->get('GIFT_BACKEND') . '/check-balance.php' .
                     '?card=' . rawurlencode($f3->get('REQUEST.card')),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    ));

    $response= curl_exec($curl);
    $err= curl_error($curl);

    curl_close($curl);

    if ($err) {
      $f3->error(500, "cURL Error #:" . $err);
    }

    // have to strip jsonp wrapping
    $response= substr($response, 1, -2);

    error_log($response);
    $data= json_decode($response);

    // turn soft errors into hard ones
    if ($data->error) {
      return $f3->error(500, $data->error);
    }

    echo $response;
  }

  function process_giftcard_payment($f3, $args) {
    $db= $f3->get('DBH');

    $sale= $this->load($f3, $f3->get('PARAMS.sale'), 'uuid');

    $curl= curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $f3->get('GIFT_BACKEND') . '/check-balance.php' .
                     '?card=' . rawurlencode($f3->get('REQUEST.card')),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    ));

    $response= curl_exec($curl);
    $err= curl_error($curl);

    curl_close($curl);

    if ($err) {
      $f3->error(500, "cURL Error #:" . $err);
    }

    // have to strip jsonp wrapping
    $response= substr($response, 1, -2);
    $data= json_decode($response);

    // turn soft errors into hard ones
    if ($data->error) {
      return $f3->error(500, $data->error);
    }

    $amount= -min($data->balance, $sale->total - $sale->paid);

    $curl= curl_init();

    $data= array(
      'card' => $f3->get('REQUEST.card'),
      'amount' => $amount
    );

    error_log($f3->get('GIFT_BACKEND') . '/add-txn.php?' .
                     http_build_query($data));

    curl_setopt_array($curl, array(
      CURLOPT_URL => $f3->get('GIFT_BACKEND') . '/add-txn.php?' .
                     http_build_query($data),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    ));

    $response= curl_exec($curl);
    $err= curl_error($curl);

    curl_close($curl);

    if ($err) {
      $f3->error(500, "cURL Error #:" . $err);
    }

    // have to strip jsonp wrapping
    $response= substr($response, 1, -2);
    $data= json_decode($response);

    error_log($response);

    // turn soft errors into hard ones
    if ($data->error) {
      return $f3->error(500, $data->error);
    }

    $payment= new DB\SQL\Mapper($db, 'sale_payment');
    $payment->sale_id= $sale->id;
    $payment->method= 'gift';
    $payment->amount= -$data->amount;
    $payment->data= json_encode(array(
      'card' => $f3->get('REQUEST.card'),
    ));
    $payment->save();

    self::capture_sales_tax($f3, $sale);

    $sale->status= 'paid';
    $sale->save();

    /*
    $headers= array();
    $headers[]= "From: " . $f3->get('CONTACT');
    $headers[]= "Reply-To: " . $f3->get('REQUEST.email');

    @mail($f3->get('CONTACT'),
          "Sale: Gift Card",
          Template::instance()->render('email-gift-card-sale.txt',
                                       'text/plain'),
          implode("\r\n", $headers));
    */

    if ($f3->get('AJAX')) {
      echo json_encode(array('message' => 'Success!'));
    } else {
      $f3->reroute('thanks');
    }
  }

  function shipstation_auth($f3, $args) {
    $user= $_SERVER['PHP_AUTH_USER'];
    $password= $_SERVER['PHP_AUTH_PW'];

    if ($user != $f3->get("SHIPSTATION_USER") ||
        $password != $f3->get("SHIPSTATION_PASSWORD")) {
      $f3->error(403);
    }
  }

  function shipstation_get($f3, $args) {
    self::shipstation_auth($f3, $args);
    
    $action= $f3->get('REQUEST.action');
    $start_date= $f3->get('REQUEST.start_date');
    $end_date= $f3->get('REQUEST.end_date');
    $page= $f3->get('REQUEST.page');

    if ($action != 'export') {
      $f3->error(500, "I don't know how to do that.");
    }

    $db= $f3->get('DBH');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->subtotal= '(SELECT SUM(quantity *
                                  sale_price(retail_price,
                                             discount_type,
                                             discount))
                      FROM sale_item WHERE sale_id = sale.id)';
    $sale->tax= 'CAST(
                   ROUND_TO_EVEN(shipping_tax +
                                 (SELECT SUM(tax)
                                    FROM sale_item WHERE sale_id = sale.id),
                                 2)
                   AS DECIMAL(9,2))';
    $sale->total= 'CAST(
                     ROUND_TO_EVEN(shipping + shipping_tax +
                                   (SELECT SUM(quantity *
                                               sale_price(retail_price,
                                                          discount_type,
                                                          discount)
                                               + tax)
                                      FROM sale_item WHERE sale_id = sale.id),
                                   2)
                     AS DECIMAL(9,2))';
    $sale->paid= '(SELECT SUM(amount)
                     FROM sale_payment
                    WHERE sale_id = sale.id)';
    $sales= $sale->find(array('status != "new" AND modified BETWEEN ? AND ?',
                              (new \Datetime($start_date))->format("Y-m-d H:i:s"),
                              (new \Datetime($end_date))->format("Y-m-d H:i:s")),
                        array('order' => 'id'));

    echo '<?xml version="1.0" encoding="utf-8"?>', "\n";
    echo "<Orders>\n";
    foreach ($sales as $order) {
      echo "<Order>\n";
      echo " <OrderID>", $order->id, "</OrderID>\n";
      echo " <OrderNumber>", sprintf('%07d', $order->id), "</OrderNumber>\n";
      echo " <OrderDate>",
           (new \Datetime($order->created))->format("m/d/Y H:i"),
           "</OrderDate>\n";
      echo " <OrderStatus><![CDATA[", $order->status, "]]></OrderStatus>\n";
      echo " <LastModified>",
           (new \Datetime($order->modified))->format("m/d/Y H:i"),
           "</LastModified>\n";
      echo " <OrderTotal>", $order->total, "</OrderTotal>\n";
      echo " <TaxAmount>", $order->tax, "</TaxAmount>\n";
      echo " <ShippingAmount>", $order->shipping, "</ShippingAmount>\n";
      
      /* customer */
      echo " <Customer>\n";
      echo "  <CustomerCode><![CDATA[", $order->email, "]]></CustomerCode>\n";
      echo "  <BillTo>\n";
      echo "   <Name><![CDATA[", $order->name, "]]></Name>\n";
      echo "   <Email><![CDATA[", $order->email, "]]></Email>\n";
      echo "  </BillTo>\n";
      /* shipping address */
      $shipping_address= new DB\SQL\Mapper($db, 'sale_address');
      $shipping_address->load(array('id = ?',
                                    $order->shipping_address_id ? 
                                    $order->shipping_address_id :
                                    $order->billing_address_id));
      echo "  <ShipTo>\n";
      echo "   <Name><![CDATA[", $shipping_address->name, "]]></Name>\n";
      echo "   <Address1><![CDATA[",
           $shipping_address->address1,
           "]]></Address1>\n";
      echo "   <Address2><![CDATA[",
           $shipping_address->address2,
           "]]></Address2>\n";
      echo "   <City><![CDATA[", $shipping_address->city, "]]></City>\n";
      echo "   <State><![CDATA[", $shipping_address->state, "]]></State>\n";
      $zip= $shipping_address->zip5 . ($shipping_address->zip4 ?
                                       "-" . $shipping_address->zip4 :
                                       "");
      echo "   <PostalCode><![CDATA[", $zip, "]]></PostalCode>\n";
      echo "   <Country><![CDATA[US]]></Country>\n";
      //echo "   <Phone><![CDATA[", $shipping_address->email, "]]></Phone>\n";
      echo "  </ShipTo>\n";
      /* shipping address */
      echo " </Customer>\n";

      /* items */
      echo " <Items>\n";

      $item= new DB\SQL\Mapper($db, 'sale_item');
      $item->code= "(SELECT code FROM item WHERE id = item_id)";
      $item->name= "(SELECT name FROM item WHERE id = item_id)";
      $item->sale_price= "sale_price(retail_price, discount_type, discount)";

      $items= $item->find(array('sale_id = ?', $order->id),
                           array('order' => 'id'));

      foreach ($items as $i) {
        echo "  <Item>\n";
        echo "   <LineItemID>", $i->id, "</LineItemID>\n";
        echo "   <SKU><![CDATA[", $i->code, "]]></SKU>\n";
        echo "   <Name><![CDATA[", $i->name, "]]></Name>\n";
        echo "   <Quantity>", $i->quantity, "</Quantity>\n";
        echo "   <UnitPrice>", $i->sale_price, "</UnitPrice>\n";
        echo "  </Item>\n";
      }
      echo " </Items>\n";
      echo "</Order>\n";
    }
    echo "</Orders>\n";
  }

  function shipstation_post($f3, $args) {
    self::shipstation_auth($f3, $args);

    $action= $f3->get('REQUEST.action');
    $order_number= $f3->get('REQUEST.order_number');
    $carrier= $f3->get('REQUEST.carrier');
    $service= $f3->get('REQUEST.service');
    $tracking_number= $f3->get('REQUEST.tracking_number');

    if ($action != 'shipnotify') {
      $f3->error(500, "I don't know how to do that.");
    }

    $db= $f3->get('DBH');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->load(array('id = ?', $order_number))
      or $f3->error(404);

    $shipment= new DB\SQL\Mapper($db, 'sale_shipment');
    $shipment->sale_id= $sale->id;
    $shipment->carrier= $carrier;
    $shipment->service= $service;
    $shipment->tracking_number= $tracking_number;
    // XXX parse body for this data
    $shipment->created= date("Y-m-d H:i:s");
    $shipment->ship_date= date("Y-m-d");
    $shipment->shipping_cost= 0.00;
    $shipment->data= file_get_contents('php://input');

    $shipment->save();

    echo "Success!";
  }
}
