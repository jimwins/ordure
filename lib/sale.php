<?php

class Sale {

  static function addRoutes($f3) {
    $f3->route("GET|HEAD /sale/new", 'Sale->create');
    $f3->route("GET|HEAD /sale/@sale", 'Sale->display');
    $f3->route("GET|HEAD /sale/@sale/json", 'Sale->json');
    $f3->route("POST /sale/@sale/add-item [ajax]", 'Sale->add_item');
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
