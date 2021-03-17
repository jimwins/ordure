<?php

require '../lib/point-in-kml-polygon.php';

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
    $f3->route("GET /sale/@sale/get-stripe-payment-intent",
               'Sale->get_stripe_payment_intent');
    $f3->route("POST /sale/@sale/process-stripe-payment",
               'Sale->process_stripe_payment');
    $f3->route("POST /sale/@sale/process-creditcard-payment",
               'Sale->process_creditcard_payment');
    $f3->route("GET /sale/@sale/get-paypal-order",
               'Sale->get_paypal_order');
    $f3->route("POST /sale/@sale/process-paypal-payment",
               'Sale->process_paypal_payment');
    $f3->route("POST /sale/@sale/process-other-payment [ajax]",
               'Sale->process_other_payment');
    $f3->route("POST /sale/@sale/process-rewards",
               'Sale->process_rewards_payment');
    $f3->route("POST /sale/@sale/remove-item [ajax]", 'Sale->remove_item');
    $f3->route("POST /sale/@sale/update-item [ajax]", 'Sale->update_item');
    $f3->route("POST /sale/@sale/set-address", 'Sale->set_address');
    $f3->route("POST /sale/@sale/remove-address [ajax]",
               'Sale->remove_address');
    $f3->route("POST /sale/@sale/set-in-store-pickup [ajax]",
               'Sale->set_in_store_pickup');
    $f3->route("POST /sale/@sale/set-shipping-method",
               'Sale->set_shipping_method');
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
      $f3->route("GET|POST /cart/set-in-store-pickup", 'Sale->set_in_store_pickup');
      $f3->route("POST /cart/set-shipping-method", 'Sale->set_shipping_method');
      $f3->route("POST /cart/ship-to-billing", 'Sale->ship_to_billing_address');
      $f3->route("GET|POST /cart/apply-tax-exemption", 'Sale->apply_tax_exemption');
      $f3->route("GET|POST /cart/remove-tax-exemption", 'Sale->remove_tax_exemption');
      $f3->route("POST /cart/set-shipping-method", 'Sale->set_shipping_method');
      $f3->route("POST /cart/place-order", 'Sale->place_order');
      $f3->route("POST /cart/amz-get-details", 'Sale->amz_get_details');
      $f3->route("POST /cart/amz-process-order", 'Sale->amz_process_order');
      $f3->route("GET|POST /cart/retrieve", 'Sale->retrieve_cart');
      $f3->route("GET /cart/combine-carts", 'Sale->combine_carts');
      $f3->route("GET /cart/forget", 'Sale->forget_cart_and_redir');
      $f3->route("GET /cart/remember", 'Sale->remember_cart_test');
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
                        array('order' => 'id DESC', 'limit' => 100));

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

    $q= "SELECT item.code, item.name,
                item.width, item.length, item.height, item.weight,
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
    $item->length= "(SELECT length FROM item WHERE item_id = item.id)";
    $item->height= "(SELECT height FROM item WHERE item_id = item.id)";
    $item->hazmat= "(SELECT hazmat FROM item WHERE item_id = item.id)";
    $item->oversized= "(SELECT oversized FROM item WHERE item_id = item.id)";
    $item->no_backorder= "(SELECT no_backorder FROM item WHERE item_id = item.id)";
    $item->stock= "(SELECT stock FROM item JOIN scat_item WHERE item_id = item.id AND scat_item.code = item.code)";
    $item->minimum_quantity= "(SELECT minimum_quantity FROM item JOIN scat_item WHERE item_id = item.id AND scat_item.code = item.code)";

    $items= $item->find(array('sale_id = ?', $sale->id),
                         array('order' => 'id'));
    $items_out= $stock_status= [];
    foreach ($items as $i) {
      $items_out[]= $i->cast();
      if ($i->oversized) {
        $stock_status['oversized']++;
      }
      if ($i->hazmat) {
        $stock_status['hazmat']++;
      }
      if (in_array(0, [ $i->weight, $i->length, $i->width, $i->height ])) {
        $stock_status['unknown']++;
      }
      // no stock and not stocked? special order.
      if (!$i->stock && !$i->minimum_quantity && $i->purchase_quantity) {
        $stock_status['special']++;
      }
      // more in order than in stock? stock is limited.
      if ($i->stock < $i->quantity && $i->purchase_quantity) {
        $stock_status['stock_limited']++;
      }
    }
    $f3->set('items', $items_out);
    $f3->set('stock_status', $stock_status);

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

    $notes= new DB\SQL\Mapper($db, 'sale_note');
    $notes= $notes->find(array('sale_id = ?', $sale->id),
                                 array('order' => 'id'));
    $notes_out= array();
    foreach ($notes as $i) {
      $notes_out[]= $i->cast();
    }
    $f3->set('notes', $notes_out);

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

  function get_shipping_options($f3, $sale, $address= null) {
    $db= $f3->get('DBH');
    $status= $special= [];
    $weight= 0.0;

    if (!$address) {
      $address= new DB\SQL\Mapper($db, 'sale_address');
      $address->load(array('id = ?', $sale->shipping_address_id))
        or $f3->error(404);
    }

    if (($minimum= $f3->get("SALE_MINIMUM")) && $minimum > $sale->total) {
      return [];
    }

    $item_dim= [];

    $items= $f3->get('items');

    foreach ($items as $sale_item) {
      // Check stock
      $scat_item= new DB\SQL\Mapper($db, 'scat_item');
      $scat_item->load(array('code = ?', $sale_item['code']));
      // no details? must be special order
      if ($scat_item->dry()) {
        $status['special']++;
        continue;
      }
      // no stock and not stocked? special order.
      if (!$scat_item->stock && !$scat_item->minimum_quantity && $scat_item->purchase_quantity) {
        $status['special']++;
      }
      // more in order than in stock? stock is limited.
      if ($scat_item->stock < $sale_item['quantity'] && $scat_item->purchase_quantity) {
        $status['stock_limited']++;
      }

      // hazmat? (extra charge)
      if ($sale_item['hazmat']) {
        $status['hazmat']++;
      }
      // oversized?
      if ($sale_item['oversized']) {
        $status['oversized']++;
      }

      // unknown dimensions?
      if ($sale_item['weight'] == 0 || $sale_item['length'] == 0) {
        $status['unknown']++;
      } else {
        $item_dim= array_merge($item_dim, array_fill(0, $sale_item['quantity'],
          [
            'length' => $sale_item['length'],
            'width' => $sale_item['width'],
            'height' => $sale_item['height'],
          ]));
        $weight+= $sale_item['weight'] * $sale_item['quantity'];
      }
    }

    if ($status['unknown']) {
      return [];
    }

    $options= [];

    if (self::can_deliver($f3)) {
      $zone= self::in_delivery_area($address);
      if ($zone) {
        if (preg_match('/\\$(\\d+)/', $zone, $m)) {
          $price= $m[1];
        } else {
          $price= 6;
        }
        // TODO better distinction between bike and bike-cargo
        if ($status['oversized']) {
          $options['cargo_bike']= (($sale->subtotal > 199) ?
                                    0.00 :
                                    $price + 10 + 0.99);
        } else {
          $options['bike']= (($sale->subtotal > 69) ?
                              0.00 :
                              $price + 0.99);
        }
      }
    }

    if (self::can_truck($f3) && self::in_truck_area($address)) {
      $truck_sizes= [
        'sm' => [ [ 30, 25, 16 ], [ 108, 4, 4 ] ],
        'md' => [ [ 46, 38, 36 ] ],
        'lg' => [ [ 74, 42, 36 ], [ 108, 8, 8 ] ],
        'xl' => [ [ 85, 56, 36 ] ],
        'xxl' => [ [ 133, 60, 60 ] ],
      ];

      $base= [
        'sm' => 13,
        'md' => 35,
        'lg' => 55,
        'xl' => 95,
        'xxl' => 170,
      ];

      $best= null;
      // figure out cargo size
      foreach ($truck_sizes as $name => $sizes) {
        if ($this->fits_in_box($sizes, $item_dim)) {
          $best= $name;
          break;
        }
      }

      // figure out price
      if ($best) {
        list($miles, $minutes)= $this->get_truck_distance($f3, $address);

        if (!$miles) {
          error_log("Unable to figure out distance for destination.");
        } else {
          $price= ($base[$best] + $miles + ($minutes / 2)) * 1.05;
          $options['local']= [
            'size' => $best,
            'price' => ceil($price) - 0.01
          ];
        }
      }
    }

    if (self::can_ship($f3) && !$status['oversized']) {
      $economy_boxes= [
        [ 9, 5, 3 ],
        [ 5, 5, 3.5 ],
        [ 12.25, 3, 3 ],
      ];

      if ($weight < 0.9 && !$status['hazmat'] &&
          $this->fits_in_box($economy_boxes, $item_dim))
      {
        $options['economy']= (($sale->subtotal > 69) ? 0.00 : 5.99);
      }

      $extra_small_boxes= array_merge($economy_boxes, [
        [ 18.75, 3, 3 ],
        [ 8.67, 5.37, 1.67 ],
        [ 10, 7, 4.75 ],
        [ 7, 7, 6 ],
        [ 12.8, 10.9, 2.37 ],
//        [ 14.37, 7.5, 5.12 ],
      ]);

      $small_boxes= [
        [ 18, 15, 8 ],
      ];

      $medium_boxes= [
        [ 19, 25, 8 ],
      ];

      if ($weight < 10 && $this->fits_in_box($extra_small_boxes, $item_dim)) {
        $options['default']= (($sale->subtotal > 99) ? 0.00 : 9.99);
      } elseif ($weight < 10 && $this->fits_in_box($small_boxes, $item_dim)) {
        $options['default']= (($sale->subtotal > 99) ? 0.00 : 9.99);
      } elseif ($weight < 20 && $this->fits_in_box($medium_boxes, $item_dim)) {
        $options['default']= (($sale->subtotal > 199) ? 0.00 : 19.99);
      } elseif ($weight < 30) {
        $options['default']= (($sale->subtotal > 399) ? 0.00 : 29.99);
      }

      if (isset($options['default']) && $status['hazmat']) {
        $options['default']+= 10;
      }
    }

    return $options;
  }

  // returns [ $miles, $minutes ]
  function get_truck_distance($f3, $address) {
    try {
      $gm= new \yidas\googleMaps\Client([
        'key' => $f3->get('GOOGLE_API_KEY')
      ]);

      $from= "645 S Los Angeles St, Los Angeles, CA 90014";
      $to= "{$address->address1}, " .
           "{$address->city}, {$address->state} {$address->zip5}";

      $result= $gm->distanceMatrix($from, $to);

      if ($result['status'] == 'OK') {
        return [
          $result['rows'][0]['elements'][0]['distance']['value'] / 1609.34,
          $result['rows'][0]['elements'][0]['duration']['value'] / 60,
        ];
      }
    } catch (\Exception $e) {
      error_log("Google exception: {$e->getMessage()}\n");
    }

    return [ 0, 0 ];
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

      self::remember_cart($f3, $uuid);

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

    $db= $f3->get('DBH');

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

      $stages= [ 'login' => 1, 'shipping' => 2, 'shipping-method' => 3, 'payment' => 4, 'amz-select' => 4 ];
      $stage= $f3->get('REQUEST.stage');

      if ($f3->get('REQUEST.access_token')) {
        $stage= 'amz-select';
      }

      if (!in_array($stage, array_keys($stages))) {
        $stage= 'login'; // start at the beginning

        if ($sale->name && $sale->email) {
          if ($sale->shipping_address_id) {
            $address= new DB\SQL\Mapper($db, 'sale_address');
            $address->load(array('id = ?', $sale->shipping_address_id))
              or $f3->error(404);

            if ($address->id && !$address->verified) {
              $this->easypost_verify_address($f3, $address);
            }

            if ($address->verified) {
              if ($address->id != 1 && !$sale->shipping_method && $stage != 'review')
              {
                $shipping_options=
                  $this->get_shipping_options($f3, $sale, $address);
                if (empty($shipping_options)) {
                  $stage= 'review';
                } elseif (count($shipping_options) == 1 &&
                          isset($shipping_options['default']))
                {
                  $sale->shipping_method= 'default';
                  $sale->shipping= $shipping_options['default'];
                  $sale->save();
                  $this->update_sales_tax($f3, $sale);
                  $sale= $this->load($f3, $uuid, 'uuid');
                  $stage= 'payment';
                } else {
                  $stage= 'shipping-method';
                }
              } else {
                if (!$sale->shipping_method) {
                  $sale->shipping_method= 'default';
                  $sale->shipping= 0;
                  $sale->save();
                  $this->update_sales_tax($f3, $sale);
                  $sale= $this->load($f3, $uuid, 'uuid');
                }
                $stage= 'payment';
              }
            } else {
              $f3->set("ADDRESS_NOT_VERIFIED", 1);
              $stage= 'shipping';
            }
          } else {
            $stage= 'shipping';
          }
        }
      }

      if ($stage == 'shipping-method') {
        if (!$shipping_options) {
          $shipping_options=
            $this->get_shipping_options($f3, $sale, $address);
        }
        $f3->set('shipping_options', $shipping_options);
      }

      // Paying? Figure out if rewards are available.
      if ($stage == 'payment') {
        $person= \Auth::authenticated_user_details($f3);
        if ($person /* && !rewards_already_used */) {
          $points= $person['points_available'];
          $due= bcsub($sale->total, $sale->paid);
          if ($points > 1000 && $due > 100.00) {
            $person['points_to_use']= 1000;
            $person['credit_available']= 100.00;
          } elseif ($points > 250 && $due > 20.00) {
            $person['points_to_use']= 250;
            $person['credit_available']= 20.00;
          } elseif ($points > 100 && $due > 6.00) {
            $person['points_to_use']= 100;
            $person['credit_available']= 6.00;
          } elseif ($points > 50 && $due > 2.00) {
            $person['points_to_use']= 50;
            $person['credit_available']= 2.00;
          }
          $f3->set('rewards', $person);
        }
      }

      $f3->set('stage', $stage);
      $f3->set('stage_number', $stages[$stage]);

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
      // all amazon-paid orders forced to default shipping
      $sale->shipping_method= 'default';

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

    if (trim($comment) != '') {
      $db= $f3->get('DBH');
      $note= new DB\SQL\Mapper($db, 'sale_note');
      $note->sale_id= $sale->id;
      $note->person_id= $sale->person_id;
      $note->content= $comment;
      $note->save();
    }

    // reload
    $sale= $this->load($f3, $uuid, 'uuid');

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
                           ['LastUpdateTimestamp'];
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
          error_log("response " . json_encode($res->toArray()));
          $f3->error(500, "Error processing Amazon payment.");
        }
      }
    }
  }

  static function remember_cart($f3, $uuid) {
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

  function remember_cart_test($f3, $args) {
    $uuid= $f3->get('REQUEST.cart');
    $domain= ($_SERVER['HTTP_HOST'] != 'localhost' ?
              $_SERVER['HTTP_HOST'] : false);

    SetCookie('cartID', $uuid, null /* don't expire */,
              '/', $domain, true, true);
    $f3->reroute('/art-supplies');
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
    $cart= false;

    if ($sale_uuid) {
      if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
        $f3->error(403);
    } else {
      $cart= true;
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

        self::remember_cart($f3, $sale_uuid);
      } else {
        $f3->get('log')->info("Loading cart from UUID '$sale_uuid'.");
      }
    }

    $db= $f3->get('DBH');

    $db->begin();

    $item_code= trim($f3->get('REQUEST.item'));
    $quantity= max((int)$f3->get('REQUEST.quantity'), 1);

    $sale= $this->load($f3, $sale_uuid, 'uuid')
      or $f3->error(404);

    if (!in_array($sale->status, array('new','cart','review')))
      $f3->error(500, 'Cart already closed. <a href="/cart/forget">Start another one.</a>');

    $item= new DB\SQL\Mapper($db, 'item');
    $item->nretail_price= "IFNULL((SELECT retail_price FROM scat_item WHERE scat_item.code = item.code), retail_price)";
    $item->discount_type= "(SELECT discount_type FROM scat_item WHERE scat_item.code = item.code)";
    $item->discount= "(SELECT discount FROM scat_item WHERE scat_item.code = item.code)";
    if (self::can_pickup($f3) || self::can_ship($f3)) {
      $item->npurchase_quantity= "IFNULL((SELECT scat_item.purchase_quantity FROM scat_item WHERE scat_item.code = item.code), purchase_quantity)";
    } else {
      $item->npurchase_quantity= "IFNULL((SELECT scat_item.is_dropshippable FROM scat_item WHERE scat_item.code = item.code), purchase_quantity)";
    }
    $item->stock= "(SELECT stock FROM scat_item WHERE scat_item.code = item.code)";
    $item->load(array('code = ?', $item_code))
      or $f3->error(404);

    $line= new DB\SQL\Mapper($db, 'sale_item');

    if ($item->npurchase_quantity > 0 || $item->is_kit) {
      /* Don't include items that are parts of a kit */
      $existing= $line->find([
        'sale_id = ? AND item_id = ? AND kit_id IS NULL',
        $sale->id, $item->id
      ]);
    }

    if ($existing) {
      $existing[0]->quantity+= max($quantity, $item->npurchase_quantity);
      if ($item->no_backorder && $line->quantity > $item->stock) {
        $existing[0]->quantity= $item->stock;
      }
      $existing[0]->save();

      /* Was this a kit? Need to adjust quantities of kit items */
      // XXX this assumes kit contents haven't changed
      if ($item->is_kit) {
        $q= "UPDATE sale_item, kit_item
                SET sale_item.quantity = ? * kit_item.quantity
              WHERE sale_id = ?
                AND kit_item.kit_id = ?
                AND sale_item.item_id = kit_item.item_id";
        $db->exec($q, [ $existing[0]->quantity, $sale->id, $item->id ]);
      }

    } else {
      $line->sale_id= $sale->id;
      $line->item_id= $item->id;
      $line->quantity= max($quantity, $item->npurchase_quantity);
      if ($item->no_backorder && $line->quantity > $item->stock) {
        $line->quantity= $item->stock;
      }
      $line->retail_price= $item->nretail_price;
      $line->discount_type= $item->discount_type;
      $line->discount= $item->discount;
      $line->discount_manual= 0;
      $line->tic= $item->tic;
      $line->tax= 0.00;

      $line->insert();

      /* Was this a kit? Need to ad the kit items */
      // XXX this assumes kit contents haven't changed
      if ($item->is_kit) {
        $q= "INSERT INTO sale_item
                    (sale_id, item_id, kit_id, quantity, retail_price, tax)
             SELECT ?,
                    item_id,
                    kit_id,
                    ? * quantity,
                    0.00,
                    0.00
               FROM kit_item
              WHERE kit_item.kit_id = ?";
        $db->exec($q, [ $sale->id, $line->quantity, $item->id ]);
      }
    }

    $db->commit();

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
    if ($line->kit_id) {
      $f3->error(500, "Can't remove kit items individually.");
    }
    if ($line->sale_id != $sale->id) {
      $f3->error(500);
    }
    $db->begin();
    $item= new DB\SQL\Mapper($db, 'item');
    $item->load(array('id = ?', $line->item_id))
      or $f3->error(404);
    if ($item->is_kit) {
      $q= "DELETE FROM sale_item WHERE sale_id = ? AND kit_id = ?";
      $db->exec($q, [ $sale->id, $item->id ]);
    }
    $line->erase();
    $db->commit();

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

    if ($line->kit_id) {
      $f3->error(500, "Can't edit kit items individually.");
    }

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

    $db->begin();

    if ($sale->status != 'new' && $sale->status != 'cart')
      $f3->error(500);

    foreach ($f3->get('REQUEST.qty') as $id => $val) {
      $line= new DB\SQL\Mapper($db, 'sale_item');
      $line->load(array('id = ?', $id))
        or $f3->error(404);

      if ($line->kit_id) {
        $f3->error(500, "Can't change kit items individually.");
      }

      $item= new DB\SQL\Mapper($db, 'item');
      $item->stock= "(SELECT stock FROM scat_item WHERE scat_item.code = item.code)";
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

      if ($val > 0 && $purchase_quantity && ($val % $purchase_quantity) != 0) {
        // XXX really should provide feedback
        continue;
      }

      if ($val > 0 && $val > $item->stock && $item->no_backorder) {
        error_log("Trying to order more than is available of {$item->code}\n");
        // XXX really should provide feedback
        continue;
      }

      if ((int)$val) {
        $line->quantity= (int)$val;
        $line->save();

        if ($item->is_kit) {
          $q= "UPDATE sale_item, kit_item
                  SET sale_item.quantity = ? * kit_item.quantity
                WHERE sale_id = ?
                  AND kit_item.kit_id = ?
                  AND sale_item.item_id = kit_item.item_id";
          $db->exec($q, [ $line->quantity, $sale->id, $item->id ]);
        }
      } else {
        if ($item->is_kit) {
          $q= "DELETE FROM sale_item WHERE sale_id = ? AND kit_id = ?";
          $db->exec($q, [ $sale->id, $item->id ]);
        }
        $line->erase();
      }
    }

    $db->commit();

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
      if ($sale->shipping_method) {
        $method= $sale->shipping_method;
        if (preg_match('/local_(.+)/', $method, $m)) {
          $method= "local";
          $size= $m[1];
        }

        $shipping_options= $this->get_shipping_options($f3, $sale);
        if (!$method ||
            !isset($shipping_options[$method]))
        {
          $method= $sale->shipping_method= 'default';
        }

        if ($method == 'local') {
          $sale->shipping_method= 'local_' . $size;
        }

        $option= $shipping_options[$method];
        $sale->shipping= is_array($option) ? $option['price'] : $option;
      }

    }

    $sale->save();
  }

  function update_shipping_and_tax($f3, $sale) {
    $this->update_shipping($f3, $sale);
    $this->update_sales_tax($f3, $sale);

    $sale->save();
  }

  function easypost_verify_address($f3, $address) {
    \EasyPost\EasyPost::setApiKey($f3->get('EASYPOST_KEY'));

    if ($address->easypost_id) {
      $easypost= \EasyPost\Address::retrieve($address->easypost_id);
    } else {
      $address_params= [
        "verify" => [ "delivery" ],
        "name" => $address->name,
        "company" => $address->company,
        "street1" => $address->address1,
        "street2" => $address->address2,
        "city" => $address->city,
        "state" => $address->state,
        "zip" => $address->zip5,
        "country" => "US",
        "phone" => $address->phone,
      ];

      $easypost= \EasyPost\Address::create($address_params);
    }

    $address->easypost_id= $easypost->id;
    $address->name= $easypost->name;
    $address->company= $easypost->company;
    $address->address1= $easypost->street1;
    $address->address2= $easypost->street2;
    $address->city= $easypost->city;
    $address->state= $easypost->state;
    list($zip5, $zip4)= explode('-', $easypost->zip);
    $address->zip5= $zip5;
    $address->zip4= $zip4;
    $address->phone= $easypost->phone;
    $address->verified= $easypost->verifications->delivery->success ? '1' : '0';
    if ($easypost->verifications->delivery->details->longitude) {
      $distance= haversineGreatCircleDistance(
        34.043810, -118.250320,
        $easypost->verifications->delivery->details->latitude,
        $easypost->verifications->delivery->details->longitude,
        3959 /* want miles */
      );

      $address->distance= $distance;
      $address->latitude= $easypost->verifications->delivery->details->latitude;
      $address->longitude= $easypost->verifications->delivery->details->longitude;
    }
    $f3->set("verifications", $easypost->verifications->delivery->errors);
    $address->save();
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

    $address->easypost_id= null;
    $address->name= trim($f3->get('REQUEST.name'));
    $address->company= trim($f3->get('REQUEST.company'));
    $address->address1= trim($f3->get('REQUEST.address1'));
    $address->address2= trim($f3->get('REQUEST.address2'));
    $address->city= trim($f3->get('REQUEST.city'));
    $address->state= trim($f3->get('REQUEST.state'));
    $address->zip5= trim($f3->get('REQUEST.zip5'));
    $address->phone= trim($f3->get('REQUEST.phone'));

    $this->easypost_verify_address($f3, $address);

    if ($type == 'shipping') {
      $sale->shipping_address_id= $address->id;
      $sale->shipping_method= null;
    } else {
      $sale->billing_address_id= $address->id;
    }

    $sale->save();

    if ($type == 'shipping' && $address->verified) {
      $this->update_shipping_and_tax($f3, $sale);
    }

    if ($f3->get('AJAX')) {
      return $this->json($f3, $args);
    }

    $f3->reroute($base. '/checkout?uuid=' . $sale->uuid);
  }

  function fits_in_box($boxes, $items) {
    $laff= new \Cloudstek\PhpLaff\Packer();

    foreach ($boxes as $size) {
      $laff->pack($items, [
            'length' => $size[0],
            'width' => $size[1],
            'height' => $size[2],
      ]);

      $container= $laff->get_container_dimensions();

      if ($container['height'] <= $size[2] &&
          !count($laff->get_remaining_boxes()))
      {
        return true;
      }
    }

    return false;
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

    if (!$sale_uuid) {
      error_log("No sale_uuid found.\n");
      $f3->error(404);
    }

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

  function set_shipping_method($f3, $args) {
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

    if ($sale->status != 'new' && $sale->status != 'cart' && $sale->status != 'unpaid')
      $f3->error(500);

    $method= $f3->get('REQUEST.method');
    $size= $f3->get('REQUEST.size');

    $shipping_options= $this->get_shipping_options($f3, $sale);

    if (!isset($shipping_options[$method])) {
      $f3->error("Selected shipping method ('{$method}') is unavailable for this order.");
    }

    $sale->shipping_method= $method . ($size ? "_$size" : "");
    error_log("new method: " . $method . ($size ? "_$size" : ""));
    $sale->shipping= (is_array($shipping_options[$method]) ?
                      $shipping_options[$method]['price'] :
                      $shipping_options[$method]);
    $sale->shipping_tax= 0; // reset the tax

    $sale->save();

    $this->update_sales_tax($f3, $sale);

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
      $sale->shipping_tax= 0.00;
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

  function apply_tax_exemption($f3, $args) {
    $sale_uuid= $f3->get('PARAMS.sale');
    $base= $f3->get('PARAMS.sale') ? '/sale/' . $sale_uuid : '/cart';

    if ($sale_uuid) {
      if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
        $f3->error(403);
    } else {
      $sale_uuid= $f3->get('COOKIE.cartID');
    }

    if (!$sale_uuid) {
      error_log("No sale_uuid found.\n");
      $f3->error(404);
    }

    $db= $f3->get('DBH');

    $sale= $this->load($f3, $sale_uuid, 'uuid');

    if ($sale->status != 'new' && $sale->status != 'cart')
      $f3->error(500);

    $person= \Auth::authenticated_user_details($f3);
    error_log(json_encode($person));
    if (!$person['exemption_certificate_id']) {
      $f3->error(500, "No exemption available.");
    }

    $sale->tax_exemption= $person['exemption_certificate_id'];

    $sale->save();

    $this->update_shipping_and_tax($f3, $sale);

    if ($f3->get('AJAX')) {
      return $this->json($f3, $args);
    }

    $f3->reroute($base . '/checkout?uuid=' . $sale->uuid);
  }

  function remove_tax_exemption($f3, $args) {
    $sale_uuid= $f3->get('PARAMS.sale');
    $base= $f3->get('PARAMS.sale') ? '/sale/' . $sale_uuid : '/cart';

    if ($sale_uuid) {
      if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER'))
        $f3->error(403);
    } else {
      $sale_uuid= $f3->get('COOKIE.cartID');
    }

    if (!$sale_uuid) {
      error_log("No sale_uuid found.\n");
      $f3->error(404);
    }

    $db= $f3->get('DBH');

    $sale= $this->load($f3, $sale_uuid, 'uuid');

    if ($sale->status != 'new' && $sale->status != 'cart')
      $f3->error(500);

    $sale->tax_exemption= null;

    $sale->save();

    $this->update_shipping_and_tax($f3, $sale);

    if ($f3->get('AJAX')) {
      return $this->json($f3, $args);
    }

    $f3->reroute($base . '/checkout?uuid=' . $sale->uuid);
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
    /* No shipping address or method? Can't do it. */
    if (!$sale->shipping_address_id ||
        ($sale->shipping_address_id > 1 && !$sale->shipping_method))
    {
      $sale->tax_calculated= null;
      $sale->save();
      return;
    }

    $db= $f3->get('DBH');

    /* Always reset shipping tax, will recalculate */
    $sale->shipping_tax= 0;

    /* Override tax for manual tax exemptions */
    if ($sale->tax_exemption == 'manual') {
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

    if ($sale->tax_exemption) {
      $data['exemptCert']= [ 'CertificateID' => $sale->tax_exemption ];
    }

    $index_map= []; $n= 1;

    $item= new DB\SQL\Mapper($db, 'sale_item');
    $item->sale_price= "sale_price(retail_price, discount_type, discount)";
    $items= $item->find(array('sale_id = ?', $sale->id),
                         array('order' => 'id'));
    if (!$items) return; // No items? No tax.
    foreach ($items as $i) {
      $index= $n++;
      $index_map[$index]= $i->id;
      $data['cartItems'][]= array(
        'Index' => $index,
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

      $item->load(array('id = ?', $index_map[$response->CartItemIndex]))
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
                            'notes' => $f3->get('notes'),
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

  function get_stripe_payment_intent($f3, $args) {
    $db= $f3->get('DBH');

    $uuid= $f3->get('PARAMS.sale');
    $sale= $this->load($f3, $uuid, 'uuid');

    $stripe= new \Stripe\StripeClient($f3->get('STRIPE_SECRET_KEY'));

    bcscale(2);
    $due= bcsub($sale->total, $sale->paid);
    $amount= (int)bcmul($due, 100);

    $customer_details= [
      'email' => $sale->email,
      'name' => $sale->name,
      'metadata' => [
        "person_id" => $sale->person_id,
      ],
    ];

    if ($sale->shipping_address_id > 0) {
      $address= new DB\SQL\Mapper($db, 'sale_address');
      $address->load(array('id = ?', $sale->shipping_address_id))
        or $f3->error(404);

      $customer_details['shipping']= [
        'name' => $address->name,
        'phone' => $address->phone,
        'address' => [
          'line1' => $address->address1,
          'line2' => $address->address2,
          'city' => $address->city,
          'state' => $address->state,
          'postal_code' => $address->zip5,
          'country' => 'US',
        ],
      ];
    }

    if ($sale->stripe_payment_intent_id) {
      $payment_intent= $stripe->paymentIntents->retrieve(
        $sale->stripe_payment_intent_id
      );

      if ($payment_intent->status == 'succeeded') {
        $f3->error(500, "Payment already completed.");
      }

      $stripe->customers->update($payment_intent->customer, $customer_details);

      if ($payment_intent->amount != $amount) {
        $stripe->paymentIntents->update($sale->stripe_payment_intent_id, [
          'amount' => $amount,
        ]);
      }
    } else {
      $customer= $stripe->customers->create($customer_details);
      $payment_intent= $stripe->paymentIntents->create([
        'customer' => $customer->id,
        'amount' => $amount,
        'currency' => 'usd',
        'metadata' => [
          "sale_id" => $sale->id,
          "sale_uuid" => $sale->uuid,
        ],
      ]);

      $sale->stripe_payment_intent_id= $payment_intent->id;
      $sale->save();
    }

    echo json_encode([
      'secret' => $payment_intent->client_secret,
    ]);
  }

  function handle_stripe_payment($f3, $uuid) {
    $db= $f3->get('DBH');

    $sale= $this->load($f3, $uuid, 'uuid');

    /* Avoid race between webhook and client-forwarded notification. */
    $res= $db->exec("SELECT GET_LOCK('ordure.stripe_payment', 5) AS lck");
    if (!$res[0]['lck']) {
      error_log("Unable to grab ordure.stripe_payment lock\n");
      return;
    }

    // save comment now in case we bail out early
    $comment= $f3->get('REQUEST.comment');
    if ($comment) {
      $note= new DB\SQL\Mapper($db, 'sale_note');
      $note->sale_id= $sale->id;
      $note->person_id= $sale->person_id;
      $note->content= $comment;
      $note->save();
    }

    $payment_intent_id= $sale->stripe_payment_intent_id;

    $existing= new DB\SQL\Mapper($db, 'sale_payment');
    $has= $existing->load([
      'data->"$.payment_intent_id" = ?', $payment_intent_id
    ]);

    // if we already have it, don't do it again!
    if ($has) {
      error_log("Already processed Stripe payment $payment_intent_id\n");
      return;
    }

    $stripe= new \Stripe\StripeClient($f3->get('STRIPE_SECRET_KEY'));

    $payment_intent= $stripe->paymentIntents->retrieve($payment_intent_id, []);

    if ($payment_intent->status != 'succeeded') {
      $f3->error(500, "Can only handle successful payment attempts here.");
    }

    foreach ($payment_intent->charges->data as $charge) {
      $payment= new DB\SQL\Mapper($db, 'sale_payment');
      $payment->sale_id= $sale->id;
      $payment->method= 'credit';
      $payment->amount= $charge->amount / 100;
      $payment->data= json_encode([
        'payment_intent_id' => $payment_intent_id,
        'charge_id' => $charge->id,
        'cc_brand' => ucwords($charge->payment_method_details->card->brand),
        'cc_last4' => $charge->payment_method_details->card->last4,
      ]);
      $payment->save();
    }

    self::capture_sales_tax($f3, $sale);

    $sale->status= 'paid';
    $sale->save();

    echo json_encode(array('message' => 'Success!'));

    $f3->abort(); // let client go

    // reload
    $sale= $this->load($f3, $uuid, 'uuid');
  }

  function process_stripe_payment($f3, $args) {
    return $this->handle_stripe_payment($f3, $f3->get('PARAMS.sale'));
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
        "metadata" => [
          "sale_id" => $sale->id,
          "sale_uuid" => $sale->uuid,
        ],
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

    // save comment
    $comment= $f3->get('REQUEST.comment');
    if ($comment) {
      $note= new DB\SQL\Mapper($db, 'sale_note');
      $note->sale_id= $sale->id;
      $note->person_id= $sale->person_id;
      $note->content= $comment;
      $note->save();
    }

    $sale->status= 'paid';
    $sale->save();

    echo json_encode(array('message' => 'Success!'));

    $f3->abort(); // let client go

    // reload
    $sale= $this->load($f3, $uuid, 'uuid');
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

    /* Less detail to the amount if order is partially paid already. */
    if ($sale->paid) {
      $amount=
        [
          'currency_code' => 'USD',
          'value' => sprintf('%.2f', $sale->total - $sale->paid),
        ];
    } else {
      $amount=
        [
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
        ];
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
          'custom_id' => $sale->uuid,
          'amount' => $amount,
          'items' => [],
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

    if ($sale->paypal_order_id) {
      $response= $client->execute(
        new \PayPalCheckoutSdk\Orders\OrdersGetRequest($sale->paypal_order_id)
      );

      $paypal_order= $response->result;

      if ($paypal_order->status == 'COMPLETED') {
        // Already completed? What are we doing here?
        $f3->error(500, "Payment already completed.");
      }

      $patch= [
        [
          'op' => 'replace',
          'path' => "/purchase_units/@reference_id=='{$sale->uuid}'/amount",
          'value' => $order['purchase_units'][0]['amount'],
        ],
        [
          'op' => 'replace',
          'path' => "/purchase_units/@reference_id=='{$sale->uuid}'/shipping/name",
          'value' => $order['purchase_units'][0]['shipping']['name'],
        ],
        [
          'op' => 'replace',
          'path' => "/purchase_units/@reference_id=='{$sale->uuid}'/shipping/address",
          'value' => $order['purchase_units'][0]['shipping']['address'],
        ],
      ];

      $request= new \PayPalCheckoutSdk\Orders\OrdersPatchRequest(
        $sale->paypal_order_id
      );
      $request->body= json_encode($patch);

      try {
        $patch_response= $client->execute($request);
      } catch (\PayPalHttp\HttpException $e) {
        error_log("HttpException {$e->statusCode}: {$e->result}");
        throw $e;
      }

    } else {
      $request= new \PayPalCheckoutSdk\Orders\OrdersCreateRequest();
      $request->prefer('return=representation');
      $request->body= json_encode($order);

      $response= $client->execute($request);

      $sale->paypal_order_id= $response->result->id;
      $sale->save();

    }

    echo json_encode($response->result);
  }

  function handle_paypal_payment($f3, $uuid, $order_id) {
    $db= $f3->get('DBH');

    $sale= $this->load($f3, $uuid, 'uuid');

    /* Avoid race between webhook and client-forwarded notification. */
    $res= $db->exec("SELECT GET_LOCK('ordure.paypal_payment', 5) AS lck");
    if (!$res[0]['lck']) {
      error_log("Unable to grab ordure.paypal_payment lock\n");
      return;
    }

    $existing= new DB\SQL\Mapper($db, 'sale_payment');
    $has= $existing->load(array('data->"$.id" = ?', $order_id));

    // if we already have it, don't do it again!
    if ($has) {
      error_log("Already processed PayPal $order_id");
      return;
    }

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

    // save comment
    $comment= $f3->get('REQUEST.comment');
    if ($comment) {
      $note= new DB\SQL\Mapper($db, 'sale_note');
      $note->sale_id= $sale->id;
      $note->person_id= $sale->person_id;
      $note->content= $comment;
      $note->save();
    }

    $sale->status= 'paid';
    $sale->save();

    echo json_encode(array('message' => 'Success!'));

    $f3->abort(); // let client go

    // reload
    $sale= $this->load($f3, $uuid, 'uuid');
  }

  function process_paypal_payment($f3, $args) {
    $uuid= $f3->get('PARAMS.sale');
    $order_id= $f3->get('REQUEST.order_id');

    return $this->handle_paypal_payment($f3, $uuid, $order_id);
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

    // save comment
    $comment= $f3->get('REQUEST.comment');
    if ($comment) {
      $note= new DB\SQL\Mapper($db, 'sale_note');
      $note->sale_id= $sale->id;
      $note->person_id= $sale->person_id;
      $note->content= $comment;
      $note->save();
    }

    // $amount is negative so we use bcsub()
    $due= bcsub($sale->total, bcsub($sale->paid, $amount));
    if ($due > 0.00) {
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

    $amount= (float)$f3->get('REQUEST.amount');
    if (!$amount || $amount > $sale->total - $sale->paid) {
      $f3->error(500, "Invalid amount.");
    }

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

  function process_rewards_payment($f3, $args) {
    $db= $f3->get('DBH');

    $sale= $this->load($f3, $f3->get('PARAMS.sale'), 'uuid');

    $due= bcsub($sale->total, $sale->paid);

    $person= \Auth::authenticated_user_details($f3);

    $points= $person['points_available'];
    if ($points > 1000 && $due > 100.00) {
      $points= 1000;
      $amount= 100.00;
    } elseif ($points > 250 && $due > 20.00) {
      $points= 250;
      $amount= 20.00;
    } elseif ($points > 100 && $due > 6.00) {
      $points= 100;
      $amount= 6.00;
    } elseif ($points > 50 && $due > 2.00) {
      $points= 50;
      $amount= 2.00;
    }

    $payment= new DB\SQL\Mapper($db, 'sale_payment');
    $payment->sale_id= $sale->id;
    $payment->method= 'loyalty';
    $payment->amount= $amount;
    $payment->data= json_encode(array(
      'points' => $points,
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

  function place_order($f3, $args) {
    $uuid= $f3->get('COOKIE.cartID');

    if (!$uuid) {
      $f3->error(404);
    }

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

    $comment= $f3->get('REQUEST.comment');

    // save comment
    if (trim($comment) != '') {
      $db= $f3->get('DBH');
      $note= new DB\SQL\Mapper($db, 'sale_note');
      $note->sale_id= $sale->id;
      $note->person_id= $sale->person_id;
      $note->content= $comment;
      $note->save();
    }

    // reload
    $sale= $this->load($f3, $uuid, 'uuid');

    self::send_order_review_email($f3, $comment);
    self::send_order_placed_email($f3);

    $this->forget_cart($f3, $args);

    $f3->reroute("/sale/" . $sale->uuid);
  }

  function send_order_review_email($f3, $comment= null) {
    $postmark= new \Postmark\PostmarkClient($f3->get('POSTMARK_TOKEN'));

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

    $logo= \Postmark\Models\PostmarkAttachment::fromFile(
      '../ui/logo.png',
      'logo.png',
      'image/png',
      'cid:logo.png',
    );

    $attach= [ $logo ];

    $from= "Raw Materials Art Supplies " . $f3->get('CONTACT_SALES');
    $to_list= $from;

    return $postmark->sendEmail(
      $from, $to_list, $f3->get('title'), $html, NULL, NULL, NULL,
      NULL, NULL, NULL, NULL, $attach, NULL
    );
  }

  function send_order_placed_email($f3) {
    $postmark= new \Postmark\PostmarkClient($f3->get('POSTMARK_TOKEN'));

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

    $logo= \Postmark\Models\PostmarkAttachment::fromFile(
      '../ui/logo.png',
      'logo.png',
      'image/png',
      'cid:logo.png',
    );

    $attach= [ $logo ];

    $from= "Raw Materials Art Supplies " . $f3->get('CONTACT_SALES');
    $to_list= str_replace(',', '', $f3->get('sale.name')) . " " .
              $f3->get('sale.email');
    $bcc= $from;

    return $postmark->sendEmail(
      $from, $to_list, $f3->get('title'), $html, NULL, NULL, NULL,
      NULL, NULL, $bcc, NULL, $attach, NULL
    );
  }

  function confirm_order($f3, $args) {
    if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER')) {
        $f3->error(403);
    }

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= $this->load($f3, $sale_uuid, 'uuid')
      or $f3->error(404);

    if (!$sale->shipping_method && $sale->shipping > 0) {
      $sale->shipping_method= 'default';
    }

    $sale->status= 'unpaid';
    $sale->save();

    $content_top= $f3->get('REQUEST.content_top');
    $content_bottom= $f3->get('REQUEST.content_bottom');

    self::send_order_reviewed_email($f3, $content_top, $content_bottom);

    return $this->json($f3, $args);
  }

  function send_order_reviewed_email($f3, $top, $bottom) {
    $postmark= new \Postmark\PostmarkClient($f3->get('POSTMARK_TOKEN'));

    $order_no= sprintf("%07d", $f3->get('sale.id'));
    $f3->set('title', "Thanks for shopping with us! (Order #{$order_no})");
    $f3->set('preheader', "Thank you for shopping at Raw Materials Art Supplies!  We have reviewed your order.");

    $f3->set('content_top', Markdown::instance()->convert($top));

    $f3->set('call_to_action', 'Pay Your Invoice Online');
    $f3->set('call_to_action_url', 'https://' .
             $_SERVER['HTTP_HOST'] . '/sale/' . $f3->get('sale.uuid') . '/pay');

    $f3->set('content_bottom', Markdown::instance()->convert($bottom));

    $html= Template::instance()->render('email-template.html');

    $logo= \Postmark\Models\PostmarkAttachment::fromFile(
      '../ui/logo.png',
      'logo.png',
      'image/png',
      'cid:logo.png',
    );

    $attach= [ $logo ];

    $from= "Raw Materials Art Supplies " . $f3->get('CONTACT_SALES');
    $to_list= str_replace(',', '', $f3->get('sale.name')) . " " .
              $f3->get('sale.email');
    $bcc= $from;

    return $postmark->sendEmail(
      $from, $to_list, $f3->get('title'), $html, NULL, NULL, NULL,
      NULL, NULL, $bcc, NULL, $attach, NULL
    );
  }

  function send_note($f3, $args) {
    if (\Auth::authenticated_user($f3) != $f3->get('ADMIN_USER')) {
        $f3->error(403);
    }

    $db= $f3->get('DBH');

    $sale_uuid= $f3->get('PARAMS.sale');

    $sale= $this->load($f3, $sale_uuid, 'uuid')
      or $f3->error(404);

    $comment= $f3->get('REQUEST.note');

    if (trim($comment) != '') {
      $db= $f3->get('DBH');
      $note= new DB\SQL\Mapper($db, 'sale_note');
      $note->sale_id= $sale->id;
      $note->person_id= \Auth::authenticated_user($f3);
      $note->content= $comment;
      $note->save();
    }

    self::send_order_note($f3, $comment);

    return $this->json($f3, $args);
  }

  function send_order_note($f3, $note) {
    $postmark= new \Postmark\PostmarkClient($f3->get('POSTMARK_TOKEN'));

    $order_no= sprintf("%07d", $f3->get('sale.id'));
    $f3->set('title', "Thanks for shopping with us! (Order #{$order_no})");
    $f3->set('preheader', "Thank you for shopping at Raw Materials Art Supplies!  We have reviewed your order.");

    $f3->set('content_top', Markdown::instance()->convert($note));

    $html= Template::instance()->render('email-template.html');

    $logo= \Postmark\Models\PostmarkAttachment::fromFile(
      '../ui/logo.png',
      'logo.png',
      'image/png',
      'cid:logo.png',
    );

    $attach= [ $logo ];

    $from= "Raw Materials Art Supplies " . $f3->get('CONTACT_SALES');
    $to_list= str_replace(',', '', $f3->get('sale.name')) . " " .
              $f3->get('sale.email');
    $bcc= $from;

    return $postmark->sendEmail(
      $from, $to_list, $f3->get('title'), $html, NULL, NULL, NULL,
      NULL, NULL, $bcc, NULL, $attach, NULL
    );
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
    $postmark= new \Postmark\PostmarkClient($f3->get('POSTMARK_TOKEN'));

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

    $logo= \Postmark\Models\PostmarkAttachment::fromFile(
      '../ui/logo.png',
      'logo.png',
      'image/png',
      'cid:logo.png',
    );

    $attach= [ $logo ];

    $from= "Raw Materials Art Supplies " . $f3->get('CONTACT_SALES');
    $to_list= str_replace(',', '', $f3->get('sale.name')) . " " .
              $f3->get('sale.email');

    return $postmark->sendEmail(
      $from, $to_list, $f3->get('title'), $html, NULL, NULL, NULL,
      NULL, NULL, $bcc, NULL, $attach, NULL
    );
  }

  function send_order_test($f3, $args) {
    $this->load($f3, $f3->get('PARAMS.sale'), 'uuid');
    self::send_order_review_email($f3);
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

  static function in_delivery_area($address) {
    return pointInKmzPolygon(
      '../ui/delivery.kmz',
      $address->latitude,
      $address->longitude
    );
  }

  static function can_truck($f3) {
    switch ($f3->get('TRUCK_AVAIL')) {
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

  static function in_truck_area($address) {
    // 20 miles for now, see how it goes
    return $address->distance < 20;
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

/**
 * from: https://stackoverflow.com/a/10054282
 * Calculates the great-circle distance between two points, with
 * the Haversine formula.
 * @param float $latitudeFrom Latitude of start point in [deg decimal]
 * @param float $longitudeFrom Longitude of start point in [deg decimal]
 * @param float $latitudeTo Latitude of target point in [deg decimal]
 * @param float $longitudeTo Longitude of target point in [deg decimal]
 * @param float $earthRadius Mean earth radius in [m]
 * @return float Distance between points in [m] (same as earthRadius)
 */
function haversineGreatCircleDistance(
  $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000)
{
  // convert from degrees to radians
  $latFrom = deg2rad($latitudeFrom);
  $lonFrom = deg2rad($longitudeFrom);
  $latTo = deg2rad($latitudeTo);
  $lonTo = deg2rad($longitudeTo);

  $latDelta = $latTo - $latFrom;
  $lonDelta = $lonTo - $lonFrom;

  $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
    cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
  return $angle * $earthRadius;
}
