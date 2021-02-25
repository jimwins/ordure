<?php

class Catalog {

  static function addRoutes($f3) {
    $CATALOG= $f3->get('CATALOG');
    $f3->route("GET|HEAD /$CATALOG", 'Catalog->top');
    $f3->route("GET|HEAD /$CATALOG/@dept", 'Catalog->dept');
    $f3->route("GET|HEAD /$CATALOG/@dept/@subdept", 'Catalog->subdept');
    $f3->route("GET|HEAD /$CATALOG/@dept/@subdept/@product", 'Catalog->product');
    $f3->route("GET|HEAD /$CATALOG/@dept/@subdept/@product/*", 'Catalog->product');
    $f3->route("GET|HEAD /$CATALOG/brand", 'Catalog->brands');
    $f3->route("GET|HEAD /$CATALOG/brand/@brand", 'Catalog->brand');

    /* Use Sphinx if it is configured */
    if ($f3->get('sphinx.dsn')) {
      $f3->route("GET|HEAD /$CATALOG/search", 'Catalog->sphinx_search');
    } else {
      $f3->route("GET|HEAD /$CATALOG/search", 'Catalog->search');
    }

    $f3->route("GET|HEAD /$CATALOG/status", 'Catalog->status');

    $f3->route("GET|HEAD /$CATALOG/wordforms.txt", 'Catalog->wordforms');

    $f3->route("GET|HEAD /oembed", 'Catalog->oembed');

    $f3->route("GET|HEAD /$CATALOG/sitemap.xml", 'Catalog->sitemap');
  }

  static function addFunctions($f3) {
    $f3->set('itemList', function (...$items) use ($f3) {
      return self::itemList($f3, $items);
    });
    $f3->set('kit', function ($code) use ($f3) {
      return self::kit($f3, $code);
    });
  }

