<?php

class Catalog {

  static function addRoutes($f3) {
    $CATALOG= $f3->get('CATALOG');
    $f3->route("GET|HEAD /$CATALOG", 'Catalog->top');
    $f3->route("GET|HEAD /$CATALOG/@dept", 'Catalog->dept');
    $f3->route("GET|HEAD /$CATALOG/@dept/@subdept", 'Catalog->subdept');
    $f3->route("GET|HEAD /$CATALOG/@dept/@subdept/@product", 'Catalog->product');

    /* Use Sphinx if it is configured */
    if ($f3->get('sphinx.dsn')) {
      $f3->route("GET|HEAD /$CATALOG/search", 'Catalog->sphinx_search');
    } else {
      $f3->route("GET|HEAD /$CATALOG/search", 'Catalog->search');
    }

    $f3->route("GET|HEAD /oembed", 'Catalog->oembed');
  }


  static function amount($d) {
    return ($d < 0 ? '(' : '') . '$' . sprintf("%.2f", abs($d)) . ($d < 0 ? ')' : '');
  }

  function top($f3) {
    $db= $f3->get('DBH');

    $dept= new DB\SQL\Mapper($db, 'department');
    $dept->products= '(SELECT COUNT(*)
                         FROM product
                        WHERE department = department.id)';
    $departments= $dept->find(array('parent = 0'),
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

    $dept->load(array('slug = ? AND parent = 0', $f3->get('PARAMS.dept')))
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
    if (!$page->slug) {
      $page->slug= $slug;
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

    $dept->load(array('slug = ? AND parent = 0', $f3->get('PARAMS.dept')))
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
    $product->stocked= '(SELECT SUM(stock) + SUM(minimum_quantity)
                           FROM item
                           JOIN scat_item ON item.code = scat_item.code
                          WHERE item.product = product.id)';

    $products= $product->find(array('department = ?' .
                                    ($f3->get('ADMIN') ?
                                     '' :
                                     ' AND active'), 
                                    $dept->id),
                              array('order' =>
                                      '!active, brand_name, name'));

    $f3->set('products', $products);

    $slug= substr_replace($f3->get('URI'), '', 0, strlen($f3->get('BASE')) + 1);
    $page= new DB\SQL\Mapper($db, 'page');
    $page->load(array('slug=?', $slug));

    if (!$page->title) {
      $page->title= $dept->name . ' - Raw Materials Art Supplies';
    }
    if (!$page->slug) {
      $page->slug= $slug;
    }

    $f3->set('PAGE', $page);

    echo Template::instance()->render('catalog-dept.html');
  }

  static function getProductSlug($f3, $id) {
    $db= $f3->get('DBH');

    $product= new DB\SQL\Mapper($db, 'product');
    $product->load(array('id=?', $id));
    if (!$product || !$product->active) return false;

    $dept= new DB\SQL\Mapper($db, 'department');
    $dept->load(array('id=?', $product->department));
    if (!$dept) return false;
    $subdept_slug= $dept->slug;

    $dept->load(array('id=?', $dept->parent));
    if (!$dept) return false;

    return $dept->slug . '/' . $subdept_slug . '/' . $product->slug;
  }

  function product($f3, $args) {
    $db= $f3->get('DBH');

    $dept= new DB\SQL\Mapper($db, 'department');
    $dept->products= '(SELECT COUNT(*)
                         FROM product
                        WHERE department = department.id)';

    $dept->load(array('slug = ? AND parent = 0', $f3->get('PARAMS.dept')))
      or $f3->error(404);

    $f3->set('dept', $dept->cast());

    $departments= $dept->find(array('parent=?', $dept->id),
                              array('order' => 'name'));

    $f3->set('departments', $departments);

    $dept->load(array('slug = ? AND parent = ?',
                      $f3->get('PARAMS.subdept'),
                      $dept->id))
      or $f3->error(404);

    $f3->set('subdept', $dept);

