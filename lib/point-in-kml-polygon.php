<?php

# adapted from https://stackoverflow.com/questions/14818567/point-in-polygon-algorithm-giving-wrong-results-sometimes/18190354#18190354

//Point class, storage of lat/long-pairs
class Point implements \JsonSerializable {
  public $lat, $long;

  public function __construct($lat, $long) {
    $this->lat= $lat;
    $this->long= $long;
  }

  public function jsonSerialize() {
    return [ 'latitude' => $this->lat, 'longitude' => $this->long ];
  }
}

function pointInPolygon($p, $polygon) {
  $c= 0;
  $p1= $polygon[0];
  $n= count($polygon);

  for ($i=1; $i<=$n; $i++) {
    $p2= $polygon[$i % $n];
    if ($p->long > min($p1->long, $p2->long)
        && $p->long <= max($p1->long, $p2->long)
        && $p->lat <= max($p1->lat, $p2->lat)
        && $p1->long != $p2->long)
    {
      $xinters= ($p->long - $p1->long) *
                ($p2->lat - $p1->lat) /
                ($p2->long - $p1->long) + $p1->lat;
      if ($p1->lat == $p2->lat || $p->lat <= $xinters) {
        $c++;
      }
    }
    $p1= $p2;
  }

  // if the number of edges we passed through is even, then it's not inside
  return ($c % 2) != 0;
}

function pointInKmzPolygon($file, $lat, $long) {
  return pointInKmlPolygon("zip://$file#doc.kml", $lat, $long);
}

function pointInKmlPolygon($file, $lat, $long) {
  $kml= simplexml_load_file($file);

  $point= new Point($lat, $long);

  foreach ($kml->Document->Folder->Placemark as $place) {
    if ($place->Polygon) {
      $coordinates= $place->Polygon->outerBoundaryIs->LinearRing->coordinates;
      $coords= preg_split('/\s+/', trim($coordinates));

      $polygon= array_map(function ($p) {
        list ($long, $lat)= preg_split('/,/', $p);
        return new Point($lat, $long);
      }, $coords);

      if (pointInPolygon($point, $polygon)) {
        return $place->name ?: true;
      }
    }
  }

  return false;
}
