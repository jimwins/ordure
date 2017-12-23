<?php

class Rewards {

  // These URLs are kind of a mess
  static function addRoutes($f3) {
    $f3->route("POST /process-rewards", 'Rewards->process');
    $f3->route("GET /get-pending-rewards [json]",
               'Rewards->getPendingRequests');
    $f3->route("GET|POST /mark-rewards-processed [json]",
               'Rewards->markRewardsProcessed');
  }

  function process($f3, $args) {
    $db= $f3->get('DBH');

    $loyalty= new DB\SQL\Mapper($db, 'loyalty');
    
    $loyalty->name= $f3->get('REQUEST.name');
    $loyalty->email= $f3->get('REQUEST.email');
    $loyalty->phone= $f3->get('REQUEST.phone');
    $loyalty->loyalty_number=
      preg_replace('/\D+/', '', $f3->get('REQUEST.phone'));
    $loyalty->code= $f3->get('REQUEST.code');

    $loyalty->save();

    // Sign them up for the newsletter
    if ($f3->get('REQUEST.subscribe')) {
      $key= $f3->get("MAILERLITE_KEY");
      $groupsApi= (new MailerLiteApi\MailerLite($key))->groups();

      $subscriber= [
          'email' => $loyalty->email,
          'fields' => [
              'name' => $loyalty->name,
          ]
      ];

      $response= $groupsApi->addSubscriber($f3->get('MAILERLITE_GROUP'),
                                            $subscriber);
    }

    $page= new DB\SQL\Mapper($db, 'page');

    $page->load(array('slug=?', 'reward-thanks'))
      or $f3->error(404);

    $f3->set('PAGE', $page);

    echo Template::instance()->render('page.html');
  }

  function getPendingRequests($f3) {
    $db= $f3->get('DBH');

    $loyalty= new DB\SQL\Mapper($db, 'loyalty');

    $key= $f3->get('UPLOAD_KEY');

    if ($key != $_REQUEST['key']) {
      $f3->error(500, 'Wrong key.');
    }

    $list= $loyalty->find(array('processed = 0'));

    $out= array();
    foreach ($list as $l) {
      $out[]= $l->cast();
    }

    echo json_encode($out, JSON_PRETTY_PRINT);
  }

  function markRewardsProcessed($f3) {
    $db= $f3->get('DBH');

    $loyalty= new DB\SQL\Mapper($db, 'loyalty');

    $key= $f3->get('UPLOAD_KEY');

    if ($key != $_REQUEST['key']) {
      $f3->error(500, 'Wrong key.');
    }

    $item= $loyalty->find(array('id = ?', $_REQUEST['id']));

    if (!$item) {
      die(json_encode(array("error" => "No such record found.")));
    }

    $item[0]->processed= 1;
    $item[0]->save();

    echo json_encode($item[0]->cast(), JSON_PRETTY_PRINT);
  }
}
