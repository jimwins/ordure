<?php
require 'lib/common.php';

list(, $dept, $subdept, $product)= explode('/', $_SERVER['PATH_INFO']);

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
  $slug= $db->escape($dept);
  $q= "SELECT id, name, slug
         FROM department
        WHERE parent IS NULL
          AND slug = '$slug'";
  $parent= $db->get_one_assoc($q);
  // XXX error handling
  $parent_check= "parent = $parent[id]";

  echo '<a class="list-group-item" href="' . href('art-supplies') . '"><b><span class="pull-right glyphicon glyphicon-chevron-up"></span> Back to Top</b></a>';
  echo '</div>';
  echo '<div class="panel-heading"><h3 class="panel-title">',
       ashtml($parent['name']),
       '</h3></div>';
  echo '<div class="list-group">';
} else {
  $parent_check= "parent IS NULL";
}

$q= "SELECT slug, name, parent
       FROM department
      WHERE $parent_check
      ORDER BY IFNULL(parent, 0), IF(parent IS NULL, -1, pos), name";
$r= $db->query($q);

while ($row= $r->fetch_assoc()) {
  $active= ($row['slug'] == $subdept) ? 'active' : '';
  echo '<a class="list-group-item ' . $active . '" href="' .
       href('art-supplies/', $dept, ($dept ? '/' : ''), $row['slug']), '">',
       ashtml($row['name']), '</a>';
}?>
    </div>
  </div>
</div>
<div class="col-sm-9">
  <table class="table table-striped table-condensed">
    <tbody>
<?
if ($product) {
  $subdept_id= $db->get_one("SELECT id FROM department WHERE slug = '" . ashtml($subdept) . "'");
  $q= "SELECT product.slug, product.name, brand.name brand,
              description, image
         FROM product
         LEFT JOIN brand ON product.brand = brand.id
        WHERE product.slug = '" . $db->escape($product) . "'
        ORDER BY brand.name, name";

  $product= $db->get_one_assoc($q) or die($db->error);
  // XXX errors
?>
  <div class="page-header">
    <h1>
      <?=ashtml($product['name'])?>
      <small><?=ashtml($product['brand'])?></small>
    </h1>
  </div>
<?if ($product['image']) {?>
  <div class="pull-right thumbnail">
    <?=img($product['image'], 240)?>
  </div>
<?}?>
  <div><?=$product['description']?></div>
<?
} else if ($subdept) {
  $subdept_id= $db->get_one("SELECT id FROM department WHERE slug = '" . ashtml($subdept) . "'");
  $q= "SELECT product.name, product.slug, brand.name brand
         FROM product
         LEFT JOIN brand ON product.brand = brand.id
        WHERE department = $subdept_id
        ORDER BY brand.name, name";
  
  $r= $db->query($q) or die($db->error);
  // XXX errors
  
  while ($row= $r->fetch_assoc()) {
    echo '<tr><td>' . ashtml($row['brand']) . '</td><td><a href="' . href('art-supplies/', $dept, '/', $subdept, '/', $row['slug']) . '">' . ashtml($row['name']) . '</a></td></tr>';
  }
}
?>
    </tbody>
  </table>
</div>
<?
foot();
