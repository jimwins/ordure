<?php

use Respect\Validation\Validator as v;

class Auth {

  static function addRoutes($f3) {
    $f3->route("GET|HEAD /login", 'Auth->viewLoginForm');
    $f3->route("POST /login", 'Auth->login');
    $f3->route("POST /login/get-link", 'Auth->getLoyaltyLink');
    $f3->route("GET /login/key/*", 'Auth->loginWithKey');
    $f3->route("GET|HEAD /forgotPassword", 'Auth->viewForgotPasswordForm');
    $f3->route("GET|HEAD /register", 'Auth->viewRegisterForm');
    $f3->route("POST /register", 'Auth->register');
    $f3->route("GET|HEAD /account", 'Auth->account');
    $f3->route("POST /account/update", 'Auth->updateAccount');
    $f3->route("GET|HEAD /logout", 'Auth->logout');
  }

  static function prehash($password) {
    return base64_encode(hash('sha256', $password, true));
  }

  static function validate_auth_token($f3, $token) {
    list($selector, $validator)= explode(':', $token);
    if (!$selector || !$validator) {
      return false;
    }

    $db= $f3->get('DBH');
    $auth_token= new DB\SQL\Mapper($db, 'auth_token');
    $auth_token->load(array('selector = ?', $selector));
    if ($auth_token->dry()) {
      return false;
    }

    if ($auth_token->expires &&
        new \Datetime() > new \Datetime($auth_token->expires)) {
      $auth_token->erase();
      return false; // expired
    }

    if (hash_equals($auth_token->token, hash('sha256', $validator))) {
      return $auth_token;
    }

    return false;
  }

  static function authenticated_user($f3) {
    if (($user_id= $f3->get('AUTHENTICATED_USER_ID'))) {
      return $user_id;
    }

    if (($login_token= $f3->get('COOKIE.loginToken'))) {
      if (($auth_token= self::validate_auth_token($f3, $login_token))) {
        /* Push out expiry of token if more than a day since we've seen it */
        if (new \Datetime('-1 day') > new \Datetime($auth_token->modified)) {
          $expires= new \Datetime('+14 days');
          $auth_token->expires= $expires->format('Y-m-d H:i:s');
          $auth_token->save();

          self::generateAuthCookie($login_token, $expires);
        }

        $f3->set('AUTHENTICATED_USER_ID', $auth_token->person_id);

        return $auth_token->person_id;
      }
    }
    return false;
  }

  function generate_auth_token($f3, $person_id, $expires) {
    $selector= bin2hex(random_bytes(6));
    $validator= base64_encode(random_bytes(24));
    $token= hash('sha256', $validator);

    $db= $f3->get('DBH');
    $auth_token= new DB\SQL\Mapper($db, 'auth_token');
    $auth_token->selector= $selector;
    $auth_token->token= $token;
    $auth_token->person_id= $person_id;
    $auth_token->expires= $expires->format('Y-m-d H:i:s');

    $auth_token->save();

    return "$selector:$validator";
  }

  function issue_auth_token($f3, $person_id) {
    $expires= new \Datetime('+14 days');
    $token= self::generate_auth_token($f3, $person_id, $expires);

    self::generateAuthCookie($token, $expires);
  }

  static function generateAuthCookie($token, $expires) {
    $domain= ($_SERVER['HTTP_HOST'] != 'localhost' ?
              $_SERVER['HTTP_HOST'] : false);

    SetCookie('loginToken', "$token", $expires->format('U'),
              '/', $domain, true, true);
    SetCookie('loggedIn', "1", $expires->format('U'),
              '/', $domain, true, false);
  }

  function viewLoginForm($f3) {
    $email= (string)@$_REQUEST['email'];
    $password= (string)@$_REQUEST['password'];
    $createNew= (int)@$_REQUEST['createNew'];
    $rememberMe= (int)@$_REQUEST['rememberMe'];

    $f3->set('email', $email);
    $f3->set('password', $password);
    $f3->set('createNew', $createNew);
    $f3->set('rememberMe', $rememberMe);

    $f3->set('LOGIN_FAILED', false);

    echo Template::instance()->render('login.html');
  }