  static function itemList($f3, $list) {
    $db= $f3->get('DBH');

    $items= [];

    foreach ($list as $code) {
      $item= new DB\SQL\Mapper($db, 'item');
      $item->description= '""';
      $item->media= '""';
      $item->minimum_quantity= 1;
      $item->sale_price= "(SELECT sale_price(scat_item.retail_price,
                           scat_item.discount_type,
                           scat_item.discount) FROM scat_item WHERE scat_item.code = item.code)";
      $item->stocked= '(SELECT IF(stock > 0, stock, 0) + minimum_quantity
                          FROM scat_item WHERE item.code = scat_item.code)';
      $item->is_dropshippable= '(SELECT is_dropshippable
                             FROM scat_item WHERE item.code = scat_item.code)';
      $item->load(array('code = ?', $code));
      $items[]= $item;
    }

    $f3->set('items', $items);

    echo Template::instance()->render("catalog-item-list.html");
  }

  static function kit($f3, $code) {
    $db= $f3->get('DBH');

    $items= [];

    $item= new DB\SQL\Mapper($db, 'item');
    $item->sale_price= "IFNULL((SELECT sale_price(scat_item.retail_price,
                         scat_item.discount_type,
                         scat_item.discount) FROM scat_item WHERE scat_item.code = item.code), retail_price)";
    $item->load(array('code = ?', $code));

    $f3->set('kit', $item);

    $q= "SELECT item.id, code, name, retail_price, kit_item.quantity,
                (SELECT minimum_quantity
                   FROM scat_item
                  WHERE item.code = scat_item.code) minimum_quantity,
                (SELECT IF(stock > 0, stock, 0)
                   FROM scat_item WHERE item.code = scat_item.code) stocked
           FROM kit_item
           JOIN item ON kit_item.item_id = item.id
          WHERE kit_id = ?";
    $items= $db->exec($q, [ $item->id ]);

    $f3->set('items', $items);

    echo Template::instance()->render("catalog-kit.html");
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

  function find_products($f3, $where, $options) {
    $db= $f3->get('DBH');

    $product= new DB\SQL\Mapper($db, 'product');
    $product->brand_name= '(SELECT name
                              FROM brand
                             WHERE brand = brand.id)';
    $product->media= '(SELECT JSON_ARRAYAGG(JSON_OBJECT("id", image.id,
                                                        "uuid", image.uuid,
                                                        "name", image.name,
                                                        "alt_text", image.alt_text,
                                                        "width", image.width,
                                                        "height", image.height,
                                                        "ext", image.ext))
                        FROM product_to_image
                        LEFT JOIN image ON image.id = product_to_image.image_id
                       WHERE product_to_image.product_id = product.id
                       GROUP BY product.id)';
    $product->stocked= '(SELECT SUM(IF(stock > 0, stock, 0)) + SUM(minimum_quantity)
                           FROM item
                           JOIN scat_item ON item.code = scat_item.code
                          WHERE item.product = product.id
                            AND item.active)';
    $product->is_dropshippable= '(SELECT SUM(is_dropshippable)
                           FROM item
                           JOIN scat_item ON item.code = scat_item.code
                          WHERE item.product = product.id
                            AND item.active)';

    $ds= \Sale::can_dropship($f3) ? '!!is_dropshippable,' : '';
    $products= $product->find($where, $options);

    foreach ($products as &$product) {
      $product['slug']= Catalog::getProductSlug($f3, $product['id']);
      $product->media= json_decode($product->media, true);
      if (!$product->media && $product->image) {
        $product->media= [ [ 'src' => $product->image,
                             'alt_text' => $product->name ] ];
      }
    }

    return $products;
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

    $products= $this->find_products(
      $f3,
      [ 'department = ? AND active', $dept->id ],
      [ 'order' => "!!IFNULL(stocked,0),$ds !active, brand_name, name" ]
    );

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
    $product->media= '(SELECT JSON_ARRAYAGG(JSON_OBJECT("id", image.id,
                                                        "uuid", image.uuid,
                                                        "name", image.name,
                                                        "alt_text", image.alt_text,
                                                        "width", image.width,
                                                        "height", image.height,
                                                        "ext", image.ext))
                        FROM product_to_image
                        LEFT JOIN image ON image.id = product_to_image.image_id
                       WHERE product_to_image.product_id = product.id
                       GROUP BY product.id)';
    $product->load(array('slug = ? AND department = ?',
                         $f3->get('PARAMS.product'),
                         $dept->id));
    $product->media= json_decode($product->media, true);
    if (!$product->media && $product->image) {
      $product->media= [ [ 'src' => $product->image,
                           'alt_text' => $product->name ] ];
    }
    $f3->set('product', $product);

    $active= "";

    if (!$product->active) {
      $f3->error(404);
    }
    $active= " AND active";

    $pq= \Sale::can_ship($f3) || \Sale::can_pickup($f3) ? 'scat_item.purchase_quantity' : 'scat_item.is_dropshippable';

    $q= "SELECT item.id, item.code, item.name, item.short_name, variation,
                '' description,
                (SELECT COUNT(*) FROM item_to_image WHERE item_id = item.id)
                  media,
                IFNULL(scat_item.retail_price, item.retail_price) retail_price,
                $pq purchase_quantity,
                length, width, height, weight,
                sale_price(scat_item.retail_price,
                           scat_item.discount_type,
                           scat_item.discount) sale_price,
                scat_item.discount_type, scat_item.discount,
                IF(stock > 0, stock, 0) stocked,
                minimum_quantity, is_dropshippable,
                item.prop65, item.oversized, item.hazmat,
                thumbnail, active
           FROM item
           LEFT JOIN scat_item ON scat_item.code = item.code
          WHERE product = ? $active
          ORDER BY variation, !active,
                   IF(minimum_quantity OR stocked > 0, 0, 1), code";

    $items= $db->exec($q, $product['id']);

    $variations= array();
    foreach ($items as $item) {
      @$variations[$item['variation']]++;
    }

    uksort($variations, 'strnatcasecmp');

    $f3->set('items', $items);
    $f3->set('variations', $variations);

    if ($args['*']) {
      // sometimes 'product' arg leaks into '*'?
      $code= preg_replace("!^{$args['product']}/!", '', $args['*']);

      $item= new DB\SQL\Mapper($db, 'item');
      $item->description= '""';
      $item->sale_price= "(SELECT sale_price(scat_item.retail_price,
                           scat_item.discount_type,
                           scat_item.discount) FROM scat_item WHERE scat_item.code = item.code)";
      $item->media= '(SELECT JSON_ARRAYAGG(JSON_OBJECT("id", image.id,
                                                          "uuid", image.uuid,
                                                          "name", image.name,
                                                          "alt_text", image.alt_text,
                                                          "width", image.width,
                                                          "height", image.height,
                                                          "ext", image.ext))
                          FROM item_to_image
                          LEFT JOIN image ON image.id = item_to_image.image_id
                         WHERE item_to_image.item_id = item.id
                         GROUP BY item.id)';
      $item->minimum_quantity= '(SELECT minimum_quantity
                             FROM scat_item WHERE item.code = scat_item.code)';
      $item->stocked= '(SELECT IF(stock > 0, stock, 0) + minimum_quantity
                             FROM scat_item WHERE item.code = scat_item.code)';
      $item->is_dropshippable= '(SELECT is_dropshippable
                             FROM scat_item WHERE item.code = scat_item.code)';
      $item->load(array('code = ?', $code))
        or $f3->error(404, "Item not found.");
      $item->media= json_decode($item->media, true);
      $f3->set('featured_item', $item);
    }

    $f3->set('EXTRA_HEAD', '<link rel="alternate" type="application/json+oembed" href="http://' . $_SERVER['HTTP_HOST'] . $f3->get('BASE') . '/oembed?url=' . urlencode('http://' . $_SERVER['HTTP_HOST'] . $f3->get('URI') . '') . '&format=json" title="oEmbed Profile" />');

    $f3->set('PAGE',
             array('title' => "$product[name] by $product[brand_name] - Raw Materials Art Supplies"));

    echo Template::instance()->render('catalog-product.html');
  }

  function brands($f3, $args) {
    $db= $f3->get('DBH');

    $brand= new DB\SQL\Mapper($db, 'brand');
    $brands= $brand->find(array('active = 1'), array('order' => 'name'));
    $f3->set('brands', $brands);

    $dept= new DB\SQL\Mapper($db, 'department');
    $departments= $dept->find(array('parent=?', 0),
                              array('order' => 'name'));
    $f3->set('departments', $departments);

    echo Template::instance()->render('catalog-brands.html');
  }

  function brand($f3, $args) {
    $db= $f3->get('DBH');

    $brand= new DB\SQL\Mapper($db, 'brand');
    $brand->products= '(SELECT COUNT(*)
                          FROM product
                         WHERE brand = brand.id
                           AND product.active)';

    $brand->load(array('slug = ?', $f3->get('PARAMS.brand')))
      or $f3->error(404);

    $f3->set('brand', $brand->cast());

    $dept= new DB\SQL\Mapper($db, 'department');
    $dept->products= '(SELECT COUNT(*)
                         FROM product
                        WHERE department = department.id
                          AND product.active)';
    $departments= $dept->find(array('parent = 0'),
                              array('order' => 'name'));

    $f3->set('departments', $departments);

    $products= $this->find_products(
      $f3,
      [ 'brand = ? AND active', $brand->id ],
      [ 'order' => '!!IFNULL(stocked,0), !active, brand_name, name' ]
    );

    $f3->set('products', $products);

    $slug= substr_replace($f3->get('URI'), '', 0, strlen($f3->get('BASE')) + 1);
    $page= new DB\SQL\Mapper($db, 'page');
    $page->load(array('slug=?', $slug));

    if (!$page->title) {
      $page->title= $brand->name . ' - Raw Materials Art Supplies';
    }
    if (!$page->slug) {
      $page->slug= $slug;
    }

    $f3->set('PAGE', $page);

    echo Template::instance()->render('catalog-brand.html');
  }

  function oembed($f3, $args) {
    $db= $f3->get('DBH');

    $url= $f3->get('REQUEST.url');

    if ($f3->exists('REQUEST.type') && $f3->get('REQUEST.type') != 'json') {
      $f3->error(501);
    }

    $cat= $f3->get('CATALOG');
    $base= $f3->get('BASE');
    if (preg_match("!^https?://[-a-z.]+$base/$cat/([-a-z.]+)/([-a-z.]+)/([-a-z.]+)!i",
                   $url, $m)) {
      $f3->set('PARAMS.dept', $m[1]);
      $f3->set('PARAMS.subdept', $m[2]);
      $f3->set('PARAMS.product', $m[3]);
    } else {
      $f3->error(500, "Couldn't figure out product for link.");
    }

    $dept= new DB\SQL\Mapper($db, 'department');
    $dept->products= '(SELECT COUNT(*)
                         FROM product
                        WHERE department = department.id)';

    $dept->load(array('slug = ? AND parent = 0', $f3->get('PARAMS.dept')))
      or $f3->error(404);

    if ($dept->dry()) {
      $f3->error(404);
    }

    $f3->set('dept', $dept->cast());

    $departments= $dept->find(array('parent=?', $dept->id),
                              array('order' => 'name'));

    $f3->set('departments', $departments);

    $dept->load(array('slug=?', $f3->get('PARAMS.subdept')))
      or $f3->error(404);

    $f3->set('subdept', $dept);
    if ($dept->dry()) {
      $f3->error(404);
    }

    $product= new DB\SQL\Mapper($db, 'product');
    $product->brand_name = '(SELECT name
                               FROM brand
                              WHERE brand = brand.id)';
    $product->load(array('slug=?', $f3->get('PARAMS.product')));
    $f3->set('product', $product);

    if ($product->dry()) {
      $f3->error(404);
    }

    $active= " AND active";

    $q= "SELECT item.id, item.code, item.name, item.short_name, variation,
                IFNULL(scat_item.retail_price, item.retail_price) retail_price,
                IFNULL(scat_item.purchase_quantity, item.purchase_quantity) purchase_quantity,
                length, width, height, weight,
                sale_price(scat_item.retail_price,
                           scat_item.discount_type,
                           scat_item.discount) sale_price,
                discount_type, discount,
                IF(stock > 0, stock, 0) stocked,
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

  function sitemap($f3, $args) {
    $db= $f3->get('DBH');

    $base= $f3->get('BASE');
    $cat= $f3->get('CATALOG');

    $q= "SELECT CONCAT((SELECT CONCAT(dept.slug, '/', subdept.slug)
                          FROM department dept
                          JOIN department subdept
                            ON dept.id = subdept.parent
                         WHERE product.department = subdept.id), '/', slug)
                  AS slug,
                DATE_FORMAT(modified, '%Y-%m-%dT%TZ') AS modified
           FROM product
          WHERE active
          ORDER BY 1";

    $urls= $db->exec($q);

    header("Content-type: application/xml");
    echo '<?xml version="1.0" encoding="UTF-8"?>', "\n",
         '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
    foreach ($urls as $url) {
      echo "  <url>\n",
           '    <loc>https://', $_SERVER['HTTP_HOST'], '/',
                                $cat, "/", $url['slug'], "</loc>\n",
           '    <lastmod>', $url['modified'], "</lastmod>\n",
           "  </url>\n";
      echo $wordform['wordform'], "\n";
    }
    echo '</urlset>', "\n";
  }

  function status($f3, $args) {
    $file= '/tmp/last-loaded-prices';
    if (file_exists($file) &&
        filemtime($file) > time() - (15 * 60)) {
      echo "Prices are current.";
      return;
    }
    echo "ERROR: Prices are not current.";
  }

  function wordforms($f3, $args) {
    $db= $f3->get('DBH');

    $q= "SELECT CONCAT(source, ' => ', dest) wordform FROM wordform";
    $wordforms= $db->exec($q);

    foreach ($wordforms as $wordform) {
      echo $wordform['wordform'], "\n";
    }
  }

  function search($f3, $args) {
    $db= $f3->get('DBH');

    $term= '';
    if ($f3->exists('REQUEST.q')) {
      $term= $f3->get('REQUEST.q');
    }

    if ($term && preg_match('!^[-A-Z0-9/.]+$!i', trim($term))) {
      $item= new DB\SQL\Mapper($db, 'item');
      $item->load(array('code=?', trim($term)));

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
            LIMIT 100
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

    $query= '';
    if ($f3->exists('REQUEST.q')) {
      $query= $f3->get('REQUEST.q');
    }

    /* Check if this a direct match for an item code */
    if ($query && preg_match('!^[-A-Z0-9/.]+$!i', $query)) {
      $item= new DB\SQL\Mapper($db, 'item');
      $item->load(array('code=? AND active', $query));

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
                           $product->slug . '/' . $item->code, false);
            }
          }
        }
      }
    }

    if ($query) {
      $sph= new DB\SQL($f3->get('sphinx.dsn'),
                       $f3->get('sphinx.user'),
                       $f3->get('sphinx.password'),
                       array(
                         \PDO::MYSQL_ATTR_LOCAL_INFILE => true,
                         \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                       ));

     $index= $f3->get('sphinx.index') ?: 'ordure';

     $retry= false;

retry:
     $q= "SELECT *,WEIGHT() weight
            FROM $index
           WHERE match(?)
           LIMIT 100
          OPTION ranker=expr('sum(lcs*user_weight)*1000+bm25+if(items, 4000, 0)')";
      
      $res= $sph->exec($q, $query);

      if (count($res)) {
        $ids= array_map(function ($i) { return $i['id']; }, $res);

        /* This is a gross way of maintaining the order */
        $products= $this->find_products(
          $f3,
          [ 'id IN (' . join(',', $ids) . ')' ],
          [ 'order' => 'FIELD(id, "' . join('","', $ids) . '")' ]
        );
      } elseif (!$retry) {
        $terms= preg_split('/\\s+/', trim($query));
        $new_terms= [];
        $changed= 0;

        foreach ($terms as $term) {
          $res= $sph->exec("CALL SUGGEST(?,?)", [ $term, $index ]);
          if (count($res)) {
            $suggest= $res[0]['suggest'];

            if (strcasecmp($suggest, $term)) {
              $changed++;
            }
            $new_terms[]= $suggest;
          } else {
            $changed++;
          }
        }

        if ($changed && count($new_terms)) {
          $query= join(' ', $new_terms);
          $f3->set('changed_query', $query);
          $retry= true;
          goto retry;
        }
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
