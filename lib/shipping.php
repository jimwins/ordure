<?php

class Shipping {

  static function fits_in_box($boxes, $items) {
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
        return $size;
      }
    }

    return false;
  }

  static function item_can_ship_free($item) {
    $boxes= [ [ 33, 19, 4 ], [ 20, 13, 10 ], [ 54, 4, 4 ] ];

    return ($item['weight'] < 10 &&
            self::fits_in_box($boxes, [
              [ $item['width'], $item['height'], $item['length'] ]
            ]));
  }

  static function get_base_local_delivery_rate($item_dim, $weight) {
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
      if (Shipping::fits_in_box($sizes, $item_dim)) {
        return $base[$name];
      }
    }

    return false;
  }

  static function get_shipping_rate($item_dim, $weight, $hazmat, $total) {
    $extra= 0; //$hazmat ? 10 : 0;

    //error_log("getting rate for " . json_encode($item_dim) . " weighing $weight\n");

    $economy_boxes= [
      [ 9, 5, 3 ],
      [ 5, 5, 3.5 ],
      [ 12.25, 3, 3 ],
    ];

    if ($weight < 0.9 && !$hazmat &&
        Shipping::fits_in_box($economy_boxes, $item_dim))
    {
      return ($total > 79) ? 0 : 5.99;
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
      [ 20.25, 13.25, 10.25 ],
      [ 22, 18, 6 ],
      [ 33, 19, 4.5 ],
      [ 54, 4, 4 ],
    ];

    if ($weight < 10 && Shipping::fits_in_box($extra_small_boxes, $item_dim)) {
      return $extra + (($total > 79) ? 0.00 : 9.99);
    } elseif ($weight < 10 && Shipping::fits_in_box($small_boxes, $item_dim)) {
      return $extra + (($total > 79) ? 0.00 : 9.99);
    } elseif ($weight < 20 && Shipping::fits_in_box($medium_boxes, $item_dim)) {
      return $extra + (($total > 79) ? 0.00 : 19.99);
    } else {
      return $extra + (($total > 79) ? 0.00 : 29.99);
    }

    return false;
  }

  static function get_best_shipping_rate($f3, $address,
                                          $item_dim, $weight, $hazmat,
                                          $no_free_shipping,
                                          $order_total)
  {
    \EasyPost\EasyPost::setApiKey($f3->get('EASYPOST_KEY'));

    # width, height, depth, weight (lb), cost
    $all_boxes= [
      [  5,     5,     3.5,  0.13, 0.39 ],
      [  9,     5,     3,    0.21, 0.53 ],
      [  9,     8,     8,    0.48, 0.86 ],
      [ 12.25,  3,     3,    0.19, 1.03 ],
      [ 10,     7,     5,    0.32, 0.82 ],
      [ 12,     9.5,   4,    0.44, 1.01 ],
      [ 12,     9,     9,    0.65, 0.98 ],
      [ 15,    12,     4,    0.81, 1.28 ],
      [ 15,    12,     8,    0.91, 1.59 ],
      [ 18,    16,     4,    1.16, 1.77 ],
      [ 18.75,  3,     3,    0.24, 1.24 ],
      [ 20.25, 13.25, 10.25, 1.21, 2.17 ],
      [ 22,    18,     6,    1.54, 2.32 ],
      [ 33,    19,     4.5,  2.09, 4.08 ],
      [ 54,     4,     4,    0.88, 0.00 ],
    ];

    $method= null;
    $best_rate= null;

    /* We always try to check shipping rates, even for orders that qualify for
     * free shipping. */

    if (($box= Shipping::fits_in_box($all_boxes, $item_dim))) {

      error_log("total: $order_total, using box: " . json_encode($box));

      $options= [];
      if ($hazmat) {
        $options['hazmat']= 'LIMITED_QUANTITY';
      }
      $details= [
        'from_address' => [
          'name' => 'Shipping Department',
          'company' => 'Raw Materials Art Supplies',
          'street1' => '645 S Los Angeles St',
          'city' => 'Los Angeles',
          'state' => 'CA',
          'zip' => '90014',
          'phone' => '213-627-7223',
        ],
        'to_address' => [ 'id' => $address->easypost_id ],
        'parcel' => [
          'length' => $box[0],
          'width' => $box[1],
          'height' => $box[2],
          'weight' => ceil(($weight + $box[3]) * 16),
        ],
        'options' => $options,
      ];

      $shipment= \EasyPost\Shipment::create($details);

      error_log("generated shipping rates {$shipment->id}\n");

      foreach ($shipment->rates as $rate) {
        error_log("rate: {$rate->carrier} / {$rate->service}: {$rate->rate}\n");
        if ($hazmat) {
          if (in_array($rate->carrier, [ 'USPS' ]) &&
              $rate->service == 'ParcelSelect' &&
              self::state_in_continental_us($address->state) &&
              self::address_is_po_box($address))
          {
            if (!$best_rate || $rate->rate < $best_rate) {
              $method= 'default';
              $best_rate= $rate->rate;
            }
          }
        } else {
          if (in_array($rate->carrier, [ 'USPS' ]) &&
              in_array($rate->service, [ 'First', 'Priority' ]))
          {
            if (!$best_rate || $rate->rate < $best_rate) {
              $method= 'default';
              $best_rate= $rate->rate;
              if ($rate->service == 'First') {
                //$method= 'economy';
              }
            }
          }
        }

        if (in_array($rate->carrier, [ 'UPSDAP', 'UPS' ]) &&
            $rate->service == 'Ground')
        {
          if (!$best_rate || $rate->rate < $best_rate) {
            $method= 'default';
            $best_rate= $rate->rate;
          }
        }
      }
    }

    if ($best_rate) {
      error_log("got best rate: $best_rate + $box[4] for $method\n");
    }

    // Free over $79 and in continental US and all items eligible
    if (!$no_free_shipping &&
        $order_total > 79 &&
        self::state_in_continental_us($address->state))
    {
      error_log("qualified for free shipping");
      return [ 0.00, 'default' ];
    }

    return [ $best_rate + $box[4], $method ];
  }

  static function address_is_po_box($address) {
    return preg_match('/po box/i', $address->address1 . $address->address2);
  }

  static function state_in_continental_us($state) {
    return in_array($state, [
      // 'AK',
      'AL',
      'AZ',
      'AR',
      'CA',
      'CO',
      'CT',
      'DE',
      'FL',
      'GA',
      // 'HI',
      'ID',
      'IL',
      'IN',
      'IA',
      'KS',
      'KY',
      'LA',
      'ME',
      'MD',
      'MA',
      'MI',
      'MN',
      'MS',
      'MO',
      'MT',
      'NE',
      'NV',
      'NH',
      'NJ',
      'NM',
      'NY',
      'NC',
      'ND',
      'OH',
      'OK',
      'OR',
      'PA',
      // 'PR',
      'RI',
      'SC',
      'SD',
      'TN',
      'TX',
      'UT',
      'VT',
      'VA',
      'WA',
      'WV',
      'WI',
      'WY',
    ]);
  }
}