    $product= new DB\SQL\Mapper($db, 'product');
    $product->brand_name = '(SELECT name
                               FROM brand
                              WHERE brand = brand.id)';
    $product->load(array('slug = ? AND department = ?',
                         $f3->get('PARAMS.product'),
                         $dept->id));
    $f3->set('product', $product);

    $active= "";

    if (!$f3->get('ADMIN')) {
      if (!$product->active) {
        $f3->error(404);
      }
      $active= " AND active";
    }

    $q= "SELECT item.id, item.code, item.name, item.short_name, variation,
                unit_of_sale,
                IFNULL(scat_item.retail_price, item.retail_price) retail_price,
                IFNULL(scat_item.purchase_quantity, item.purchase_quantity) purchase_quantity,
                length, width, height, weight,
                sale_price(scat_item.retail_price,
                           scat_item.discount_type,
                           scat_item.discount) sale_price,
                discount_type, discount,
                stock stocked,
                minimum_quantity,
                thumbnail, active
           FROM item
           LEFT JOIN scat_item ON scat_item.code = item.code
          WHERE product = ? $active
          ORDER BY variation, !active,
                   IF(minimum_quantity OR stocked, 0, 1), code";

    $items= $db->exec($q, $product['id']);

    $variations= array();
    foreach ($items as $item) {
      @$variations[$item['variation']]++;
    }

    uksort($variations, 'strnatcasecmp');

    $f3->set('items', $items);
    $f3->set('variations', $variations);

    $f3->set('EXTRA_HEAD', '<link rel="alternate" type="application/json+oembed" href="http://' . $_SERVER['HTTP_HOST'] . $f3->get('BASE') . '/oembed?url=' . urlencode('http://' . $_SERVER['HTTP_HOST'] . $f3->get('URI') . '') . '&format=json" title="oEmbed Profile" />');

    $f3->set('PAGE',
             array('title' => "$product[name] by $product[brand_name] - Raw Materials Art Supplies"));

    echo Template::instance()->render('catalog-product.html');
  }

  function oembed($f3, $args) {
    $db= $f3->get('DBH');

    $url= $f3->get('REQUEST.url');

    if ($f3->exists('REQUEST.type') && $f3->get('REQUEST.type') != 'json') {
      $f3->error(501);
    }

    $cat= $f3->get('CATALOG');
    $base= $f3->get('BASE');
    if (preg_match("!^http://[-a-z.]+$base/$cat/([-a-z.]+)/([-a-z.]+)/([-a-z.]+)!i",
                   $url, $m)) {
      $f3->set('PARAMS.dept', $m[1]);
      $f3->set('PARAMS.subdept', $m[2]);
      $f3->set('PARAMS.product', $m[3]);
    }

    $dept= new DB\SQL\Mapper($db, 'department');
    $dept->products= '(SELECT COUNT(*)
                         FROM product
                        WHERE department = department.id)';

    $dept->load(array('slug = ? AND parent = 0', $f3->get('PARAMS.dept')))
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

    $active= "";
    if (!$f3->get('ADMIN')) {
      $active= " AND active";
    }

    $q= "SELECT item.id, item.code, item.name, item.short_name, variation,
                unit_of_sale,
                IFNULL(scat_item.retail_price, item.retail_price) retail_price,
                IFNULL(scat_item.purchase_quantity, item.purchase_quantity) purchase_quantity,
                length, width, height, weight,
                sale_price(scat_item.retail_price,
                           scat_item.discount_type,
                           scat_item.discount) sale_price,
                discount_type, discount,
                stock stocked,
                thumbnail, active
           FROM item
           LEFT JOIN scat_item ON scat_item.code = item.code
          WHERE product = ? $active
          ORDER BY variation, !active, IF(stocked IS NULL, 1, 0), code";

    $items= $db->exec($q, $product['id']);

    $variations= array();
    foreach ($items as $item) {
      @$variations[$item['variation']]++;
    }

    $f3->set('items', $items);
    $f3->set('variations', $variations);

    echo Template::instance()->render('catalog-oembed.json',
                                      'application/json');
  }

  function search($f3, $args) {
    $db= $f3->get('DBH');

    $term= '';
    if ($f3->exists('REQUEST.q')) {
      $term= $f3->get('REQUEST.q');
    }

    if ($term && preg_match('!^[-A-Z0-9/.]+$!i', $term)) {
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
                  brand.name brand_name, active
             FROM product
             LEFT JOIN brand ON product.brand = brand.id
             JOIN department subdept ON product.department = subdept.id
            WHERE MATCH(product.name, description)
                  AGAINST(? IN NATURAL LANGUAGE MODE)
              AND active
            -- ORDER BY !active, brand.name, name";
      
      $products= $db->exec($q, $term);

      $f3->set('products', $products);
    }

    $dept= new DB\SQL\Mapper($db, 'department');
    $dept->products= '(SELECT COUNT(*)
                         FROM product
                        WHERE department = department.id)';
    $departments= $dept->find(array('parent = 0'),
                              array('order' => 'name'));

    $f3->set('departments', $departments);

    $page= array('title' => "Search results @ Raw Materials Art Supplies");
    $f3->set('PAGE', $page);

    echo Template::instance()->render('catalog-search.html');
  }

  function sphinx_search($f3, $args) {
    $db= $f3->get('DBH');

    $term= '';
    if ($f3->exists('REQUEST.q')) {
      $term= $f3->get('REQUEST.q');
    }

    /* Check if this a direct match for an item code */
    if ($term && preg_match('!^[-A-Z0-9/.]+$!i', $term)) {
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
      $sph= new DB\SQL($f3->get('sphinx.dsn'),
                       $f3->get('sphinx.user'),
                       $f3->get('sphinx.password'),
                       array(
                         \PDO::MYSQL_ATTR_LOCAL_INFILE => true,
                         \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                       ));

      $q= "select * from ordure where match(?)"; 
      
      $products= $sph->exec($q, $term);

      /* XXX sphinx doesn't have full slug stored */
      foreach ($products as &$product) {
        $product['slug']= Catalog::getProductSlug($f3, $product['id']);
      }

      $f3->set('products', $products);
    }

    $dept= new DB\SQL\Mapper($db, 'department');
    $dept->products= '(SELECT COUNT(*)
                         FROM product
                        WHERE department = department.id)';
    $departments= $dept->find(array('parent = 0'),
                              array('order' => 'name'));

    $f3->set('departments', $departments);

    $page= array('title' => "Search results @ Raw Materials Art Supplies");
    $f3->set('PAGE', $page);

    echo Template::instance()->render('catalog-search.html');
  }
}
