<?php
require 'lib/common.php';

list(, $i_dept, $i_subdept, $i_product)= explode('/', $_SERVER['PATH_INFO']);

$dept= $subdept= $departments= $product= $products= $items= array();

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

$q= "SELECT slug, name, parent
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

  $q= "SELECT id, code, name, short_name,
              unit_of_sale, retail_price, purchase_qty,
              length, width, height, weight,
              thumbnail
         FROM item
        WHERE product = $product[id]
        ORDER BY short_name";

  $r= $db->query($q) or die($db->error);
  // XXX errors

  $items= array();
  while ($row= $r->fetch_assoc()) {
    $items[]= $row;
  }

} elseif ($subdept) {
  $q= "SELECT product.name, product.slug, brand.name brand
         FROM product
         LEFT JOIN brand ON product.brand = brand.id
        WHERE department = $subdept[id]
        ORDER BY brand.name, name";
  
  $r= $db->query($q) or die($db->error);
  // XXX errors
  while ($row= $r->fetch_assoc()) {
    $products[]= $row;
  }
}

head();
?>
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
       ashtml($row['name']), '</a>';
}
?>
    </div>
  </div>
</div>
<?
if ($product) {
?>
<div class="col-sm-9">
  <div class="page-header">
    <h1>
      <?=ashtml($product['name'])?>
      <small><?=ashtml($product['brand'])?></small>
    </h1>
  </div>
  <div class="col-sm-9"><?=$product['description']?></div>
<?if ($product['image']) {?>
  <div class="col-sm-3 thumbnail">
    <?=img($product['image'], 240)?>
  </div>
<?}?>
  <table class="table table-condensed table-striped">
    <thead>
      <tr>
        <th>Item No.</th><th>Description</th>
        <th>List</th><th>Sale</th>
        <th>UOM</th><th>Inc</th>
        <th class="text-center">&nbsp;<span class="glyphicon glyphicon-picture"></span>&nbsp;</th>
      </tr>
    </thead>
    <tbody>
<?foreach ($items as $item) {?>
      <tr> 
        <td><?=ashtml($item['code'])?></td>
        <td><?=ashtml($item['short_name'])?></td>
        <td>$<?=ashtml($item['retail_price'])?></td>
        <td></td>
        <td><?=ashtml($item['unit_of_sale'])?></td>
        <td><?=ashtml($item['purchase_qty'])?></td>
        <td class="text-center"><a class="item-thumb" data-original-title="<?=$item['short_name']?>" data-placement="left" data-trigger="hover"><?=img($item['thumbnail'], 32)?></a></td>
      </tr>
<?}?>
    </tbody>
  </table>
</div>
<?
} else if ($subdept) {
?>
<div class="col-sm-9">
  <table class="table table-striped table-condensed">
    <tbody>
<?
  foreach ($products as $row) {
    echo '<tr><td>' . ashtml($row['brand']) . '</td><td><a href="' . href('art-supplies/', $dept['slug'], '/', $subdept['slug'], '/', $row['slug']) . '">' . ashtml($row['name']) . '</a></td></tr>';
  }
?>
    </tbody>
  </table>
</div>
<?
}
foot();
?>
