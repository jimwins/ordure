<?php
require 'lib/common.php';

$dept= $subdept= $departments=
  $product= $products= $items= $variations= array();

list(, $i_dept, $i_subdept, $i_product)= explode('/', $_SERVER['PATH_INFO']);

if ($i_dept) {
  $slug= $db->escape($i_dept);
  $q= "SELECT id, name, slug
         FROM department
        WHERE parent IS NULL
          AND slug = '$slug'";
  $dept= $db->get_one_assoc($q)
    or die($db->error);
  /// XXX error
}

if ($dept) {
  $parent_check= "parent = $dept[id]";
} else {
  $parent_check= "parent IS NULL";
}

$q= "SELECT id, slug, name, parent,
            (SELECT COUNT(*)
               FROM product
              WHERE department = department.id) products
       FROM department
      WHERE $parent_check
      ORDER BY IFNULL(parent, 0), IF(parent IS NULL, -1, pos), name";
$r= $db->query($q);

$departments= array();
while ($row= $r->fetch_assoc()) {
  $departments[]= $row;
}

if ($i_subdept) {
  $slug= $db->escape($i_subdept);
  $q= "SELECT id, name, slug
         FROM department
        WHERE parent = $dept[id]
          AND slug = '$slug'";
  $subdept= $db->get_one_assoc($q)
    or die($db->error);
}

if ($i_product) {
  $q= "SELECT product.id, product.slug, product.name,
              brand.name brand, description, image
         FROM product
         LEFT JOIN brand ON product.brand = brand.id
        WHERE product.slug = '" . $db->escape($i_product) . "'
          AND department = $subdept[id]
        ORDER BY brand.name, name";

  $product= $db->get_one_assoc($q)
    or die($db->error);

  $q= "SELECT item.id, item.code, item.name, item.short_name, variation,
              unit_of_sale,
              IFNULL(scat_item.retail_price, item.retail_price) retail_price,
              purchase_qty,
              length, width, height, weight,
              scat.sale_price(scat_item.retail_price,
                              scat_item.discount_type,
                              scat_item.discount) sale_price,
              stock stocked,
              thumbnail
         FROM item
         LEFT JOIN scat_item ON scat_item.code = item.code
        WHERE product = $product[id]
        ORDER BY variation, code";

  $r= $db->query($q) or die($db->error);
  // XXX errors

  while ($row= $r->fetch_assoc()) {
    $variations[$row['variation']]++;
    $items[]= $row;
  }

} elseif ($subdept) {
  $q= "SELECT product.name, product.slug, brand.name brand, inactive
         FROM product
         LEFT JOIN brand ON product.brand = brand.id
        WHERE department = $subdept[id]
        ORDER BY inactive, brand.name, name";
  
  $r= $db->query($q) or die($db->error);
  // XXX errors
  while ($row= $r->fetch_assoc()) {
    $products[]= $row;
  }
}

$title= 'Raw Materials Art Supplies';
$page_slug= 'art-supplies';
if ($product) {
  $title= "$product[name] by $product[brand] - $title";
  $page_slug.= "/$dept[slug]/$subdept[slug]/$product[slug]";
} elseif ($subdept) {
  $title= "$subdept[name] - $title";
  $page_slug.= "/$dept[slug]/$subdept[slug]";
} elseif ($dept) {
  $title= "$dept[name] - $title";
  $page_slug.= "/$dept[slug]";
}

head($title);
?>
<div class="alert alert-info">
  <strong>Welcome</strong> to the beginnings of our new online store. Please
  feel free to look around while we continue construction.
</div>
<div class="col-sm-3">
  <div class="panel panel-default">
    <div class="panel-heading">
      <h3 class="panel-title">Departments</h3>
    </div>
    <div class="list-group">
<?
if ($dept) {
  echo '<a class="list-group-item" href="' . href('art-supplies') . '"><b><span class="pull-right glyphicon glyphicon-chevron-up"></span> Back to Top</b></a>';
  echo '</div>';
  echo '<div class="panel-heading"><h3 class="panel-title">',
       ashtml($dept['name']),
       '</h3></div>';
  echo '<div class="list-group">';
}

foreach($departments as $row) {
  $active= ($row['slug'] == $i_subdept) ? 'active' : '';
  echo '<a class="list-group-item ' . $active . '" href="' .
       href('art-supplies/', $dept['slug'], ($dept ? '/' : ''),
            $row['slug']), '">',
       '<span class="badge pull-right">', $row['products'], '</span>',
       ashtml($row['name']), '</a>';
}
?>
    </div>
  </div>
