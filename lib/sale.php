<?php

use Respect\Validation\Validator as v;

$f3->set('amount', function ($d) {
  return ($d < 0 ? '(' : '') . '$' . sprintf("%.2f", abs($d)) . ($d < 0 ? ')' : '');
});

function amount($d) {
  return ($d < 0 ? '(' : '') . '$' . sprintf("%.2f", abs($d)) . ($d < 0 ? ')' : '');
}

class Sale {

  static function addRoutes($f3) {
    $f3->route("GET|HEAD /sale/new", 'Sale->new_sale');
    $f3->route("GET|HEAD /sale/list", 'Sale->showList');
    $f3->route("GET|HEAD /sale/list-items", 'Sale->showItemsList');
    $f3->route("GET|HEAD /sale/@sale", 'Sale->dispatch');
    $f3->route("GET|HEAD /sale/@sale/edit", 'Sale->edit');
    $f3->route("GET|HEAD /sale/@sale/pay", 'Sale->pay');
    $f3->route("GET|HEAD /sale/@sale/checkout", 'Sale->pay');
    $f3->route("GET|HEAD /sale/@sale/paid", 'Sale->status');
    $f3->route("GET|HEAD /sale/@sale/thanks", 'Sale->status');
    $f3->route("GET|HEAD /sale/@sale/status", 'Sale->status');
    $f3->route("GET|HEAD /sale/@sale/json", 'Sale->fetch_json');
    $f3->route("GET|HEAD /sale/@sale/test", 'Sale->send_order_test');
    $f3->route("GET|HEAD /sale/@sale/clone", 'Sale->make_clone');
    $f3->route("POST /sale/@sale/add-item [ajax]", 'Sale->add_item');
    $f3->route("POST /sale/@sale/calculate-sales-tax [ajax]",
               'Sale->calculate_sales_tax');
    $f3->route("POST /sale/@sale/add-exemption [ajax]",
               'Sale->add_exemption');
    $f3->route("POST /sale/@sale/get-giftcard-balance [ajax]",
               'Sale->get_giftcard_balance');
    $f3->route("POST /sale/@sale/process-giftcard-payment",
               'Sale->process_giftcard_payment');
    $f3->route("POST /sale/@sale/process-creditcard-payment",
               'Sale->process_creditcard_payment');
    $f3->route("GET /sale/@sale/get-paypal-order",
               'Sale->get_paypal_order');
    $f3->route("POST /sale/@sale/process-paypal-payment",
               'Sale->process_paypal_payment');
    $f3->route("POST /sale/@sale/process-other-payment [ajax]",
               'Sale->process_other_payment');
    $f3->route("POST /sale/@sale/remove-item [ajax]", 'Sale->remove_item');
    $f3->route("POST /sale/@sale/update-item [ajax]", 'Sale->update_item');
    $f3->route("POST /sale/@sale/set-address", 'Sale->set_address');
    $f3->route("POST /sale/@sale/remove-address [ajax]",
               'Sale->remove_address');
    $f3->route("POST /sale/@sale/set-in-store-pickup [ajax]",
               'Sale->set_in_store_pickup');
    $f3->route("POST /sale/@sale/set-shipping [ajax]", 'Sale->set_shipping');
    $f3->route("POST /sale/@sale/ship-to-billing [ajax]",
               'Sale->ship_to_billing_address');
    $f3->route("POST /sale/@sale/bill-to-shipping",
               'Sale->bill_to_shipping_address');
    $f3->route("POST /sale/@sale/set-person [ajax]", 'Sale->set_person');
    $f3->route("POST /sale/@sale/set-status [ajax]", 'Sale->set_status');
    $f3->route("POST /sale/@sale/verify-address [ajax]",
               'Sale->verify_address');
    $f3->route("POST /sale/@sale/confirm-order [ajax]", 'Sale->confirm_order');
    $f3->route("POST /sale/@sale/send-note [ajax]", 'Sale->send_note');

    $f3->route("GET|HEAD /shipstation", 'Sale->shipstation_get');
    $f3->route("POST /shipstation", 'Sale->shipstation_post');

    if ($f3->get('FEATURE_cart')) {
      $f3->route("GET|HEAD /cart", 'Sale->cart');
      $f3->route("GET|HEAD /cart/checkout", 'Sale->cart_checkout');
      $f3->route("POST /cart/add-item", 'Sale->add_item');
      // not very REST of us
      $f3->route("GET /cart/remove-item", 'Sale->remove_item');
      $f3->route("POST /cart/update", 'Sale->update_items');
      $f3->route("POST /cart/update-person", 'Sale->set_person');
      $f3->route("POST /cart/calculate-shipping", 'Sale->calculate_shipping');
      $f3->route("POST /cart/change-shipping-option",
                 'Sale->change_shipping_option');
      $f3->route("POST /cart/set-address", 'Sale->set_address');
      $f3->route("POST /cart/set-in-store-pickup", 'Sale->set_in_store_pickup');
      $f3->route("POST /cart/ship-to-billing", 'Sale->ship_to_billing_address');
      $f3->route("POST /cart/place-order", 'Sale->place_order');
      $f3->route("POST /cart/amz-get-details", 'Sale->amz_get_details');
      $f3->route("POST /cart/amz-process-order", 'Sale->amz_process_order');
      $f3->route("GET|POST /cart/retrieve", 'Sale->retrieve_cart');
      $f3->route("GET /cart/combine-carts", 'Sale->combine_carts');
      $f3->route("GET /cart/forget", 'Sale->forget_cart_and_redir');
    }
  }

  function create($f3, $status= 'new') {
    $db= $f3->get('DBH');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->person_id= 0;
    $sale->status= $status;
    // Could use real UUID() but this is shorter. Hardcoded '1' could be
    // replaced with a server-id to further avoid collisions
    $sale->uuid= sprintf("%08x%02x%s", time(), 1, bin2hex(random_bytes(8)));

    $sale->insert();

    return $sale;
  }

  function new_sale($f3, $args) {
    if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
      $f3->error(403);

    $sale= $this->create($f3);

    $f3->reroute("/sale/" . $sale->uuid);
  }

  function showList($f3, $args) {
    if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER')) {
      if ($f3->get('UPLOAD_KEY') != $_REQUEST['key']) {
        $f3->error(403);
      }
    }

