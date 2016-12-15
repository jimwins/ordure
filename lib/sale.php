<?php

class Sale {

  static function addRoutes($f3) {
    $f3->route("GET|HEAD /sale/new", 'Sale->create');
    $f3->route("GET|HEAD /sale/@sale", 'Sale->display');
    $f3->route("GET|HEAD /sale/@sale/json", 'Sale->json');
    $f3->route("POST /sale/@sale/add-item [ajax]", 'Sale->add_item');
    $f3->route("POST /sale/@sale/calculate-sales-tax [ajax]",
               'Sale->calculate_sales_tax');
    $f3->route("POST /sale/@sale/remove-item [ajax]", 'Sale->remove_item');
    $f3->route("POST /sale/@sale/set-address [ajax]", 'Sale->set_address');
    $f3->route("POST /sale/@sale/verify-address [ajax]",
               'Sale->verify_address');
    $f3->route("POST /sale/@sale/set-person [ajax]", 'Sale->set_person');
  }

  function create($f3, $args) {
    $db= $f3->get('DBH');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->person_id= 0;
    // Could use real UUID() but this is shorter. Hardcoded '1' could be
    // replaced with a server-id to further avoid collisions
    $sale->uuid= sprintf("%08x%02x%s", time(), 1, bin2hex(random_bytes(8)));

    $sale->insert();

    $f3->reroute("./" . $sale->uuid);
  }

  function load($f3, $sale_id) {
    $db= $f3->get('DBH');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->subtotal= '(SELECT SUM(quantity *
                                  sale_price(retail_price,
                                             discount_type,
                                             discount))
                      FROM sale_item WHERE sale_id = sale.id)';
    $sale->shipping= 0.00;
    $sale->tax= '(SELECT SUM(quantity * tax)
                    FROM sale_item WHERE sale_id = sale.id)';
    $sale->total= '(SELECT SUM(quantity *
                               (sale_price(retail_price,
                                           discount_type,
                                           discount) +
                                tax))
                      FROM sale_item WHERE sale_id = sale.id)';
    $sale->load(array('id = ?', $sale_id))
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
    $shipping_address->copyTo('shipping_address');

    $item= new DB\SQL\Mapper($db, 'sale_item');
    $item->code= "(SELECT code FROM item WHERE id = item_id)";
    $item->name= "(SELECT name FROM item WHERE id = item_id)";
    $item->sale_price= "sale_price(retail_price, discount_type, discount)";
    $items= $item->find(array('sale_id = ?', $sale->id),
                         // XXX force shipping items to end?
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
      $payments_out[]= $i->cast();
    }
    $f3->set('payments', $payments_out);
  }

  function display($f3, $args) {
    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->load(array('uuid = ?', $sale_uuid))
      or $f3->error(404);

    $this->load($f3, $sale->id);

    echo Template::instance()->render('sale.html');
  }

  function add_item($f3, $args) {
    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');
    $item_code= $f3->get('REQUEST.item');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->load(array('uuid = ?', $sale_uuid))
      or $f3->error(404);

    $item= new DB\SQL\Mapper($db, 'item');
    $item->retail_price= "IFNULL((SELECT retail_price FROM scat_item WHERE scat_item.code = item.code), retail_price)";
    $item->discount_type= "(SELECT discount_type FROM scat_item WHERE scat_item.code = item.code)";
    $item->discount= "(SELECT discount FROM scat_item WHERE scat_item.code = item.code)";
    $item->load(array('code = ?', $item_code))
      or $f3->error(404);

    $line= new DB\SQL\Mapper($db, 'sale_item');
    $line->sale_id= $sale->id;
    $line->item_id= $item->id;
    $line->quantity= 1;
    $line->retail_price= $item->retail_price;
    $line->discount_type= $item->discount_type;
    $line->discount= $item->discount;
    $line->discount_manual= 0;
    $line->tic= $item->tic;
    $line->tax= 0.00;

    $line->insert();

    return $this->json($f3, $args);
  }

  function remove_item($f3, $args) {
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

    return $this->json($f3, $args);
  }

  function set_address($f3, $args) {
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
    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->load(array('uuid = ?', $sale_uuid))
      or $f3->error(404);

    $person= new DB\SQL\Mapper($db, 'person');
    if (($person_id= $f3->get('REQUEST.id'))) {
      $person->load(array('id = ?', $person_id))
        or $f3->error(404);
    } else {
      $person->load(array('email = ?', $f3->get('REQUEST.email')));
    }
    if ($person->dry()) {
      $person->role= 'customer';
    }
    
    $person->name= $f3->get('REQUEST.name');
    $person->email= $f3->get('REQUEST.email');
    $person->save();

    $sale->person_id= $person->id;
    $sale->save();

    return $this->json($f3, $args);
  }

  function calculate_sales_tax($f3, $args) {
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
                         // XXX force shipping items to end?
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

    // XXX add fake shipping cartItem

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
      // XXX blah
      echo "cURL Error #:" . $err;
      return;
    }

    error_log($response);
    $data= json_decode($response);

    foreach ($data->CartItemsResponse as $response) {
      $item->load(array('id = ?', $response->CartItemIndex))
        or $f3->error(404);
      $item->tax= $response->TaxAmount;
      $item->save();
    }

    return $this->json($f3, $args);
  }

  function json($f3, $args) {
    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->load(array('uuid = ?', $sale_uuid))
      or $f3->error(404);

    $this->load($f3, $sale->id);

    echo json_encode(array( 'sale' => $f3->get('sale'),
                            'person' => $f3->get('person'),
                            'billing_address' => $f3->get('billing_address'),
                            'shipping_address' => $f3->get('shipping_address'),
                            'items' => $f3->get('items'),
                            'payments' => $f3->get('payments')));
  }
}