</div>
<div class="col-sm-9">
<?
if ($product) {
?>
  <ol class="breadcrumb">
    <li><a href="<?=href('art-supplies')?>">Art Supplies</a></li>
    <li><a href="<?=href('art-supplies/', $dept['slug'])?>"><?=ashtml($dept['name'])?></a></li>
    <li><a href="<?=href('art-supplies/', $dept['slug'], '/', $subdept['slug'])?>"><?=ashtml($subdept['name'])?></a></li>
    <li class="active"><?=ashtml($product['name'])?></li>
  </ol>

  <div class="page-header">
    <h1>
      <?=ashtml($product['name'])?>
      <small><?=ashtml($product['brand'])?></small>
    </h1>
  </div>
  <div class="col-sm-9">
    <?=$product['description']?>
<?if (count($items) == 1) { $item= $items[0]; ?>
    <dl class="dl-horizontal">
      <dt>List Price</dt><dd>$<?=$item['retail_price']?></dd>
<?if ($item['sale_price']) {?>
      <dt class="text-primary"><b>Sale Price</b></dt>
      <dd class="text-primary"><b>$<?=$item['sale_price']?></b></dd>
<?}?>
<?if ($item['stocked']) {?>
      <dd class="text-primary">Available in store.</dd>
<?} else {?>
      <dd>Available by special order.</dd>
<?}?>
    </dl>
<?}?>
  </div>
<?if ($product['image']) {?>
  <div class="col-sm-3 thumbnail">
    <?=img($product['image'], 240)?>
  </div>
<?}?>
<?
if (count($items) > 1) {
  if (count($variations) > 1) {?>
<ul class="nav nav-tabs">
<?
  $c= 0;
  foreach ($variations as $var => $num) {
    $c++;
    echo '<li',
         ($c == 1) ? ' class="active"' : '',
         '><a href="#c', $c, '" data-toggle="tab">',
         ashtml($var), '</a></li>';
  }
?>
</ul>
<div class="tab-content">
<?
  }
$c= 0;
foreach ($variations as $var => $num) {
  $c++;
  if (count($variations) > 1) {?>
    <div id="c<?=$c?>" class="tab-pane <?=($c == 1) ? 'active' : ''?>">
<?}?>
  <table class="table table-condensed table-striped">
    <thead>
      <tr>
        <th>Item No.</th><th>Description</th>
        <th>List</th><th>Sale</th>
        <th class="text-center hastip" title="Whether this item is stocked in our store or only available via special order." data-placement="left">Available in Store</th>
      </tr>
    </thead>
    <tbody>
<?foreach ($items as $item) {
    if (strcmp($item['variation'], $var)) continue;
?>
      <tr> 
        <td><?=ashtml($item['code'])?></td>
        <td><?=ashtml($item['short_name'])?></td>
        <td>$<?=ashtml($item['retail_price'])?></td>
        <td class="text-primary"><strong><?if ($item['sale_price']) {?>$<?=$item['sale_price']?><?}?></strong></td>
        <td class="text-center"><?if ($item['stocked']) {?><span class="glyphicon glyphicon-ok-circle"><?}?></td>
      </tr>
<?}?>
    </tbody>
  </table>
<?if (count($variations) > 1) {?>
  </div><!-- .tab-pane -->
<?}?>
<?}?>
<?if (count($variations) > 1) {?>
  </div><!-- .tab-content -->
<?}
}
?>
<?
} else if ($subdept) {
?>
  <ol class="breadcrumb">
    <li><a href="<?=href('art-supplies')?>">Art Supplies</a></li>
    <li><a href="<?=href('art-supplies/', $dept['slug'])?>"><?=ashtml($dept['name'])?></a></li>
    <li class="active"><?=ashtml($subdept['name'])?></li>
  </ol>

  <div data-slug="<?=$page_slug?>">
    <div class="rendered">
<?
  $page= render_page_contents($db, $page_slug);
  echo $page['rendered'];
?>
    </div><!-- .rendered -->
  </div>

  <table class="table table-striped table-condensed">
    <tbody>
<?
  $class = array('', 'text-muted', 'text-danger');
  foreach ($products as $row) {
    echo '<tr class=' . $class[$row['inactive']] . '><td>' . ashtml($row['brand']) . '</td><td><a href="' . href('art-supplies/', $dept['slug'], '/', $subdept['slug'], '/', $row['slug']) . '">' . ashtml($row['name']) . '</a></td></tr>';
  }
?>
    </tbody>
  </table>
</div>
<?
} else {
  if ($dept) {?>
  <ol class="breadcrumb">
    <li><a href="<?=href('art-supplies')?>">Art Supplies</a></li>
    <li><?=ashtml($dept['name'])?></li>
  </ol>
<?}?>
  <div data-slug="<?=$page_slug?>">
    <div class="rendered">
<?
  $page= render_page_contents($db, $page_slug);
  echo $page['rendered'];
?>
    </div><!-- .rendered -->
  </div>
<?}?>
</div><!-- .col-sm-9 -->
<?
foot();
?>
<script>
$(function() {
  $('.hastip').tooltip();
});
</script>
<script>
var BASE= '<?=BASE?>';
</script>
<script src="<?=BASE?>admin/admin.js"></script>
