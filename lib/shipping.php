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
        return true;
      }
    }

    return false;
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
    $extra= $hazmat ? 10 : 0;

    $economy_boxes= [
      [ 9, 5, 3 ],
      [ 5, 5, 3.5 ],
      [ 12.25, 3, 3 ],
    ];

    if ($weight < 0.9 && !$hazmat &&
        Shipping::fits_in_box($economy_boxes, $item_dim))
    {
      return ($total > 69) ? 0 : 5.99;
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

    if ($weight < 10 && Shipping::fits_in_box($extra_small_boxes, $item_dim)) {
      return $extra + (($total > 99) ? 0.00 : 9.99);
    } elseif ($weight < 10 && Shipping::fits_in_box($small_boxes, $item_dim)) {
      return $extra + (($total > 99) ? 0.00 : 9.99);
    } elseif ($weight < 20 && Shipping::fits_in_box($medium_boxes, $item_dim)) {
      return $extra + (($total > 199) ? 0.00 : 19.99);
    } elseif ($weight < 30) {
      return $extra + (($total > 399) ? 0.00 : 29.99);
    }

    return false;
  }
}
