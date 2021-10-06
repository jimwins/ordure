<?php

class Uploader {
  static function addRoutes($f3) {
    $f3->route("POST /~grab-image", 'Uploader->grabImage');
  }

  function grabImage($f3, $args) {
    $key= $f3->get('UPLOAD_KEY');

    if ($key != $f3->get('REQUEST.key')) {
      $f3->error(500, 'Wrong key.');
    }

    $b2_id= $f3->get('B2_KEYID');
    $b2_key= $f3->get('B2_APPLICATION_KEY');
    $b2_bucket= $f3->get('B2_BUCKET');

    $b2= new \ChrisWhite\B2\Client($b2_id, $b2_key);

    $url= $f3->get('REQUEST.url');

    // Special hack to get full-size Salsify images
    $url= str_replace('/c_limit,cs_srgb,h_600,w_600', '', $url);

    error_log("Grabbing image from URL '$url'\n");

    $client= new \GuzzleHttp\Client();
    $response= $client->get($url);

    $file= $response->getBody();
    $name= basename(parse_url($url, PHP_URL_PATH));

    if ($response->hasHeader('Content-Type')) {
      $content_type= $response->getHeader('Content-Type')[0];
      if (!preg_match('/^image/', $content_type)) {
        $f3->error(500, "URL was not an image, it was a '$content_type'");
      }
    }

    $uuid= sprintf("%08x%02x%s", time(), 1, bin2hex(random_bytes(8)));

    // No extension? Probably a JPEG
    $ext= pathinfo($name, PATHINFO_EXTENSION) ?: 'jpg';

    $b2_file= $b2->upload([
      'BucketName' => $b2_bucket,
      'FileName' => "i/o/$uuid.$ext",
      'Body' => $file,
    ]);

    $path= sprintf(
      '%s/file/%s/%s',
      $b2->getAuthorization()['downloadUrl'],
      $b2_bucket,
      $b2_file->getName()
    );

    header("Content-type: application/json");

    echo json_encode([
      'path' => $path,
      'ext' => $ext,
      'uuid' => $uuid,
      'name' => $name,
      'id' => $b2_file->getId()
    ]);
  }
}
