<?php

class Rewards {

  static function addRoutes($f3) {
    $f3->route("POST /process-rewards", 'Rewards->process');
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
}