  function login($f3) {
    $db= $f3->get('DBH');

    $email= (string)@$_REQUEST['email'];
    $password= (string)@$_REQUEST['password'];
    $createNew= (int)@$_REQUEST['createNew'];
    $rememberMe= (int)@$_REQUEST['rememberMe'];

    $f3->set('email', $email);
    $f3->set('password', $password);
    $f3->set('createNew', $createNew);
    $f3->set('rememberMe', $rememberMe);

    if ($email && $createNew) {
      $f3->reroute('/register?email=' . urlencode($email));
    }

    $person= new DB\SQL\Mapper($db, 'person');
    $auth= new DB\SQL\Mapper($db, 'auth');

    if (!$email || !$password ||
        !$person->load(array('email=?', $email)) ||
        !$auth->load(array('person_id=?', $person->id)))
    {
      $f3->set('LOGIN_FAILED', true);
    }
    else {
      // Verify the password
      if (password_verify(self::prehash($password), $auth->password_hash)) {
        // If a new hashing algorithm is available, upgrade
        if (password_needs_rehash($auth->password_hash, PASSWORD_DEFAULT)) {
          $new_hash= password_hash(self::prehash($password),
                                   PASSWORD_DEFAULT);
          $auth->password_hash= $new_hash;
          $auth->save();
        }

        // Log user in
        $auth->failures= 0;
        $auth->last_auth= date("Y-m-d H:i:s");
        $auth->save();

        self::issue_auth_token($f3, $auth->person_id);
        // XXX redirect to where we're told
        $f3->reroute('/account');
      }
    }

    if (!$auth->dry()) {
      $auth->failures++;
      $auth->save();
    }

    $f3->set('LOGIN_FAILED', false);

    echo Template::instance()->render('login.html');
  }

  function loginWithKey($f3, $args) {
    $key= $args['*'];

    if (($auth= self::validate_auth_token($f3, $key))) {
      self::issue_auth_token($f3, $auth->person_id);
      $f3->reroute('/account');
    }

    $f3->set('KEY_FAILED', true);

    echo Template::instance()->render('login.html');
  }

  function viewForgotPasswordForm($f3) {
    $f3->error(500, "Sorry, I can't do that yet.");
  }

  function viewRegisterForm($f3) {
    $db= $f3->get('DBH');

    $name= (string)@$_REQUEST['name'];
    $email= (string)@$_REQUEST['email'];
    $password= (string)@$_REQUEST['password'];
    $password2= (string)@$_REQUEST['password2'];
    $createNew= (int)@$_REQUEST['createNew'];
    $rememberMe= (int)@$_REQUEST['rememberMe'];

    $f3->set('name', $name);
    $f3->set('email', $email);
    $f3->set('password', $password);
    $f3->set('password2', $password2);
    $f3->set('rememberMe', $rememberMe);

    $f3->set('REGISTER_FAILED', false);

    echo Template::instance()->render('register.html');
  }

  function register($f3) {
    $db= $f3->get('DBH');

    $name= (string)@$_REQUEST['name'];
    $email= (string)@$_REQUEST['email'];
    $password= (string)@$_REQUEST['password'];
    $password2= (string)@$_REQUEST['password2'];
    $rememberMe= (int)@$_REQUEST['rememberMe'];

    $f3->set('name', $name);
    $f3->set('email', $email);
    $f3->set('password', $password);
    $f3->set('password2', $password2);
    $f3->set('rememberMe', $rememberMe);

    $person= new DB\SQL\Mapper($db, 'person');

    if (!$name || !$email || !$password || !$password2 ||
        $password != $password2) {
      $f3->set('REGISTER_FAILED', true);
    }
    elseif ($person->load(array('email=?', $email))) { 
      // If an account with that email address exists, bail out
      $f3->set('REGISTER_FAILED', true);
      // XXX Should probably log them in if password is correct
    }
    else {
      $person->set('email', $email);
      $person->set('name', $name);
      $person->save();

      $hash= password_hash(self::prehash($password), PASSWORD_DEFAULT);

      $auth= new DB\SQL\Mapper($db, 'auth');
      $auth->copyfrom(array('person_id' => $person->get('id'),
                            'password_hash' => $hash));
      $auth->save();

      $f3->reroute('/login?email=' . rawurlencode($email));
    }

    echo Template::instance()->render('register.html');
  }

