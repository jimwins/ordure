<?php

class Catalog {

  static function addRoutes($f3) {
    $CATALOG= $f3->get('CATALOG');
    $f3->route("GET /$CATALOG", 'Catalog->top');
    $f3->route("GET /$CATALOG/@dept", 'Catalog->dept');
    $f3->route("GET /$CATALOG/@dept/@subdept", 'Catalog->subdept');
    $f3->route("GET /$CATALOG/@dept/@subdept/@product", 'Catalog->product');
    $f3->route("GET /$CATALOG/search", 'Catalog->search');
  }

  function top($f3) {
    $db= $f3->get('DBH');

    $dept= new DB\SQL\Mapper($db, 'department');
    $dept->products= '(SELECT COUNT(*)
                         FROM product
                        WHERE department = department.id)';
    $departments= $dept->find(array('parent IS NULL'),
                              array('order' => 'name'));

    $f3->set('departments', $departments);

    $page= new DB\SQL\Mapper($db, 'page');
    $page->load(array('slug=?', $f3->get('CATALOG')))
      or $f3->error(404);

    if (!$page->title) {
      $page->title= 'Shop Online at Raw Materials Art Supplies';
    }

    $f3->set('PAGE', $page);

    echo Template::instance()->render('catalog-dept.html');
  }

  function dept($f3, $args) {
    $db= $f3->get('DBH');

    $dept= new DB\SQL\Mapper($db, 'department');
    $dept->products= '(SELECT COUNT(*)
                         FROM product
                        WHERE department = department.id)';

    $dept->load(array('slug = ? AND parent IS NULL', $f3->get('PARAMS.dept')))
      or $f3->error(404);

    $f3->set('dept', $dept);

    $departments= $dept->find(array('parent=?', $dept->id),
                              array('order' => 'name'));

    $f3->set('departments', $departments);

    $slug= substr_replace($f3->get('URI'), '', 0, strlen($f3->get('BASE')) + 1);
    $page= new DB\SQL\Mapper($db, 'page');
    $page->load(array('slug=?', $slug));

    if (!$page->title) {
      $page->title= $dept->name . ' - Raw Materials Art Supplies';
    }

    $f3->set('PAGE', $page);

    echo Template::instance()->render('catalog-dept.html');
  }

  function subdept($f3, $args) {
    $db= $f3->get('DBH');

    $dept= new DB\SQL\Mapper($db, 'department');
    $dept->products= '(SELECT COUNT(*)
                         FROM product
                        WHERE department = department.id)';

    $dept->load(array('slug = ? AND parent IS NULL', $f3->get('PARAMS.dept')))
      or $f3->error(404);

    $f3->set('dept', $dept->cast());

    $departments= $dept->find(array('parent=?', $dept->id),
                              array('order' => 'name'));

    $f3->set('departments', $departments);

    $dept->load(array('slug=?', $f3->get('PARAMS.subdept')))
      or $f3->error(404);

    $f3->set('subdept', $dept);

    $product= new DB\SQL\Mapper($db, 'product');
    $product->brand_name= '(SELECT name
                              FROM brand
                             WHERE brand = brand.id)';
    $product->stocked= '(SELECT SUM(stock)
                           FROM item
                           JOIN scat_item ON item.code = scat_item.code
                          WHERE item.product = product.id)';

    $products= $product->find(array('department=?', $dept->id),
                              array('order' =>
                                      'inactive = 2, brand_name, name'));

    $f3->set('products', $products);

    $slug= substr_replace($f3->get('URI'), '', 0, strlen($f3->get('BASE')) + 1);
    $page= new DB\SQL\Mapper($db, 'page');
    $page->load(array('slug=?', $slug));

    if (!$page->title) {
      $page->title= $dept->name . ' - Raw Materials Art Supplies';
    }

    $f3->set('PAGE', $page);

    echo Template::instance()->render('catalog-dept.html');
  }

  function product($f3, $args) {
    $db= $f3->get('DBH');

    $dept= new DB\SQL\Mapper($db, 'department');
    $dept->products= '(SELECT COUNT(*)
                         FROM product
                        WHERE department = department.id)';

    $dept->load(array('slug = ? AND parent IS NULL', $f3->get('PARAMS.dept')))
      or $f3->error(404);

    $f3->set('dept', $dept->cast());

    $departments= $dept->find(array('parent=?', $dept->id),
                              array('order' => 'name'));

    $f3->set('departments', $departments);

    $dept->load(array('slug=?', $f3->get('PARAMS.subdept')))
      or $f3->error(404);

    $f3->set('subdept', $dept);

    $product= new DB\SQL\Mapper($db, 'product');
    $product->brand_name = '(SELECT name
                               FROM brand
                              WHERE brand = brand.id)';
    $product->load(array('slug=?', $f3->get('PARAMS.product')));
    $f3->set('product', $product);

    $q= "SELECT item.id, item.code, item.name, item.short_name, variation,
                unit_of_sale,
                IFNULL(scat_item.retail_price, item.retail_price) retail_price,
                purchase_qty,
                length, width, height, weight,
                sale_price(scat_item.retail_price,
                           scat_item.discount_type,
                           scat_item.discount) sale_price,
                stock stocked,
                thumbnail, inactive
           FROM item
           LEFT JOIN scat_item ON scat_item.code = item.code
          WHERE product = ?
          ORDER BY variation, inactive, IF(stocked IS NULL, 1, 0), code";

    $items= $db->exec($q, $product['id']);

    $variations= array();
    foreach ($items as $item) {
      @$variations[$item['variation']]++;
    }

    $f3->set('items', $items);
    $f3->set('variations', $variations);

    $f3->set('PAGE',
             array('title' => "$product[name] by $product[brand_name] - Raw Materials Art Supplies"));

    echo Template::instance()->render('catalog-product.html');
  }

  function search($f3, $args) {
    $db= $f3->get('DBH');

    $term= '';
    if ($f3->exists('REQUEST.q')) {
      $term= $f3->get('REQUEST.q');
    }

    if ($term && preg_match('!^[-A-Z0-9/]+$!i', $term)) {
      $item= new DB\SQL\Mapper($db, 'item');
      $item->load(array('code=?', $term));

      if (!$item->dry()) {
        $product= new DB\SQL\Mapper($db, 'product');
        $product->load(array('id=?', $item->product));

        if (!$product->dry()) {
          $dept= new DB\SQL\Mapper($db, 'department');
          $dept->load(array('id=?', $product->department));

          if (!$dept->dry()) {
            $subdept_slug= $dept->slug;
            $dept->load(array('id=?', $dept->parent));
            if (!$dept->dry()) {
              $f3->reroute('/'. $f3->get('CATALOG') . '/' .
                           $dept->slug . '/' . $subdept_slug . '/' .
                           $product->slug, false);
            }
          }
        }
      }
    }

    if ($term) {
      $q= "SELECT product.name,
                  (SELECT slug
                     FROM department
                    WHERE subdept.parent = department.id) AS dept,
                  subdept.slug AS subdept, product.slug,
                  brand.name brand_name, inactive
             FROM product
             LEFT JOIN brand ON product.brand = brand.id
             JOIN department subdept ON product.department = subdept.id
            WHERE MATCH(product.name, description)
                  AGAINST(? IN NATURAL LANGUAGE MODE)
              AND inactive != 2
            -- ORDER BY inactive, brand.name, name";
      
      $products= $db->exec($q, $term);

      $f3->set('products', $products);
    }

    $dept= new DB\SQL\Mapper($db, 'department');
    $dept->products= '(SELECT COUNT(*)
                         FROM product
                        WHERE department = department.id)';
    $departments= $dept->find(array('parent IS NULL'),
                              array('order' => 'name'));

    $f3->set('departments', $departments);

    $slug= substr_replace($f3->get('URI'), '', 0, strlen($f3->get('BASE')) + 1);
    $page= new DB\SQL\Mapper($db, 'page');
    $page->load(array('slug=?', $slug));
    $f3->set('PAGE', $page);

    echo Template::instance()->render('catalog-search.html');
  }
}
