<?php

class Events {

  static function addRoutes($f3) {
    $f3->route("GET /~update-events", 'Events->update');

    $f3->set('list_events', function() {
      $data= json_decode(@file_get_contents('/tmp/event-data.json'));
      if (!$data) return;

      echo \Template::instance()->render("events.html", null, [
        'events' => $data->events,
      ]);
    });
  }

  function update($f3, $args) {
    $client= new \GuzzleHttp\Client();
    $params= [ 'status' => 'live', 'expand' => 'ticket_availability' ];
    $api_url= 'https://www.eventbriteapi.com/v3/users/me/events/';
    $res= $client->request('GET', $api_url, [
                             'query' => $params,
                             'headers' => [
                               'Authorization' => 'Bearer ' .
                                                  $f3->get('EVENTBRITE_APIKEY'),
                             ],
                           ]);

    $data= $res->getBody();

    $f= fopen('/tmp/event-data.json', 'w');
    fputs($f, $data);
    fclose($f);

    echo $data;
  }

}