  static function authenticated_user_details($f3) {
    $person_id= self::authenticated_user($f3);
    if (!$person_id) {
      return false;
    }

    $client= new GuzzleHttp\Client();

    $backend= $f3->get('GIFT_BACKEND');
    $uri= $backend . "/person/{$person_id}";

    try {
      $response= $client->get($uri, [
        'headers' => [ 'Accept' => 'application/json' ]
      ]);
    } catch (\Exception $e) {
      throw new \Exception(sprintf("Request failed: %s (%s)",
                                   $e->getMessage(), $e->getCode()));
    }

    $data= json_decode($response->getBody(), true);

    if (json_last_error() != JSON_ERROR_NONE) {
      $f3->error(500, json_last_error_msg());
    }

    return $data;
  }

  function account($f3, $args) {
    $person_id= self::authenticated_user($f3);

    if (!$person_id) {
      $f3->reroute('/login');
    }

    $f3->set('person', self::authenticated_user_details($f3));

    echo Template::instance()->render('account.html');
  }

  function updateAccount($f3, $args) {
    $person_id= self::authenticated_user($f3);

    if (!$person_id) {
      $f3->reroute('/login');
    }

    $conflict= $f3->get('REQUEST.conflict');
    if ($conflict) {
      self::email_conflict_report($f3, $person_id);
      $f3->reroute("/account?success=conflict");
    }

    $client= new GuzzleHttp\Client();

    $backend= $f3->get('GIFT_BACKEND');
    $uri= $backend . '/person/' . $person_id;

    $data= [
      'name' => $f3->get('REQUEST.name'),
      'email' => $f3->get('REQUEST.email'),
      'phone' => $f3->get('REQUEST.phone'),
      'rewardsplus' => $f3->get('REQUEST.rewardsplus'),
    ];

    try {
      $response= $client->patch($uri, [
        'json' => $data,
      ]);
    } catch (\GuzzleHttp\Exception\ClientException $e) {
      if ($e->hasResponse() && $e->getResponse()->getStatusCode() == 409) {
        $f3->reroute('/account?errors[]=conflict&' . http_build_query($data));
      }
    } catch (\Exception $e) {
      $f3->reroute('/account?errors[]=unable&' . http_build_query($data));
    }

    $person= json_decode($response->getBody(), true);

    if (json_last_error() != JSON_ERROR_NONE) {
      $f3->reroute('/account?errors[]=unable&' . http_build_query($data));
    }

    if ($person->errors) {
      $f3->reroute('/account?' . http_build_query(array_merge($person, $data)));
    }

    $person= self::authenticated_user_details($f3);

    $f3->reroute('/account?success=update');
  }

  function logout($f3, $args) {
    $domain= ($_SERVER['HTTP_HOST'] != 'localhost' ?
              $_SERVER['HTTP_HOST'] : false);

    SetCookie('loginToken', "", (new \Datetime("-24 hours"))->format("U"),

              '/', $domain, true, true);
    SetCookie('loggedIn', "", (new \Datetime("-24 hours"))->format("U"),
              '/', $domain, true, false);
    $f3->reroute('/login');
  }

  function getLoyaltyLink($f3, $args) {
    $loyalty= trim($f3->get('REQUEST.loyalty'));

    $client= new GuzzleHttp\Client();

    $backend= $f3->get('GIFT_BACKEND');
    $uri= $backend . '/person/search/?loyalty=' . $loyalty;

    try {
      $response= $client->get($uri, [
        'headers' => [ 'Accept' => 'application/json' ]
      ]);
    } catch (\Exception $e) {
      $f3->error(500, (sprintf("Request failed: %s (%s)",
                               $e->getMessage(), $e->getCode())));
    }

    $people= json_decode($response->getBody());

    if (json_last_error() != JSON_ERROR_NONE) {
      $f3->error(500, json_last_error_msg());
    }

    if (!$people) {
      $f3->reroute('/login?error=invalid_loyalty');
    }

    if (strcasecmp($loyalty, $people[0]->email) == 0) {
      self::email_login_link($f3, $people[0]);
      $f3->reroute('/login?success=email_sent');
    }

    self::sms_login_link($f3, $people[0]);
    $f3->reroute('/login?success=sms_sent');
  }

  function generate_login_link($f3, $person) {
    $expires= new \Datetime('+24 hours');
    $key= self::generate_auth_token($f3, $person->id, $expires);
    return 'https://' . $_SERVER['HTTP_HOST'] .
           '/login/key/' . rawurlencode($key);
  }