    $db= $f3->get('DBH');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->tax= 'CAST(ROUND(shipping_tax, 2) +
                      (SELECT SUM(ROUND(tax,2))
                         FROM sale_item WHERE sale_id = sale.id)
                   AS DECIMAL(9,2))';
    $sale->total= 'CAST(shipping + ROUND(shipping_tax, 2) +
                        (SELECT SUM(quantity * sale_price(retail_price,
                                                          discount_type,
                                                          discount)
                                    + ROUND(tax, 2))
                           FROM sale_item WHERE sale_id = sale.id)
                     AS DECIMAL(9,2))';
    $sale->paid= '(SELECT SUM(amount)
                     FROM sale_payment
                    WHERE sale_id = sale.id)';

    $which= ($f3->get('REQUEST.all') ?
             'status != "cancelled"' :
             ($f3->get('REQUEST.carts') ?
              'status = "cart"' :
              'status != "cancelled" AND
               status != "shipped" AND
               status != "cart"'));
    $status= $f3->get('REQUEST.status');
    if ($status) {
      $status= addslashes($status);
      $which= "status = '$status'";
    }

    if ($f3->get('REQUEST.yesterday')) {
      $which.= " AND DATE(created) = DATE(NOW() - INTERVAL 1 DAY)";
    }

    $f3->set('which', ($f3->get('REQUEST.all') ?
                       'all' :
                       ($f3->get('REQUEST.carts') ?
                        'carts' : 'default')));

    $sales= $sale->find($which,
                        array('order' => 'id'));

    $sales_out= array();
    foreach ($sales as $i) {
      $sales_out[]= $i->cast();
    }
    $f3->set('sales', $sales_out);

    if ($f3->get('REQUEST.json')) {
      header("Content-type: application/json");
      echo json_encode($sales_out, JSON_PRETTY_PRINT);
      return;
    }

    echo Template::instance()->render('sale-list.html');
  }

  function showItemsList($f3, $args) {
    if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER')) {
      if ($f3->get('UPLOAD_KEY') != $_REQUEST['key']) {
        $f3->error(403);
      }
    }

    $db= $f3->get('DBH');

    $f3->set('which', 'items');

    $days= (int)$f3->get('REQUEST.days');
    if (!$days) $days= 2;

    $q= "SELECT item.code, item.name, item.width, item.weight,
                (SELECT stock FROM scat_item WHERE scat_item.code = item.code)
                  AS stock,
                SUM(quantity) total
           FROM sale_item
           JOIN sale ON sale_item.sale_id = sale.id
           JOIN item ON sale_item.item_id = item.id
          WHERE sale.modified BETWEEN NOW() - INTERVAL $days DAY AND NOW()
            AND sale.status = 'cart'
          GROUP BY item.id
          ORDER BY code";

    $items= $db->exec($q);

    if ($f3->get('REQUEST.json')) {
      header("Content-type: application/json");
      echo json_encode($items, JSON_PRETTY_PRINT);
      return;
    }

    $f3->set('items', $items);

    echo Template::instance()->render('sale-list-items.html');
  }

  function load($f3, $sale_id, $type= 'id') {
    $db= $f3->get('DBH');
    $f3->get('log')->info("Loading $sale_id by $type.");

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->subtotal= '(SELECT SUM(quantity *
                                  sale_price(retail_price,
                                             discount_type,
                                             discount))
                      FROM sale_item WHERE sale_id = sale.id)';
    $sale->tax= 'CAST(ROUND(shipping_tax, 2) +
                      (SELECT SUM(ROUND(tax,2))
                         FROM sale_item WHERE sale_id = sale.id)
                   AS DECIMAL(9,2))';
    $sale->total= 'CAST(shipping + ROUND(shipping_tax, 2) +
                        (SELECT SUM(quantity * sale_price(retail_price,
                                                          discount_type,
                                                          discount)
                                    + ROUND(tax, 2))
                           FROM sale_item WHERE sale_id = sale.id)
                     AS DECIMAL(9,2))';
    $sale->paid= '(SELECT SUM(amount)
                     FROM sale_payment
                    WHERE sale_id = sale.id)';
    $sale->load(array($type . ' = ?', $sale_id))
      or $f3->error(404);
    $sale->copyTo('sale');

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
    $item->name= "IFNULL(override_name,
                         (SELECT name FROM item WHERE id = item_id))";
    $item->sale_price= "sale_price(retail_price, discount_type, discount)";

    if (self::can_pickup($f3) || self::can_ship($f3)) {
      $item->purchase_quantity= "IFNULL((SELECT scat_item.purchase_quantity FROM scat_item WHERE scat_item.code = (SELECT code FROM item WHERE id = item_id)), (SELECT purchase_quantity FROM item WHERE id = item_id))";
    } else {
      $item->purchase_quantity= "IFNULL((SELECT scat_item.is_dropshippable FROM scat_item WHERE scat_item.code = (SELECT code FROM item WHERE id = item_id)), (SELECT purchase_quantity FROM item WHERE id = item_id))";
    }
    $item->is_dropshippable= "(SELECT scat_item.is_dropshippable FROM scat_item WHERE scat_item.code = (SELECT code FROM item WHERE id = item_id))";

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
    $item->product_id= "(SELECT product FROM item WHERE id = item_id)";
    $item->product_name= "(SELECT product.name FROM item JOIN product ON product.id = item.product WHERE item.id = item_id)";
    $item->brand_name= "(SELECT brand.name FROM item JOIN product ON product.id = item.product JOIN brand ON product.brand = brand.id WHERE item.id = item_id)";
    $item->weight= "(SELECT weight FROM item WHERE item_id = item.id)";
    $item->width= "(SELECT width FROM item WHERE item_id = item.id)";
    $item->stock= "(SELECT stock FROM item JOIN scat_item WHERE item_id = item.id AND scat_item.code = item.code)";

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

    $shipments= new DB\SQL\Mapper($db, 'sale_shipment');
    $shipments= $shipments->find(array('sale_id = ?', $sale->id),
                                 array('order' => 'id'));
    $shipments_out= array();
    foreach ($shipments as $i) {
      $shipments_out[]= $i->cast();
    }
    $f3->set('shipments', $shipments_out);

    list($shipping_status, $shipping_rate, $special_conditions)=
      $this->get_shipping_estimate($f3, $sale, $items);
    $f3->set('shipping_status', $shipping_status);
    $f3->set('shipping_rate', $shipping_rate);
    $f3->set('special_conditions', $special_conditions);

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
    case 'cart':
      return $f3->reroute('/sale/' . $sale->uuid . '/edit');
    case 'unpaid':
      return $f3->reroute('/sale/' . $sale->uuid . '/pay');
    case 'paid':
    case 'review':
    case 'processing':
    case 'shipped':
    case 'cancelled':
    case 'onhold':
      return $f3->reroute('/sale/' . $sale->uuid . '/status');
    default:
      $f3->error(404);
    }
  }

  function edit($f3, $args) {
    if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
      $f3->error(403);
    $this->load($f3, $f3->get('PARAMS.sale'), 'uuid');
    echo Template::instance()->render('sale-edit.html');
  }

  function make_clone($f3, $args) {
    if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
      $f3->error(403);

    $sale= $this->load($f3, $f3->get('PARAMS.sale'), 'uuid');

    $new_sale= $this->create($f3);

    $new_sale->name= $sale->name;
    $new_sale->email= $sale->email;
    $new_sale->billing_address_id= $sale->billing_address_id;
    $new_sale->shipping_address_id= $sale->shipping_address_id;
    $new_sale->save();

    $f3->reroute("/sale/{$new_sale->uuid}/edit");
  }

  // returns [ shipping_status, shipping_rate, special_conditions ]
  function get_shipping_estimate($f3, $sale, $items) {
    $db= $f3->get('DBH');
    $status= $rate= $special= [];
    $weight= 0.0;

    if (($minimum= $f3->get("SALE_MINIMUM")) && $minimum > $sale->total) {
      $status['below_minimum']++;
    }

    foreach ($items as $sale_item) {
      // Check stock
      $scat_item= new DB\SQL\Mapper($db, 'scat_item');
      $scat_item->load(array('code = ?', $sale_item['code']));
      // no details? must be special order
      if ($scat_item->dry()) {
        $status['special']++;
      }
      // no stock and not stocked? special order.
      if (!$scat_item->stock && !$scat_item->minimum_quantity) {
        $status['special']++;
      }
      // more in order than in stock? stock is limited.
      if ($scat_item->stock < $sale_item['quantity']) {
        $status['stock_limited']++;
      }

      // Check item details
      $item= new DB\SQL\Mapper($db, 'item');
      $item->load(array('code = ?', $sale_item['code']));
      // hazmat? (extra charge)
      if ($item->hazmat && $sale->shipping_address_id != 1) {
        $special['hazmat']++;
      }
      // unknown dimensions?
      if ($item->weight == 0 || $item->length == 0) {
        $rate['unknown']++;
      }
      $weight+= $item->weight;
      // not small?
      $size= [$item->height, $item->length, $item->width ];
      sort($size, SORT_NUMERIC);
      if ($size[0] > 8 || $size[1] > 19 || $size[2] > 25) {
        $rate['large']++;
      } else if ($size[0] > 8 || $size[1] > 15 || $size[2] > 18) {
        $rate['medium']++;
      }
      // truck?
      if ($item->oversized) {
        $rate['truck']++;
      }
    }

    // if overweight for medium, might be able to ship as large
    if ($medium && $weight > 20)
      $rate['large']++;
    // if overweight for large, will have to get price quote
    if ($large && $weight > 30)
      $rate['unknown']++;

    return [
      ($status['below_minimum'] ? 'below_minimum' :
        ($status['special'] ? 'special_order' :
          ($status['stock_limited'] ? 'stock_limited' :
            'immediate'))),
      $rate['unknown'] ? 'unknown' :
        ($weight < 0 ? 'free' :
          ($rate['truck'] ? 'truck' :
            ($rate['large'] ? 'large' :
              ($rate['medium'] ? 'medium' :
                'small')))),
      array_keys($special)
    ];
  }

  function cart($f3, $args) {
    $uuid= $f3->get('REQUEST.uuid');
    if (empty($uuid)) {
      $uuid= $f3->get('COOKIE.cartID');
    }

    if ($uuid) {
      $sale= $this->load($f3, $uuid, 'uuid');

      if ($sale->status != 'cart') {
        $this->forget_cart($f3, $args);
        $f3->reroute($f3->get('BASE') . '/cart');
      }

      $this->remember_cart($f3, $uuid);

      $domain= ($_SERVER['HTTP_HOST'] != 'localhost' ?
                $_SERVER['HTTP_HOST'] : false);
      SetCookie('cartDetails',
                json_encode(array('items' => count($f3->get('items')),
                                  'total' => $sale->total)),
                0 /* session cookie */,
                '/', $domain, true, false); // JavaScript accessible

    }
    echo Template::instance()->render('sale-cart.html');
  }

  function cart_checkout($f3, $args) {
    $uuid= $f3->get('COOKIE.cartID');

    /* XXX check $f3->get('PARAMS.uuid') to detect cookie failure? */

    if ($uuid) {
      $sale= $this->load($f3, $uuid, 'uuid');

      $domain= ($_SERVER['HTTP_HOST'] != 'localhost' ?
                $_SERVER['HTTP_HOST'] : false);
      SetCookie('cartDetails',
                json_encode(array('items' => count($f3->get('items')),
                                  'total' => $sale->total)),
                0 /* session cookie */,
                '/', $domain, true, false); // JavaScript accessible

      $stages= [ 'login', 'shipping', 'payment', 'amz-select' ];
      $stage= $f3->get('REQUEST.stage');

      if ($f3->get('REQUEST.access_token')) {
        $stage= 'amz-select';
      }

      if (!in_array($stage, $stages)) {
        $shipping_rate= $f3->get('shipping_rate');
        if ($sale->shipping_address_id &&
            in_array($shipping_rate, [ 'truck', 'unknown' ]))
        {
          $stage= 'review';
        }
        elseif ($sale->shipping_address_id) {
          $stage= 'payment';
        }
        elseif ($sale->person_id || ($sale->email && $sale->name)) {
          $stage= 'shipping';
        }
        else {
          $stage= 'login';
        }
      }

      $f3->set('stage', $stage);

      echo Template::instance()->render('sale-checkout.html');
    } else {
      $f3->reroute($f3->get('BASE') . $f3->get('CATALOG'));
    }
  }

  function get_amz_client($f3) {
    $config= [
      'merchant_id' => $f3->get('AMZ_MERCHANT_ID'),
      'access_key' => $f3->get('AMZ_ACCESS_KEY'),
      'secret_key' => $f3->get('AMZ_SECRET_KEY'),
      'client_id' => $f3->get('AMZ_CLIENT_ID'),
      'region' => $f3->get('AMZ_REGION'),
      'currency_code' => $f3->get('AMZ_CURRENCY_CODE'),
      'sandbox' => (bool)$f3->get('DEBUG'),
    ];

    $client= new \AmazonPay\Client($config);
    if ($f3->get('DEBUG')) {
      $client->setLogger($f3->get('log'));
    }
    return $client;
  }

  function amz_get_details($f3, $args) {
    $uuid= $f3->get('COOKIE.cartID');

    /* XXX check $f3->get('PARAMS.uuid') to detect cookie failure? */

    if (!$uuid) {
      $f3->error(500);
    }

    $db= $f3->get('DBH');

    $client= $this->get_amz_client($f3);

    $sale= $this->load($f3, $uuid, 'uuid');

    $order_reference_id= $f3->get('REQUEST.order_reference_id');
    if ($order_reference_id) {
      $sale->amz_order_reference_id=
        $f3->get('REQUEST.order_reference_id');
    }

    if (!$sale->amz_order_reference_id) {
      // We don't have order_reference_id yet, so ignore this call
      return $this->json($f3, $args, $uuid);
    }

    $params= [
      'amount' => $sale->total,
      'currency_code' => $config['currency_code'],
      'seller_order_id' => $sale->id,
      'store_name' => 'Raw Materials Art Supplies',
      'seller_note' => 'Your order of art supplies',
      'amazon_order_reference_id' => $sale->amz_order_reference_id,
    ];

    $res= $client->setOrderReferenceDetails($params);
    if ($client->success) {
      $params['access_token']= $f3->get('REQUEST.access_token');
      $res= $client->getOrderReferenceDetails($params);
      $details= $res->toArray();

      /* Save details */
      $sale->name= $details['GetOrderReferenceDetailsResult']
                           ['OrderReferenceDetails']
                           ['Buyer']['Name'];
      $sale->email= $details['GetOrderReferenceDetailsResult']
                            ['OrderReferenceDetails']
                            ['Buyer']['Email'];

      $amz_address= $details['GetOrderReferenceDetailsResult']
                            ['OrderReferenceDetails']
                            ['Destination']
                            ['PhysicalDestination'];

      /* We always just create new address records */
      $address= new DB\SQL\Mapper($db, 'sale_address');

      $address->name= $amz_address['Name'];
      $address->address1= $amz_address['AddressLine1'];
      $address->address2= $amz_address['AddressLine2'];
      $address->city= $amz_address['City'];
      $address->state= $amz_address['StateOrRegion'];
      $zip5= $amz_address['PostalCode'];
      if (preg_match('/^(\d{5})-(\d{4})$/', $zip5, $m)) {
        $zip5= $m[1];
        $address->zip4= $m[2];
      }
      $address->zip5= $zip5;
      $address->phone= $amz_address['Phone'];
      $address->verified= 0;

      $address->save();

      $sale->shipping_address_id= $address->id;
    } else {
      $details= $res->toArray();

      $f3->get('log')->debug(json_encode($details, JSON_PRETTY_PRINT));

      $f3->error(500, "Sorry, an unexpected error occured.");
    }

    $sale->save();

    $this->update_shipping_and_tax($f3, $sale);

    $f3->set('PARAMS.sale', $uuid);
    return $this->json($f3, $args);
  }

  function amz_process_order($f3, $args) {
    $uuid= $f3->get('COOKIE.cartID');

    if (!$uuid) {
      $f3->error(404);
    }

    $db= $f3->get('DBH');

    $client= $this->get_amz_client($f3);

    $sale= $this->load($f3, $uuid, 'uuid');
    if ($sale->status != 'cart')
      $f3->error(500, "This is not an active shopping cart.");

    $params= [
      'amazon_order_reference_id' => $sale->amz_order_reference_id,
      'mws_auth_token' => null,
    ];

    $res= $client->confirmOrderReference($params);
    if ($client->success) {
      $params['authorization_amount']= $sale->total;
      $params['authorization_reference_id']= uniqid();
      $params['seller_authorization_note']=
        'Authorizing and capturing the payment';
      $params['transaction_timeout']= 0;

      $params['capture_now']= false;
      $params['soft_descriptor']= null;

      $res= $client->authorize($params);

      if ($client->success) {
        $details= $res->toArray();

        $f3->get('log')->debug(json_encode($details, JSON_PRETTY_PRINT));

        if ($details['AuthorizeResult']
                    ['AuthorizationDetails']
                    ['AuthorizationStatus']
                    ['State']
              != 'Open')
        {
          // XXX Send email to admin

          // XXX turn reason into friendlier text
          $code= $details['AuthorizeResult']
                         ['AuthorizationDetails']
                         ['AuthorizationStatus']
                         ['ReasonCode'];
          $reasons= [
            'TransactionTimedOut' =>
              "The transaction timed out. Click on the 'Cart' link at the top of the page to restart the payment process.",
            'InvalidPaymentMethod' => "There were problem with the payment method. Please try again, possibly with a different payment method.",
            'AmazonRejected' => "Amazon has rejected the transaction.",
            'ProcessingFailure' => "Amazon could not process the transaction because of an internal processing error.",
          ];
          $f3->error(500, $reasons[$code]);
        }

        $payment= new DB\SQL\Mapper($db, 'sale_payment');
        $payment->sale_id= $sale->id;
        $payment->method= 'amazon';
        $payment->amount=
          $details['AuthorizeResult']
                  ['AuthorizationDetails']
                  ['AuthorizationAmount']
                  ['Amount'];
        $payment->data= json_encode($details['AuthorizeResult']
                                            ['AuthorizationDetails']);
        $payment->save();

        self::capture_sales_tax($f3, $sale);

        $sale->status= 'paid';
        $sale->save();
      } else {
        $details= $res->toArray();

        $f3->get('log')->debug(json_encode($details, JSON_PRETTY_PRINT));

        $f3->error(500, $details['Error']['Message']);
      }
    } else {
      $details= $res->toArray();

      $f3->get('log')->debug(json_encode($details, JSON_PRETTY_PRINT));

      $f3->error(500, $details['Error']['Message']);
    }

    // save comment
    $comment= $f3->get('REQUEST.comment');

    $db= $f3->get('DBH');
    $note= new DB\SQL\Mapper($db, 'sale_note');
    $note->sale_id= $sale->id;
    $note->person_id= $sale->person_id;
    $note->content= $comment;
    $note->save();

    // reload
    $sale= $this->load($f3, $uuid, 'uuid');

    self::send_order_paid_email($f3);

    if ($f3->get('AJAX')) {
      return $this->json($f3, $args, $uuid);
    }

    $this->forget_cart($f3, $args);

    $f3->reroute("/sale/" . $sale->uuid);
  }

  function capture_payments($f3, $sale) {
    $db= $f3->get('DBH');

    $payment= new DB\SQL\Mapper($db, 'sale_payment');
    $payments= $payment->find(array('sale_id = ?', $sale->id));

    foreach ($payments as $pay) {
      if ($pay->method == 'amazon' && !$pay->captured) {
        $client= $this->get_amz_client($f3);

        $data= json_decode($pay->data);

        $params= [
          'amazon_authorization_id' => $data->AmazonAuthorizationId,
          'capture_reference_id' => uniqid(),
          'capture_amount' => $pay->amount,
          'seller_capture_note' => 'Thank you for your order!',
          'mws_auth_token' => null,
        ];

        $f3->get('log')->debug("Capturing: " . json_encode($params, JSON_PRETTY_PRINT));

        $res= $client->capture($params);

        if ($client->success) {
          $details= $res->toArray();

          /* Save details */
          $status= $details['CaptureResponse']
                           ['CaptureResult']
                           ['CaptureDetails']
                           ['CaptureStatus']
                           ['State'];

          if ($status == 'Completed') {
            $when= $details['CaptureResponse']
                           ['CaptureResult']
                           ['CaptureDetails']
                           ['CaptureStatus']
                           ['CreationTimestamp'];
            # XXX should convert to local timezone since that's how we roll
            $pay->captured= (new \Datetime($when))->format('Y-m-d H:i:s');

          }
          elseif ($status == 'Pending') {
            $f3->get('log')->warning("Payment {$pay->id} still pending.");
          }
          elseif ($status == 'Declined') {
            $f3->get('log')->error("Payment {$pay->id} declined!");
          }
          else {
            $f3->get('log')->error("Didn't understand Amazon response for {$pay->id}!");
          }

          $pay->save();
        } else {
          $f3->error(500, "Error processing Amazon payment.");
        }
      }
    }
  }

  function remember_cart($f3, $uuid) {
    $domain= ($_SERVER['HTTP_HOST'] != 'localhost' ?
              $_SERVER['HTTP_HOST'] : false);

    SetCookie('cartID', $uuid, null /* don't expire */,
              '/', $domain, true, true);
  }

  function forget_cart($f3, $args) {
    $domain= ($_SERVER['HTTP_HOST'] != 'localhost' ?
              $_SERVER['HTTP_HOST'] : false);
    SetCookie('cartID', "", (new \Datetime("-24 hours"))->format("U"),
              '/', $domain, true, true);
    SetCookie('cartDetails', "", (new \Datetime("-24 hours"))->format("U"),
              '/', $domain, true, false);
  }

  function forget_cart_and_redir($f3, $args) {
    $this->forget_cart($f3, $args);
    $f3->reroute('/cart');
  }

  function pay($f3, $args) {
    $uuid= $f3->get('PARAMS.sale');
    $sale= $this->load($f3, $uuid, 'uuid');

    if ($sale->status != 'unpaid') {
      $f3->reroute('/sale/' . $uuid);
    }

    $f3->set('action', 'pay');
    echo Template::instance()->render('sale-pay.html');
  }

  function status($f3, $args) {
    $uuid= $f3->get('PARAMS.sale');
    $this->load($f3, $uuid, 'uuid');
    if ($uuid == $f3->get('COOKIE.cartID')) {
      $this->forget_cart($f3, $args);
    }
    echo Template::instance()->render('sale-status.html');
  }

  function add_item($f3, $args) {
    $sale_uuid= $f3->get('PARAMS.sale');

    if ($sale_uuid) {
      if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
        $f3->error(403);
    } else {
      $sale_uuid= $f3->get('COOKIE.cartID');
      $person_id= \Auth::authenticated_user($f3);

      /* No cart yet? Create one. */
      if (!$sale_uuid) {
        $sale= $this->create($f3, 'cart');
        $sale_uuid= $sale->uuid;

        if ($person_id) {
          $sale->person_id= $person_id;
          $person= \Auth::authenticated_user_details($f3);
          $sale->name= $person['name'];
          $sale->email= $person['email'];
          $sale->save();
        }

        $this->remember_cart($f3, $sale_uuid);
      } else {
        $f3->get('log')->info("Loading cart from UUID '$sale_uuid'.");
      }
    }

    $db= $f3->get('DBH');

    $item_code= trim($f3->get('REQUEST.item'));

    $sale= $this->load($f3, $sale_uuid, 'uuid')
      or $f3->error(404);

    if (!in_array($sale->status, array('new','cart','review')))
      $f3->error(500);

    $item= new DB\SQL\Mapper($db, 'item');
    $item->nretail_price= "IFNULL((SELECT retail_price FROM scat_item WHERE scat_item.code = item.code), retail_price)";
    $item->discount_type= "(SELECT discount_type FROM scat_item WHERE scat_item.code = item.code)";
    $item->discount= "(SELECT discount FROM scat_item WHERE scat_item.code = item.code)";
    if (self::can_pickup($f3) || self::can_ship($f3)) {
      $item->npurchase_quantity= "IFNULL((SELECT scat_item.purchase_quantity FROM scat_item WHERE scat_item.code = item.code), purchase_quantity)";
    } else {
      $item->npurchase_quantity= "IFNULL((SELECT scat_item.is_dropshippable FROM scat_item WHERE scat_item.code = item.code), purchase_quantity)";
    }
    $item->load(array('code = ?', $item_code))
      or $f3->error(404);

    $line= new DB\SQL\Mapper($db, 'sale_item');

    $existing= $line->find([
      'sale_id = ? AND item_id = ?',
      $sale->id, $item->id
    ]);

    if ($existing) {
      $existing[0]->quantity+= $item->npurchase_quantity;
      $existing[0]->save();
    } else {
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
    }

    // reload sale so shipping gets recalculated correctly
    $sale= $this->load($f3, $sale_uuid, 'uuid');
    $this->update_shipping_and_tax($f3, $sale);

    if ($f3->get('AJAX')) {
      return $this->json($f3, $args);
    }

    $f3->reroute('/cart?uuid=' . $sale->uuid .
                 '&added=' . rawurlencode($item->code));
  }

  function remove_item($f3, $args) {
    $sale_uuid= $f3->get('PARAMS.sale');

    if ($sale_uuid) {
      if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
        $f3->error(403);
    } else {
      $sale_uuid= $f3->get('COOKIE.cartID');
    }

    $db= $f3->get('DBH');

    $sale= $this->load($f3, $sale_uuid, 'uuid')
      or $f3->error(404);

    $sale_item_id= $f3->get('REQUEST.item');

    error_log("Removing $sale_item_id from $sale_uuid\n");

    $line= new DB\SQL\Mapper($db, 'sale_item');
    $line->load(array('id = ?', $sale_item_id))
      or $f3->error(404);
    if ($line->sale_id != $sale->id) {
      $f3->error(500);
    }
    $line->erase();

    $this->update_shipping_and_tax($f3, $sale);

    if ($f3->get('AJAX')) {
      return $this->json($f3, $args);
    }

    $f3->reroute('/cart?uuid=' . $sale->uuid .
                 '&removed=1');
  }

  function update_item($f3, $args) {
    if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
      $f3->error(403);

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');
    $sale_item_id= $f3->get('REQUEST.item');

    $sale= $this->load($f3, $sale_uuid, 'uuid')
      or $f3->error(404);

    $line= new DB\SQL\Mapper($db, 'sale_item');
    $line->load(array('id = ?', $sale_item_id))
      or $f3->error(404);

    if ($f3->exists('REQUEST.quantity')) {
      $line->quantity= (int)$f3->get('REQUEST.quantity');
    }

    if ($f3->exists('REQUEST.override_name')) {
      $line->override_name= $f3->get('REQUEST.override_name');
    }

    if ($f3->exists('REQUEST.price')) {
      $price= $f3->get('REQUEST.price');

      if (preg_match('/^\d*(\/|%)$/', $price)) {
        $line->discount_type= "percentage";
        $line->discount= (float)$price;
        $line->discount_manual= 1;
      } elseif (preg_match('/^\$?(-?\d*\.?\d*)$/', $price, $m)) {
        if ($line->retail_price != 0.00) {
          $line->discount= (float)$price;
          $line->discount_type= "fixed";
          $line->discount_manual= 1;
        } else {
          $line->retail_price= (float)$price;
          $line->discount= NULL;
          $line->discount_type= NULL;
          $line->discount_manual= NULL;
        }
      } elseif ($price == '...') {
        $item= new DB\SQL\Mapper($db, 'item');
        $item->load(array('id = ?', $line->item_id));
        if (!$item) {
          $f3->error(500, "Can't find price");
        }
        $scat_item= new DB\SQL\Mapper($db, 'scat_item');
        $scat_item->load(array('code = ?', $item->code));
        if (!$scat_item) {
          $f3->error(500, "Can't find price");
        }
        $line->retail_price= $scat_item->retail_price;
        $line->discount= $scat_item->discount;
        $line->discount_type= $scat_item->discount_type;
        $line->discount_manual= NULL;
      } else {
        $f3->error(500, "Didn't understand price.");
      }
    }

    $line->save();

    $this->update_shipping_and_tax($f3, $sale);

    return $this->json($f3, $args);
  }

  function update_items($f3, $args) {
    $sale_uuid= $f3->get('PARAMS.sale');

    if ($sale_uuid) {
      if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
        $f3->error(403);
    } else {
      $sale_uuid= $f3->get('COOKIE.cartID');
    }

    if (!$sale_uuid)
      $f3->error(404);

    $db= $f3->get('DBH');

    $sale= $this->load($f3, $sale_uuid, 'uuid');

    if ($sale->status != 'new' && $sale->status != 'cart')
      $f3->error(500);

    foreach ($f3->get('REQUEST.qty') as $id => $val) {
      $line= new DB\SQL\Mapper($db, 'sale_item');
      $line->load(array('id = ?', $id))
        or $f3->error(404);

      $item= new DB\SQL\Mapper($db, 'item');
      $item->is_dropshippable= "(SELECT scat_item.is_dropshippable FROM scat_item WHERE scat_item.code = item.code)";
      $item->load(array('id = ?', $line->item_id))
        or $f3->error(404);

      if (!v::numeric()->min(0, true)->validate($val)) {
        // XXX really should provide feedback
        continue;
      }

      if (self::can_pickup($f3) || self::can_ship($f3)) {
        $purchase_quantity= $item->purchase_quantity;
      } else {
        $purchase_quantity= $item->is_dropshippable;
      }

      if ($val > 0 && ($val % $purchase_quantity) != 0) {
        // XXX really should provide feedback
        continue;
      }

      if ((int)$val) {
        $line->quantity= (int)$val;
        $line->save();
      } else {
        $line->erase();
      }
    }

    $sale= $this->load($f3, $sale_uuid, 'uuid');

    $this->update_shipping_and_tax($f3, $sale);

    if ($f3->get('AJAX')) {
      return $this->json($f3, $args);
    }

    $added= $f3->get('REQUEST.added');
    $f3->reroute('/cart?uuid=' . $sale->uuid .
                 ($added ? '&added=' . rawurlencode($added) : ''));
  }

  function update_shipping($f3, $sale) {
    if ($sale->shipping_manual)
      return;

    $sale->shipping_tax= 0; // reset the tax
    $sale->shipping= 0.00;

    // Calculate shipping if not in-store pick-up
    if ($sale->shipping_address_id != 1) {
      $shipping_status= $f3->get('shipping_status');
      $shipping_rate= $f3->get('shipping_rate');

      switch ($shipping_rate) {
      case 'small':
        $sale->shipping= $sale->subtotal < 100.00 ? 9.99 : 0.00;
        break;
      case 'medium':
        $sale->shipping= $sale->subtotal < 200.00 ? 19.99 : 0.00;
        break;
      case 'large':
        $sale->shipping= $sale->subtotal < 300.00 ? 29.99 : 0.00;
        break;
      }

      $special_conditions= $f3->get('special_conditions');

      if (in_array('hazmat', $special_conditions)) {
        $sale->shipping+= 10.00;
      }
    }

    $sale->save();
  }

  function update_shipping_and_tax($f3, $sale) {
    $this->update_shipping($f3, $sale);
    $this->update_sales_tax($f3, $sale);

    $sale->save();
  }

  function set_address($f3, $args) {
    $sale_uuid= $f3->get('PARAMS.sale') ?: $f3->get('COOKIE.cartID');
    $base= $f3->get('PARAMS.sale') ? '/sale/' . $sale_uuid : '/cart';

    $type= $f3->get('REQUEST.type');

    if (!$sale_uuid)
      $f3->error(404);

    $db= $f3->get('DBH');

    $sale= $this->load($f3, $sale_uuid, 'uuid');

    if ($f3->get('PARAMS.sale') && $sale->status != 'unpaid' &&
        $type != 'billing' &&
        \Auth::authenticated_user($f3) != $f3->get('ADMIN_USER')) {
      $f3->error(403);
    }

    if (!in_array($sale->status, array('new','cart','review','unpaid')))
      $f3->error(500);

    $address= new DB\SQL\Mapper($db, 'sale_address');
    if (($address_id= $f3->get('REQUEST.id'))) {
      // Can't change address #1, it's special
      if ($address_id == 1) $f3->error(500);
      $address->load(array('id = ?', $address_id))
        or $f3->error(404);
    }

    /* Split ZIP+4, might all be in zip5 */
    $zip5= trim($f3->get('REQUEST.zip5'));
    if (preg_match('/^(\d{5})-(\d{4})$/', $zip5, $m)) {
      $zip5= $m[1];
      $zip4= $m[2];
    } else {
      $zip4= trim($f3->get('REQUEST.zip4'));
    }

    $address->name= trim($f3->get('REQUEST.name'));
    $address->company= trim($f3->get('REQUEST.company'));
    $address->address1= trim($f3->get('REQUEST.address1'));
    $address->address2= trim($f3->get('REQUEST.address2'));
    $address->city= trim($f3->get('REQUEST.city'));
    $address->state= trim($f3->get('REQUEST.state'));
    $address->zip5= $zip5;
    $address->zip4= $zip4;
    $address->phone= trim($f3->get('REQUEST.phone'));
    $address->verified= 0;

    $address->save();

    if ($type == 'shipping') {
      $sale->shipping_address_id= $address->id;
    } else {
      $sale->billing_address_id= $address->id;
    }

    $sale->save();

    if ($type == 'shipping') {
      $this->update_shipping_and_tax($f3, $sale);
    }

    if ($f3->get('AJAX')) {
      return $this->json($f3, $args);
    }

    $f3->reroute($base. '/checkout?uuid=' . $sale->uuid);
  }

  function calculate_shipping($f3, $args) {
    $sale_uuid= $f3->get('PARAMS.sale') ?: $f3->get('COOKIE.cartID');

    if (!$sale_uuid)
      $f3->error(404);

    $db= $f3->get('DBH');

    $sale= $this->load($f3, $sale_uuid, 'uuid');

    if ($f3->get('PARAMS.sale') && $sale->status != 'unpaid' &&
        $type != 'billing' &&
        \Auth::authenticated_user($f3) != $f3->get('ADMIN_USER')) {
      $f3->error(403);
    }

    if (!in_array($sale->status, array('new','cart','review','unpaid')))
      $f3->error(500);

    $in= json_decode($f3->get('POST.shippingAddress'));

    $address= new DB\SQL\Mapper($db, 'sale_address');

    /* Split ZIP+4, might all be in zip5 */
    $zip5= trim($in->postalCode);
    if (preg_match('/^(\d{5})-(\d{4})$/', $zip5, $m)) {
      $zip5= $m[1];
      $zip4= $m[2];
    }

    $address->name= trim($in->recipient);
    $address->company= trim($in->organization);
    $address->address1= trim($in->addressLine[0]);
    $address->address2= trim($in->addressLine[1]);
    $address->city= trim($in->city);
    $address->state= trim($in->region);
    $address->zip5= $zip5;
    $address->phone= trim($in->phone);
    $address->verified= 0;

    $address->save();

    $sale->shipping_address_id= $address->id;

    $sale->save();

    $this->update_shipping_and_tax($f3, $sale);

    // reload to get current total
    $sale= $this->load($f3, $sale_uuid, 'uuid');

    header("Content-type: application/json");
    echo json_encode([
      'status' => 'success',
      'total' => [
        'amount' => $sale->total * 100,
        'label' => 'Total',
        'pending' => false
      ],
      'shippingOptions' => [
        [
          'id' => 'shipping',
          'label' => 'Shipping & handling',
          'amount' => $sale->shipping * 100
        ],
        [
          'id' => 'pickup',
          'label' => 'FREE In-Store Pickup',
          'amount' => 0
        ]
      ]
    ]);
  }

  function change_shipping_option($f3, $args) {
    $sale_uuid= $f3->get('PARAMS.sale') ?: $f3->get('COOKIE.cartID');

    if (!$sale_uuid)
      $f3->error(404);

    $db= $f3->get('DBH');

    $sale= $this->load($f3, $sale_uuid, 'uuid');

    if ($f3->get('PARAMS.sale') && $sale->status != 'unpaid' &&
        $type != 'billing' &&
        \Auth::authenticated_user($f3) != $f3->get('ADMIN_USER')) {
      $f3->error(403);
    }

    if (!in_array($sale->status, array('new','cart','review','unpaid')))
      $f3->error(500);

    if ($f3->get('POST.shippingOption') == 'pickup') {
      $sale->shipping_address_id= 1;
    }

    $sale->save();

    $this->update_shipping_and_tax($f3, $sale);

    // reload to get current total
    $sale= $this->load($f3, $sale_uuid, 'uuid');

    header("Content-type: application/json");
    echo json_encode([
      'status' => 'success',
      'total' => [
        'amount' => $sale->total * 100,
        'label' => 'Total',
        'pending' => false
      ],
    ]);
  }

  function remove_address($f3, $args) {
    if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
      $f3->error(403);

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->load(array('uuid = ?', $sale_uuid))
      or $f3->error(404);

    $type= $f3->get('REQUEST.type');

    if ($type == 'shipping') {
      $sale->shipping_address_id= NULL;
    } else {
      $sale->billing_address_id= NULL;
    }

    $sale->save();

    return $this->json($f3, $args);
  }

  function set_in_store_pickup($f3, $args) {
    $sale_uuid= $f3->get('PARAMS.sale');
    $base= $f3->get('PARAMS.sale') ? '/sale/' . $sale_uuid : '/cart';

    if ($sale_uuid) {
      if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
        $f3->error(403);
    } else {
      $sale_uuid= $f3->get('COOKIE.cartID');
    }

    if (!$sale_uuid)
      $f3->error(404);

    $db= $f3->get('DBH');

    $sale= $this->load($f3, $sale_uuid, 'uuid');

    if ($sale->status != 'new' && $sale->status != 'cart')
      $f3->error(500);

    $sale->shipping_address_id= 1;

    $sale->save();

    $this->update_shipping_and_tax($f3, $sale);

    if ($f3->get('AJAX')) {
      return $this->json($f3, $args);
    }

    $f3->reroute($base . '/checkout?uuid=' . $sale->uuid);
  }

  function ship_to_billing_address($f3, $args) {
    $sale_uuid= $f3->get('PARAMS.sale');
    $base= $f3->get('PARAMS.sale') ? '/sale/' . $sale_uuid : '/cart';

    if ($sale_uuid) {
      if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
        $f3->error(403);
    } else {
      $sale_uuid= $f3->get('COOKIE.cartID');
    }

    if (!$sale_uuid)
      $f3->error(404);

    $db= $f3->get('DBH');

    $sale= $this->load($f3, $sale_uuid, 'uuid');

    if ($sale->status != 'new' && $sale->status != 'cart')
      $f3->error(500);

    $sale->shipping_address_id= 0;

    $sale->save();

    $this->update_shipping_and_tax($f3, $sale);

    if ($f3->get('AJAX')) {
      return $this->json($f3, $args);
    }

    $f3->reroute($base . '/checkout?uuid=' . $sale->uuid);
  }

  function bill_to_shipping_address($f3, $args) {
    $sale_uuid= $f3->get('PARAMS.sale') ?: $f3->get('COOKIE.cartID');
    $base= $f3->get('PARAMS.sale') ? '/sale/' . $sale_uuid : '/cart';

    if (!$sale_uuid)
      $f3->error(404);

    $db= $f3->get('DBH');

    $sale= $this->load($f3, $sale_uuid, 'uuid');

    if (!in_array($sale->status, [ 'new', 'review', 'cart', 'unpaid' ]))
      $f3->error(500);

    $sale->billing_address_id= $sale->shipping_address_id;

    $sale->save();

    if ($f3->get('AJAX')) {
      return $this->json($f3, $args);
    }

    $f3->reroute($base . '/checkout');
  }

  function set_shipping($f3, $args) {
    if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
      $f3->error(403);

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= $this->load($f3, $sale_uuid, 'uuid')
      or $f3->error(404);

    $shipping= $f3->get('REQUEST.shipping');

    if ($shipping == 'auto') {
      $sale->shipping= 0.00;
      $sale->shipping_manual= 0;
    } else {
      $sale->shipping= $shipping;
      $sale->shipping_manual= 1;
    }

    $sale->save();

    $this->update_shipping_and_tax($f3, $sale);

    return $this->json($f3, $args);
  }

  function verify_address($f3, $args) {
    if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
      $f3->error(403);

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= $this->load($f3, $sale_uuid, 'uuid')
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

    $client= new GuzzleHttp\Client();
    
    $uri= "https://api.taxcloud.net/1.0/taxcloud/VerifyAddress?apiKey=" .
            $f3->get('TAXCLOUD_KEY');

    try {
      $response= $client->post($uri, [ 'json' => $data ]);
    } catch (\Exception $e) {
      $f3->error(500, (sprintf("Request failed: %s (%s)",
                               $e->getMessage(), $e->getCode())));
    }

    $data= json_decode($response->getBody());

    if (json_last_error() != JSON_ERROR_NONE) {
      $f3->error(500, json_last_error_msg());
    }

    if ($data->ErrNumber != "0") {
      $f3->error(500, $data->ErrDescription);
    }

    $address->zip4= $data->Zip4;
    $address->zip5= $data->Zip5;
    $address->state= $data->State;
    $address->city= $data->City;
    $address->address2= $data->Address2;
    $address->address1= $data->Address1;
    $address->verified= 1;
    $address->save();

    $this->update_shipping_and_tax($f3, $sale);

    return $this->json($f3, $args);
  }

  function set_person($f3, $args) {
    $sale_uuid= $f3->get('PARAMS.sale');
    $base= $f3->get('PARAMS.sale') ? '/sale/' . $sale_uuid : '/cart';

    if ($sale_uuid) {
      if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
        $f3->error(403);
    } else {
      $sale_uuid= $f3->get('COOKIE.cartID');
    }

    if (!$sale_uuid)
      $f3->error(404);

    $db= $f3->get('DBH');

    $sale= $this->load($f3, $sale_uuid, 'uuid');

    if (!in_array($sale->status, array('new','cart','review')))
      $f3->error(500);

    $sale->name= trim($f3->get('REQUEST.name'));
    $sale->email= trim($f3->get('REQUEST.email'));
    $sale->save();

    if ($f3->get('AJAX')) {
      return $this->json($f3, $args);
    }

    // don't push into checkout flow if just saving address
    if ($f3->get('REQUEST.just_saving')) {
      $f3->reroute($base . '?uuid=' . $sale->uuid);
    }

    $f3->reroute($base . '/checkout?uuid=' . $sale->uuid);
  }

  function add_exemption($f3, $args) {
    if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
      $f3->error(403);

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->load(array('uuid = ?', $sale_uuid))
      or $f3->error(404);

    if ($f3->get('REQUEST.cert') == 'manual') {
      $sale->tax_exemption= "manual";
      $sale->save();

      $this->update_shipping_and_tax($f3, $sale);

      return $this->json($f3, $args);
    }

    $data= array(
      'customerID' => $sale->uuid,
      'exemptCert' => array(
        'CreatedDate' => date("m/d/Y"),
        'SinglePurchase' => true,
        'SinglePurchaseOrderNumber' => $sale->id,
        'PurchaserFirstName' => trim($f3->get('REQUEST.first_name')),
        'PurchaserLastName' => trim($f3->get('REQUEST.last_name')),
        'PurchaserTitle' => trim($f3->get('REQUEST.title')),
        'PurchaserAddress1' => trim($f3->get('REQUEST.address1')),
        'PurchaserAddress2' => trim($f3->get('REQUEST.address2')),
        'PurchaserCity' => trim($f3->get('REQUEST.city')),
        'PurchaserState' => trim($f3->get('REQUEST.state')),
        'PurchaserZip' => trim($f3->get('REQUEST.zip')),
        'ExemptStates' => array( 'StateAbbr' => 'CA', 'ReasonForExemption' => 'Resale', 'IdentificationNumber' => trim($f3->get('REQUEST.cert'))),
        'PurchaseTaxID' => array( 'TaxType' => 'StateIssued', 'IDNumber' => trim($f3->get('REQUEST.cert')), 'StateOfIssue' => 'CA'),
        'PurchaserExemptionReason' => 'Resale',
        'PurchaserExemptionValue' => '',
        'PurchaserBusinessType' => 'RetailTrade',
        'PurchaserBusinessTypeOtherValue' => '',
      ),
      'apiLoginID' => $f3->get("TAXCLOUD_ID"),
    );

    $client= new GuzzleHttp\Client();
    
    $uri= "https://api.taxcloud.net/1.0/taxcloud/AddExemptCertificate?apiKey=" .
            $f3->get('TAXCLOUD_KEY');

    try {
      $response= $client->post($uri, [ 'json' => $data ]);
    } catch (\Exception $e) {
      $f3->error(500, (sprintf("Request failed: %s (%s)",
                               $e->getMessage(), $e->getCode())));
    }

    $data= json_decode($response->getBody());

    if (json_last_error() != JSON_ERROR_NONE) {
      $f3->error(500, json_last_error_msg());
    }

    if ($data->ErrNumber != "0") {
      $f3->error(500, $data->ErrDescription);
    }

    $sale->tax_exemption= $data->CertificateID;
    $sale->save();

    return $this->json($f3, $args);
  }

  function set_status($f3, $args) {
    if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER')) {
      if ($f3->get('UPLOAD_KEY') != $_REQUEST['key']) {
        $f3->error(403);
      }
    }

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= $this->load($f3, $sale_uuid, 'uuid');

    $status= $f3->get('REQUEST.status');

    if (!in_array($status, array('new','review','unpaid','paid','processing',
                                 'shipped','cancelled','onhold'))) {
      // XXX better error handling
      $f3->error(500, "Didn't understand requested status.");
    }

    if ($status == 'shipped') {
      $this->capture_payments($f3, $sale);
    }

    $sale->status= $status;

    $sale->save();

    return $this->json($f3, $args);
  }

  function update_sales_tax($f3, $sale) {
    /* No address or shipping rate? Can't do it. */
    if ((!$sale->shipping_address_id && !$sale->billing_address_id) ||
        in_array($f3->get('shipping_rate'), [ 'truck', 'unknown' ])) {
      $sale->tax_calculated= null;
      $sale->save();
      return;
    }

    $db= $f3->get('DBH');

    /* Override tax for manual tax exemptions */
    if ($sale->tax_exemption == 'manual') {
      $sale->shipping_tax= 0;
      $item= new DB\SQL\Mapper($db, 'sale_item');
      $items= $item->find(array('sale_id = ?', $sale->id),
                           array('order' => 'id'));
      foreach ($items as $i) {
        $i->tax= 0;
        $i->save();
      }
      $sale->tax_calculated= date('Y-m-d H:i:s');
      $sale->save();
      return;
    }

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
        'Zip5' => '90014',
        'State' => 'CA',
        'City' => 'Los Angeles',
        'Address2' => '',
        'Address1' => '645 S Los Angeles St',
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

    $data= json_decode($response);

    if (!$data->CartItemsResponse) {
      error_log($response);
      $f3->error(500, "Unable to calculate sales tax.");
    }

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

  }

  function calculate_sales_tax($f3, $args) {
    if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
      $f3->error(403);

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->load(array('uuid = ?', $sale_uuid))
      or $f3->error(404);

    $this->update_sales_tax($f3, $sale);

    return $this->json($f3, $args);
  }

  function capture_sales_tax($f3, $sale) {
    /* Nothing to capture for manual exemptions */
    if ($sale->tax_exemption == 'manual') {
      return;
    }

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
      $f3->get('log')->error("cURL Error #:" . $err);
    }
  }

  function json($f3, $args, $uuid= null) {
    $this->load($f3, $uuid ? $uuid : $f3->get('PARAMS.sale'), 'uuid');

    header("Content-type: application/json");
    echo json_encode(array( 'sale' => $f3->get('sale'),
                            'person' => $f3->get('person'),
                            'billing_address' => $f3->get('billing_address'),
                            'shipping_address' => $f3->get('shipping_address'),
                            'items' => $f3->get('items'),
                            'payments' => $f3->get('payments'),
                            'shipping_status' => $f3->get('shipping_status'),
                            'shipping_rate' => $f3->get('shipping_rate'),
                            'special_conditions' =>
                              $f3->get('special_conditions'),
                            ),
                     JSON_PRETTY_PRINT);
  }

  function fetch_json($f3, $args) {
    if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER')) {
      if ($f3->get('UPLOAD_KEY') != $_REQUEST['key']) {
        $f3->error(403);
      }
    }

    return $this->json($f3, $args);
  }

  function process_creditcard_payment($f3, $args) {
    $stripe= array( 'secret_key' => $f3->get('STRIPE_SECRET_KEY'),
                    'publishable_key' => $f3->get('STRIPE_KEY'));

    $db= $f3->get('DBH');

    $uuid= $f3->get('PARAMS.sale');
    $sale= $this->load($f3, $uuid, 'uuid');

    bcscale(2);
    $due= bcsub($sale->total, $sale->paid);
    $amount= (int)bcmul($due, 100);

    \Stripe\Stripe::setApiKey($stripe['secret_key']);

    $token= $f3->get('REQUEST.stripeToken');

    if (!strlen($token)) {
      $f3->get('log')->error("No token");
      $f3->error(500, "There was an error processing your card.");
    }

    if (($name= trim($f3->get('REQUEST.name')))) {
      $sale->name= $name;
    }
    if (($email= trim($f3->get('REQUEST.email')))) {
      $sale->email= $email;
    }

    if (!$sale->email) {
      $f3->error(500, "We need an email address for this order.");
    }

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

      $f3->get('log')->debug(json_encode($body));

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

    echo json_encode(array('message' => 'Success!'));

    $f3->abort(); // let client go

    // reload
    $sale= $this->load($f3, $uuid, 'uuid');

    self::send_order_paid_email($f3);
  }

  function get_paypal_client($f3) {
    $client_id= $f3->get('PAYPAL_CLIENT_ID');
    $secret= $f3->get('PAYPAL_SECRET');

    if ($f3->get('DEBUG')) {
      $env= new \PayPalCheckoutSdk\Core\SandboxEnvironment($client_id, $secret);
    } else {
      $env=
        new \PayPalCheckoutSdk\Core\ProductionEnvironment($client_id, $secret);
    }

    return new \PayPalCheckoutSdk\Core\PayPalHttpClient($env);
  }

  function get_paypal_order($f3, $args) {
    $uuid= $f3->get('PARAMS.sale');
    $sale= $this->load($f3, $uuid, 'uuid');

    $ship_to= $f3->get('shipping_address');

    $items= $f3->get('items');
    $paypal_items= [];
if (0) {
    foreach ($items as $item) {
      $paypal_items[]= [
        'name' => $item['name'],
        'unit_amount' => [
          'currency_code' => 'USD',
          'value' => sprintf('%.2f', $item['sale_price']),
        ],
        'tax' => [
          'currency_code' => 'USD',
          'value' => sprintf('%.2f', $item['tax']),
        ],
        'quantity' => $item['quantity'],
        'sku' => $item['code'],
        'category' => 'PHYSICAL_GOODS',
      ];
    }

    /* PayPal doesn't understand taxable shipping, so treat as an item */
    if ($sale->shipping > 0.00) {
      $paypal_items[]= [
        'name' => "Shipping",
        'unit_amount' => [
          'currency_code' => 'USD',
          'value' => sprintf('%.2f', $sale->shipping),
        ],
        'tax' => [
          'currency_code' => 'USD',
          'value' => sprintf('%.2f', $sale->shipping_tax),
        ],
        'quantity' => 1,
        'sku' => 'ZZ-SHIPPING',
        'category' => 'PHYSICAL_GOODS',
      ];
    }
}

    $order= [
      'intent' => 'CAPTURE',
      'application_context' => [
        'shipping_preference' => 'SET_PROVIDED_ADDRESS',
        'user_action' => 'PAY_NOW',
      ],
      'purchase_units' => [
        [
          'reference_id' => $sale->uuid,
          'amount' => [
            'currency_code' => 'USD',
            'value' => sprintf('%.2f', $sale->total),
            'breakdown' => [
              'item_total' => [
                'currency_code' => 'USD',
                'value' => sprintf('%.2f', $sale->subtotal + $sale->shipping),
              ],
              'tax_total' => [
                'currency_code' => 'USD',
                'value' => sprintf('%.2f', $sale->tax),
              ],
            ],
          ],
          'items' => $paypal_items,
          'shipping' => [
            'name' => [
              'full_name' => $ship_to['name'],
            ],
            'address' => [
              'address_line_1' => $ship_to['address1'],
              'address_line_2' => $ship_to['address2'],
              'admin_area_2' => $ship_to['city'],
              'admin_area_1' => $ship_to['state'],
              'postal_code' => $ship_to['zip5'],
              'country_code' => 'US',
            ]
          ]
        ]
      ],
    ];

    //$f3->get('log')->debug(json_encode($order, JSON_PRETTY_PRINT));

    $client= $this->get_paypal_client($f3);

    $request= new \PayPalCheckoutSdk\Orders\OrdersCreateRequest();
    $request->prefer('return=representation');
    $request->body= json_encode($order);

    $response= $client->execute($request);

    echo json_encode($response->result);
  }

  function process_paypal_payment($f3, $args) {
    $db= $f3->get('DBH');

    $uuid= $f3->get('PARAMS.sale');
    $sale= $this->load($f3, $uuid, 'uuid');

    $order_id= $f3->get('REQUEST.order_id');

    $client= $this->get_paypal_client($f3);

    $response= $client->execute(
      new \PayPalCheckoutSdk\Orders\OrdersGetRequest($order_id)
    );

    $payment= new DB\SQL\Mapper($db, 'sale_payment');
    $payment->sale_id= $sale->id;
    $payment->method= 'paypal';
    $payment->amount= $response->result->purchase_units[0]->amount->value;
    $payment->data= json_encode($response->result);
    $payment->save();

    self::capture_sales_tax($f3, $sale);

    $sale->status= 'paid';
    $sale->save();

    echo json_encode(array('message' => 'Success!'));

    $f3->abort(); // let client go

    // reload
    $sale= $this->load($f3, $uuid, 'uuid');

    self::send_order_paid_email($f3);
  }

  function get_giftcard_balance($f3, $args) {
    $client= new GuzzleHttp\Client();

    $backend= $f3->get('GIFT_BACKEND');
    $uri= $backend . "/gift-card/" . rawurlencode($f3->get('REQUEST.card'));

    try {
      $response= $client->get($uri, [
        'headers' => [ 'Accept' => 'application/json' ]
      ]);
    } catch (\Exception $e) {
      throw new \Exception(sprintf("Request failed: %s (%s)",
                                   $e->getMessage(), $e->getCode()));
    }

    $data= json_decode($response->getBody());

    if ($data->balance == 0.00) {
      return $f3->error(500, "There is no remaining balance on this card.");
    }

    echo $response->getBody();
  }

  function process_giftcard_payment($f3, $args) {
    $db= $f3->get('DBH');

    $sale= $this->load($f3, $f3->get('PARAMS.sale'), 'uuid');

    $client= new GuzzleHttp\Client();

    $backend= $f3->get('GIFT_BACKEND');
    $uri= $backend . "/gift-card/" . rawurlencode($f3->get('REQUEST.card'));

    try {
      $response= $client->get($uri, [
        'headers' => [ 'Accept' => 'application/json' ]
      ]);
    } catch (\Exception $e) {
      throw new \Exception(sprintf("Request failed: %s (%s)",
                                   $e->getMessage(), $e->getCode()));
    }

    $data= json_decode($response->getBody());

    if ($data->balance == 0.00) {
      return $f3->error(500, "There is no remaining balance on this card.");
    }

    $amount= -min($data->balance, $sale->total - $sale->paid);

    try {
      $response= $client->post($uri, [
        'headers' => [ 'Accept' => 'application/json' ],
        'json' => [ 'amount' => $amount ],
      ]);
    } catch (\Exception $e) {
      throw new \Exception(sprintf("Request failed: %s (%s)",
                                   $e->getMessage(), $e->getCode()));
    }

    $data= json_decode($response->getBody());

    $payment= new DB\SQL\Mapper($db, 'sale_payment');
    $payment->sale_id= $sale->id;
    $payment->method= 'gift';
    $payment->amount= -$amount;
    $payment->data= json_encode(array(
      'card' => $f3->get('REQUEST.card'),
    ));
    $payment->save();

    if ($sale->total - ($sale->paid + $amount) > 0) {
      $sale->status= 'unpaid';
      $sale->save();

      echo json_encode(array('paid' => 0));
      return;
    }

    self::capture_sales_tax($f3, $sale);

    $sale->status= 'paid';
    $sale->save();

    echo json_encode(array('paid' => 1));

    $f3->abort(); // let client go

    // reload
    $sale= $this->load($f3, $uuid, 'uuid');
  }

  function process_other_payment($f3, $args) {
    if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
      $f3->error(403);

    $db= $f3->get('DBH');

    $sale= $this->load($f3, $f3->get('PARAMS.sale'), 'uuid');

    $amount= $sale->total - $sale->paid;

    $payment= new DB\SQL\Mapper($db, 'sale_payment');
    $payment->sale_id= $sale->id;
    $payment->method= 'other';
    $payment->amount= $amount;
    $payment->save();

    if ($amount != $sale->total - $sale->paid) {
      echo json_encode(array('paid' => 0));
      return;
    }

    self::capture_sales_tax($f3, $sale);

    $sale->status= 'paid';
    $sale->save();

    echo json_encode(array('paid' => 1));

    $f3->abort(); // let client go

    // reload
    $sale= $this->load($f3, $uuid, 'uuid');
  }

  function place_order($f3, $args) {
    $uuid= $f3->get('COOKIE.cartID');

    if (!$uuid) {
      $f3->error(404);
    }

    $comment= $f3->get('REQUEST.comment');

    $sale= $this->load($f3, $uuid, 'uuid');
    if ($sale->status != 'cart')
      $f3->error(500, "There's no cart here.");

    if (!$sale->email) {
      $f3->reroute('/cart?error=email');
    }

    if (!$sale->shipping_address_id) {
      $f3->reroute('/cart?error=shipping');
    }

    $sale->status= 'review';
    $sale->save();

    // save comment
    $db= $f3->get('DBH');
    $note= new DB\SQL\Mapper($db, 'sale_note');
    $note->sale_id= $sale->id;
    $note->person_id= $sale->person_id;
    $note->content= $comment;
    $note->save();

    // reload
    $sale= $this->load($f3, $uuid, 'uuid');

    self::send_order_email($f3, $comment);
    self::send_order_placed_email($f3);

    $this->forget_cart($f3, $args);

    $f3->reroute("/sale/" . $sale->uuid);
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
    $sale->tax= 'CAST(ROUND(shipping_tax, 2) +
                      (SELECT SUM(ROUND(tax,2))
                         FROM sale_item WHERE sale_id = sale.id)
                   AS DECIMAL(9,2))';
    $sale->total= 'CAST(shipping + ROUND(shipping_tax, 2) +
                        (SELECT SUM(quantity * sale_price(retail_price,
                                                          discount_type,
                                                          discount)
                                    + ROUND(tax, 2))
                           FROM sale_item WHERE sale_id = sale.id)
                     AS DECIMAL(9,2))';
    $sale->paid= '(SELECT SUM(amount)
                     FROM sale_payment
                    WHERE sale_id = sale.id)';
    $sales= $sale->find(array('status NOT IN ("new","cart") AND modified BETWEEN ? AND ?',
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
      echo " <OrderTotal>", $order->total ?: '0.00', "</OrderTotal>\n";
      echo " <TaxAmount>", $order->tax ?: '0.00', "</TaxAmount>\n";
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
      echo "   <Name><![CDATA[", $shipping_address->name ?: $order->name, "]]></Name>\n";
      echo "   <Company><![CDATA[", $shipping_address->company, "]]></Company>\n";
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
      echo "   <Phone><![CDATA[", $shipping_address->phone, "]]></Phone>\n";
      echo "  </ShipTo>\n";
      /* shipping address */
      echo " </Customer>\n";

      /* items */
      echo " <Items>\n";

      $item= new DB\SQL\Mapper($db, 'sale_item');
      $item->code= "(SELECT code FROM item WHERE id = item_id)";
      $item->name= "IFNULL(override_name,
                           (SELECT name FROM item WHERE id = item_id))";
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

    // Special handling for "other" carriers (like GSO), expects tracking
    // number to look like "Carrier/#####"
    if ($carrier == 'Other'
        && preg_match('!^(.+?)/(.+)!', $tracking_number, $m))
    {
      $carrier= $m[0];
      $tracking_number= $m[1];
    }

    $shipment= new DB\SQL\Mapper($db, 'sale_shipment');
    $shipment->sale_id= $sale->id;
    $shipment->carrier= $carrier;
    $shipment->service= $service;
    $shipment->tracking_number= $tracking_number;
    // These are just defaults in case we can't parse them from data
    $shipment->created= date("Y-m-d H:i:s");
    $shipment->ship_date= date("Y-m-d");
    $shipment->shipping_cost= 0.00;
    $shipment->data= file_get_contents('php://input');

    try {
      $xml= simplexml_load_string($shipment->data);
      $shipment->created=
        (new \Datetime($xml->LabelCreateDate))->format("Y-m-d H:i:s");
      $shipment->ship_date= (new \Datetime($xml->ShipDate))->format("Y-m-d");
      $shipment->shipping_cost= $xml->ShippingCost;
    } catch (\Exception $e) {
      $f3->get('log')->error(
        sprintf("ShipStation shipment info failure: %s (%s)",
                $e->getMessage(), $e->getCode())
      );
    }

    $shipment->save();

    $sale->status= 'shipped';
    $sale->save();

    // Capture any pending payments
    $this->capture_payments($f3, $sale);

    echo "Success!";
  }

  function send_order_email($f3, $comment= null) {
    $httpClient= new \Http\Adapter\Guzzle6\Client(new \GuzzleHttp\Client());
    $sparky= new \SparkPost\SparkPost($httpClient,
                           [ 'key' => $f3->get('SPARKPOST_KEY') ]);

    $f3->set('comment', $comment);
    $title= "Order " . $f3->get('sale.status') . ': ' .
            sprintf('%07d', $f3->get('sale.id')) . ' ' . $f3->get('sale.name');
    $f3->set('title', $title);

    if ($f3->get('sale.status') == 'review') {
      $f3->set('call_to_action', 'Review Order');
      $f3->set('call_to_action_url', 'https://' .  $_SERVER['HTTP_HOST'] .
                                     '/sale/' .
                                     $f3->get('sale.uuid') . '/edit');
    }

    $html= Template::instance()->render('email-invoice.html');

    $promise= $sparky->transmissions->post([
      'content' => [
        'html' => $html,
        'subject' => $title,
        'from' => array('name' => 'Raw Materials Art Supplies',
                        'email' => $f3->get('CONTACT_SALES')),
        'inline_images' => [
          [
            'name' => 'logo.png',
            'type' => 'image/png',
            'data' => base64_encode(file_get_contents('../ui/logo.png')),
          ],
        ],
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
      $f3->get('log')->error(
        sprintf("SparkPost failure: %s (%s)",
                $e->getMessage(), $e->getCode())
      );
    }
  }

  function send_order_placed_email($f3) {
    $httpClient= new \Http\Adapter\Guzzle6\Client(new \GuzzleHttp\Client());
    $sparky= new \SparkPost\SparkPost($httpClient,
                           [ 'key' => $f3->get('SPARKPOST_KEY') ]);

    $order_no= sprintf("%07d", $f3->get('sale.id'));
    $f3->set('title', "Thanks for shopping with us! (Order #{$order_no})");
    $f3->set('preheader', "Thank you for shopping at Raw Materials Art Supplies!  Your order is being reviewed.");

    $f3->set('content_top', Markdown::instance()->convert("Thank you for shopping at Raw Materials Art Supplies!
    
Your order will be reviewed, and you will receive another email within one business day with information on how to pay for your order, as well as the estimated shipping time."));

    $f3->clear('call_to_action');
    $f3->clear('call_to_action_url');
    $f3->clear('comment');

    $f3->set('content_bottom', Markdown::instance()->convert("Let us know if there is anything else that we can do to help."));

    $html= Template::instance()->render('email-invoice.html');

    $promise= $sparky->transmissions->post([
      'content' => [
        'html' => $html,
        'subject' => $f3->get('title'),
        'from' => array('name' => 'Raw Materials Art Supplies',
                        'email' => $f3->get('CONTACT_SALES')),
        'inline_images' => [
          [
            'name' => 'logo.png',
            'type' => 'image/png',
            'data' => base64_encode(file_get_contents('../ui/logo.png')),
          ],
        ],
      ],
      'recipients' => [
        [
          'address' => [
            'name' => $f3->get('sale.name'),
            'email' => $f3->get('sale.email'),
          ],
        ],
        [
          // BCC ourselves
          'address' => [
            'name' => $f3->get('sale.name'),
            'header_to' => $f3->get('sale.email'),
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
      $f3->get('log')->error(
        sprintf("SparkPost failure: %s (%s)",
                $e->getMessage(), $e->getCode())
      );
    }
  }

  function send_order_paid_email($f3) {
    $httpClient= new \Http\Adapter\Guzzle6\Client(new \GuzzleHttp\Client());
    $sparky= new \SparkPost\SparkPost($httpClient,
                           [ 'key' => $f3->get('SPARKPOST_KEY') ]);

    $order_no= sprintf("%07d", $f3->get('sale.id'));
    $f3->set('title', "Thanks for shopping with us! (Order #{$order_no})");
    $f3->set('preheader', "Thank you for shopping at Raw Materials Art Supplies!  Your order is being processed.");

    $sale= $f3->get('sale');
    $method= $sale['shipping_address_id'] == 1
               ? "is ready for pick up" : "has been shipped";
    $f3->set('content_top', Markdown::instance()->convert("Thank you for shopping at Raw Materials Art Supplies!

Your order is now being processed, and you will receive another email when your order $method or if we have other updates."));

    $f3->clear('call_to_action');
    $f3->clear('call_to_action_url');
    $f3->clear('comment');

    $f3->set('content_bottom', Markdown::instance()->convert("Let us know if there is anything else that we can do to help."));

    $html= Template::instance()->render('email-invoice.html');

    $promise= $sparky->transmissions->post([
      'content' => [
        'html' => $html,
        'subject' => $f3->get('title'),
        'from' => array('name' => 'Raw Materials Art Supplies',
                        'email' => $f3->get('CONTACT_SALES')),
        'inline_images' => [
          [
            'name' => 'logo.png',
            'type' => 'image/png',
            'data' => base64_encode(file_get_contents('../ui/logo.png')),
          ],
        ],
      ],
      'recipients' => [
        [
          'address' => [
            'name' => $f3->get('sale.name'),
            'email' => $f3->get('sale.email'),
          ],
        ],
        [
          // BCC ourselves
          'address' => [
            'name' => $f3->get('sale.name'),
            'header_to' => $f3->get('sale.email'),
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
      $f3->get('log')->error(
        sprintf("SparkPost failure: %s (%s)",
                $e->getMessage(), $e->getCode())
      );
    }
  }

  function confirm_order($f3, $args) {
    if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER')) {
        $f3->error(403);
    }

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= $this->load($f3, $sale_uuid, 'uuid')
      or $f3->error(404);

    $sale->status= 'unpaid';
    $sale->save();

    $content_top= $f3->get('REQUEST.content_top');
    $content_bottom= $f3->get('REQUEST.content_bottom');

    self::send_order_reviewed_email($f3, $content_top, $content_bottom);

    return $this->json($f3, $args);
  }

  function send_order_reviewed_email($f3, $top, $bottom) {
    $httpClient= new \Http\Adapter\Guzzle6\Client(new \GuzzleHttp\Client());
    $sparky= new \SparkPost\SparkPost($httpClient,
                           [ 'key' => $f3->get('SPARKPOST_KEY') ]);

    $order_no= sprintf("%07d", $f3->get('sale.id'));
    $f3->set('title', "Thanks for shopping with us! (Order #{$order_no})");
    $f3->set('preheader', "Thank you for shopping at Raw Materials Art Supplies!  We have reviewed your order.");

    $f3->set('content_top', Markdown::instance()->convert($top));

    $f3->set('call_to_action', 'Pay Your Invoice Online');
    $f3->set('call_to_action_url', 'https://' .
             $_SERVER['HTTP_HOST'] . '/sale/' . $f3->get('sale.uuid') . '/pay');

    $f3->set('content_bottom', Markdown::instance()->convert($bottom));

    $html= Template::instance()->render('email-template.html');

    $promise= $sparky->transmissions->post([
      'content' => [
        'html' => $html,
        'subject' => $f3->get('title'),
        'from' => array('name' => 'Raw Materials Art Supplies',
                        'email' => $f3->get('CONTACT_SALES')),
        'inline_images' => [
          [
            'name' => 'logo.png',
            'type' => 'image/png',
            'data' => base64_encode(file_get_contents('../ui/logo.png')),
          ],
        ],
      ],
      'recipients' => [
        [
          'address' => [
            'name' => $f3->get('sale.name'),
            'email' => $f3->get('sale.email'),
          ],
        ],
        [
          // BCC ourselves
          'address' => [
            'name' => $f3->get('sale.name'),
            'header_to' => $f3->get('sale.email'),
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
      $f3->get('log')->error(
        sprintf("SparkPost failure: %s (%s)",
                $e->getMessage(), $e->getCode())
      );
    }
  }

  function send_note($f3, $args) {
    if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER')) {
        $f3->error(403);
    }

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= $this->load($f3, $sale_uuid, 'uuid')
      or $f3->error(404);

    $message= $f3->get('REQUEST.note');

    $db= $f3->get('DBH');
    $note= new DB\SQL\Mapper($db, 'sale_note');
    $note->sale_id= $sale->id;
    $note->person_id= \Auth::authenticated_user($f3);
    $note->content= $message;
    $note->save();

    self::send_order_note($f3, $message);

    return $this->json($f3, $args);
  }

  function send_order_note($f3, $note) {
    $httpClient= new \Http\Adapter\Guzzle6\Client(new \GuzzleHttp\Client());
    $sparky= new \SparkPost\SparkPost($httpClient,
                           [ 'key' => $f3->get('SPARKPOST_KEY') ]);

    $order_no= sprintf("%07d", $f3->get('sale.id'));
    $f3->set('title', "Thanks for shopping with us! (Order #{$order_no})");
    $f3->set('preheader', "Thank you for shopping at Raw Materials Art Supplies!  We have reviewed your order.");

    $f3->set('content_top', Markdown::instance()->convert($note));

    $html= Template::instance()->render('email-template.html');

    $promise= $sparky->transmissions->post([
      'content' => [
        'html' => $html,
        'subject' => $f3->get('title'),
        'from' => array('name' => 'Raw Materials Art Supplies',
                        'email' => $f3->get('CONTACT_SALES')),
        'inline_images' => [
          [
            'name' => 'logo.png',
            'type' => 'image/png',
            'data' => base64_encode(file_get_contents('../ui/logo.png')),
          ],
        ],
      ],
      'recipients' => [
        [
          'address' => [
            'name' => $f3->get('sale.name'),
            'email' => $f3->get('sale.email'),
          ],
        ],
        [
          // BCC ourselves
          'address' => [
            'name' => $f3->get('sale.name'),
            'header_to' => $f3->get('sale.email'),
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
      $f3->get('log')->error(
        sprintf("SparkPost failure: %s (%s)",
                $e->getMessage(), $e->getCode())
      );
    }
  }

  function retrieve_cart($f3, $args) {
    $db= $f3->get('DBH');

    $email= $f3->get('REQUEST.email');

    if (!v::email()->validate($email)) {
      $f3->reroute('/cart?error=invalid_email');
    }

    // validate key
    $key= $f3->get('REQUEST.key');
    if ($key) {
      list($ts, $hash)= explode(':', $key);
      if ((int)$ts < time() - 24*3600) {
        $f3->reroute('/cart?error=expired_key');
      }
      if ((int)$ts > time() ||
          $hash != base64_encode(hash('sha256', $ts . $f3->get('UPLOAD_KEY'), true))) {
        $f3->reroute('/cart?error="invalid_key');
      }
    }

    $sale= new DB\SQL\Mapper($db, 'sale');
    $sale->items= '(SELECT SUM(quantity)
                      FROM sale_item WHERE sale_id = sale.id)';
    $sale->total= 'CAST(shipping + ROUND(shipping_tax, 2) +
                        (SELECT SUM(quantity * sale_price(retail_price,
                                                          discount_type,
                                                          discount)
                                    + ROUND(tax, 2))
                           FROM sale_item WHERE sale_id = sale.id)
                     AS DECIMAL(9,2))';
    $sales= $sale->find([ 'email = ? AND status = ?', $email, 'cart' ],
                        [ 'order' => 'id' ]);

    if ($sales && !$key) {
      self::send_cart_login($f3, $email);
      $f3->reroute('/cart?success=sent');
    } elseif (!$key) {
      $f3->reroute('/cart?error=no_carts');
    } else {
      if (count($sales) == 1) {
        $f3->reroute('/cart?uuid=' . $sales[0]->uuid);
      }
      $sales_out= array();
      foreach ($sales as $i) {
        $sales_out[]= $i->cast();
      }
      $f3->set('sales', $sales_out);
      echo Template::instance()->render('sale-cart-retrieve.html');
    }
  }

  function combine_carts($f3, $args) {
    $db= $f3->get('DBH');

    $email= $f3->get('REQUEST.email');

    $person_id= \Auth::authenticated_user($f3);
    if (!$email && $person_id) {
      $sale= new DB\SQL\Mapper($db, 'sale');
      $sales= $sale->find([ 'person_id = ? AND status = ?', $person_id, 'cart'],
                          [ 'order' => 'id' ]);
    } else {
      if (!v::email()->validate($email)) {
        $f3->error(500, "That email is invalid.");
      }

      // validate key
      $key= $f3->get('REQUEST.key');
      if ($key) {
        list($ts, $hash)= explode(':', $key);
        if ((int)$ts < time() - 24*3600) {
          $f3->error(500, "That key is expired.");
        }
        if ((int)$ts > time() ||
            $hash != base64_encode(hash('sha256', $ts . $f3->get('UPLOAD_KEY'), true))) {
          $f3->error(500, "That key is invalid.");
        }
      }

      $sale= new DB\SQL\Mapper($db, 'sale');
      $sales= $sale->find([ 'email = ? AND status = ?', $email, 'cart' ],
                          [ 'order' => 'id' ]);
    }

    if (!$sales)
      $f3->error(500, "No carts found.");

    $cart= array_pop($sales);

    $q= "UPDATE sale_item SET sale_id = ? WHERE sale_id = ?";
    foreach ($sales as $sale) {
      $db->exec($q, [ $cart->id, $sale->id ]);
      $sale->erase();
    }

    $f3->reroute('/cart?uuid=' . $cart->uuid);
  }

  function send_cart_login($f3, $email) {
    $httpClient= new \Http\Adapter\Guzzle6\Client(new \GuzzleHttp\Client());
    $sparky= new \SparkPost\SparkPost($httpClient,
                           [ 'key' => $f3->get('SPARKPOST_KEY') ]);

    $title= "Retrieve your Shopping Cart";
    $f3->set('title', $title);

    $ts= time();
    $hash= base64_encode(hash('sha256', $ts . $f3->get('UPLOAD_KEY'), true));
    $key= "$ts:$hash";

    $f3->set('content_top', Markdown::instance()->convert("Here is a link to reclaim your shopping cart:"));
    $f3->set('call_to_action', 'Retrieve Cart');
    $f3->set('call_to_action_url',
             'https://' . $_SERVER['HTTP_HOST'] . '/cart/retrieve? ' .
             http_build_query([ 'email' => $email, 'key' => $key ]));
    $f3->set('content_bottom', Markdown::instance()->convert("Let us know if there is anything else that we can do to help."));

    $html= Template::instance()->render('email-template.html');

    $promise= $sparky->transmissions->post([
      'content' => [
        'html' => $html,
        'subject' => $title,
        'from' => array('name' => 'Raw Materials Art Supplies',
                        'email' => $f3->get('CONTACT_SALES')),
        'inline_images' => [
          [
            'name' => 'logo.png',
            'type' => 'image/png',
            'data' => base64_encode(file_get_contents('../ui/logo.png')),
          ],
        ],
      ],
      'recipients' => [
        [
          'address' => [
            'name' => '',
            'email' => $email,
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
      $f3->get('log')->error(
        sprintf("SparkPost failure: %s (%s)",
                $e->getMessage(), $e->getCode())
      );
    }
  }

  function send_order_test($f3, $args) {
    $this->load($f3, $f3->get('PARAMS.sale'), 'uuid');
    self::send_order_email($f3);
    $f3->reroute("status");
  }

  static function can_order($f3) {
    switch ($f3->get('ORDERING_AVAIL')) {
    case 'all':
      return true;
    case 'rewards':
      return (bool)\Auth::authenticated_user($f3);
    case 'rewardsplus':
      $person= \Auth::authenticated_user_details($f3);
      return (bool)$person['rewardsplus'];
    }
    return false;
  }

  static function can_pickup($f3) {
    switch ($f3->get('PICKUP_AVAIL')) {
    case 'all':
      return true;
    case 'rewards':
      return (bool)\Auth::authenticated_user($f3);
    case 'rewardsplus':
      $person= \Auth::authenticated_user_details($f3);
      return (bool)$person['rewardsplus'];
    }
    return false;
  }

  static function can_ship($f3) {
    switch ($f3->get('SHIPPING_AVAIL')) {
    case 'all':
      return true;
    case 'rewards':
      return (bool)\Auth::authenticated_user($f3);
    case 'rewardsplus':
      $person= \Auth::authenticated_user_details($f3);
      return (bool)$person['rewardsplus'];
    }
    return false;
  }

  static function can_deliver($f3) {
    switch ($f3->get('DELIVERY_AVAIL')) {
    case 'all':
      return true;
    case 'rewards':
      return (bool)\Auth::authenticated_user($f3);
    case 'rewardsplus':
      $person= \Auth::authenticated_user_details($f3);
      return (bool)$person['rewardsplus'];
    }
    return false;
  }

  static function can_dropship($f3) {
    switch ($f3->get('DROPSHIP_AVAIL')) {
    case 'all':
      return true;
    case 'rewards':
      return (bool)\Auth::authenticated_user($f3);
    case 'rewardsplus':
      $person= \Auth::authenticated_user_details($f3);
      return (bool)$person['rewardsplus'];
    }
    return false;
  }
}