  function sms_login_link($f3, $person) {
    $client= new GuzzleHttp\Client();

    $backend= $f3->get('GIFT_BACKEND');
    $uri= $backend . '/sms/~send';

    $text= "You can use this link to log in within the next 24 hours: " .
           self::generate_login_link($f3, $person);

    try {
      $response= $client->post($uri, [ 'json' => [
        'to' => $person->loyalty_number,
        'text' => $text
      ] ]);
    } catch (\Exception $e) {
      $f3->error(500, (sprintf("Request failed: %s (%s)",
                               $e->getMessage(), $e->getCode())));
    }

    $data= json_decode($response->getBody());

    if (json_last_error() != JSON_ERROR_NONE) {
      $f3->error(500, json_last_error_msg());
    }
  }

  function email_login_link($f3, $person) {
    $httpClient= new \Http\Adapter\Guzzle6\Client(new \GuzzleHttp\Client());
    $sparky= new \SparkPost\SparkPost($httpClient,
                           [ 'key' => $f3->get('SPARKPOST_KEY') ]);

    $title= "Log in to your account";
    $f3->set('title', $title);

    $f3->set('content_top', Markdown::instance()->convert("Here is a link to log in to your account on our website:"));
    $f3->set('call_to_action', 'Log in');
    $f3->set('call_to_action_url', self::generate_login_link($f3, $person));
    $f3->set('content_bottom', Markdown::instance()->convert("Let us know if there is anything else that we can do to help."));

    $html= Template::instance()->render('email-template.html');

    $promise= $sparky->transmissions->post([
      'content' => [
        'html' => $html,
        'subject' => $title,
        'from' => array('name' => 'Raw Materials Art Supplies',
                        'email' => $f3->get('CONTACT_SALES')),
        'inline_images' => [
          [
            'name' => 'logo.png',
            'type' => 'image/png',
            'data' => base64_encode(file_get_contents('../ui/logo.png')),
          ],
        ],
      ],
      'recipients' => [
        [
          'address' => [
            'name' => '',
            'email' => $person->email,
          ],
        ],
      ],
      'options' => [
        'inlineCss' => true,
        'transactional' => true,
      ],
    ]);

    try {
      $response= $promise->wait();
      // XXX handle response
    } catch (\Exception $e) {
      $f3->get('log')->error(
        sprintf("SparkPost failure: %s (%s)",
                $e->getMessage(), $e->getCode())
      );
    }
  }

  function email_conflict_report($f3, $person_id) {
    $httpClient= new \Http\Adapter\Guzzle6\Client(new \GuzzleHttp\Client());
    $sparky= new \SparkPost\SparkPost($httpClient,
                           [ 'key' => $f3->get('SPARKPOST_KEY') ]);

    $title= "User conflict reported";
    $f3->set('title', $title);

    $content=
      "Someone reported a conflict in the user data.\n\n" .
      "Name: " . $f3->get('REQUEST.name') . "  \n" .
      "Email: " . $f3->get('REQUEST.email') . "  \n" .
      "Phone: " . $f3->get('REQUEST.phone') . "  \n";

    $f3->set('content_top', Markdown::instance()->convert($content));
    $f3->set('call_to_action', 'Resolve');
    $f3->set('call_to_action_url',
             $f3->get('GIFT_BACKEND') . '/person/' . $person_id);
    $f3->set('content_bottom', Markdown::instance()->convert("Contact them when this is resolved."));

    $html= Template::instance()->render('email-template.html');

    $promise= $sparky->transmissions->post([
      'content' => [
        'html' => $html,
        'subject' => $title,
        'from' => array('name' => 'Raw Materials Art Supplies',
                        'email' => $f3->get('CONTACT_SALES')),
        'inline_images' => [
          [
            'name' => 'logo.png',
            'type' => 'image/png',
            'data' => base64_encode(file_get_contents('../ui/logo.png')),
          ],
        ],
      ],
      'recipients' => [
        [
          'address' => [
            'name' => '',
            'email' => $f3->get('CONTACT_SALES'),
          ],
        ],
      ],
      'options' => [
        'inlineCss' => true,
        'transactional' => true,
      ],
    ]);

    try {
      $response= $promise->wait();
      // XXX handle response
    } catch (\Exception $e) {
      $f3->get('log')->error(
        sprintf("SparkPost failure: %s (%s)",
                $e->getMessage(), $e->getCode())
      );
    }
  }
}
