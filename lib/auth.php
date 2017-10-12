<?php

class Auth {

  static function addRoutes($f3) {
    $f3->route("GET|HEAD /login", 'Auth->viewLoginForm');
    $f3->route("POST /login", 'Auth->login');
    $f3->route("GET|HEAD /register", 'Auth->viewRegisterForm');
    $f3->route("POST /register", 'Auth->register');
    $f3->route("GET|HEAD /account", 'Auth->account');
    $f3->route("GET|HEAD /logout", 'Auth->logout');
  }

  static function prehash($password) {
    return base64_encode(hash('sha256', $password, true));
  }

  static function authenticated_user($f3) {
    if (($login_token= $f3->get('COOKIE.loginToken'))) {
      list($selector, $validator)= explode(':', $login_token);
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
        /* Push out expiry of token if more than a day since we've seen it */
        if (new \Datetime('-1 day') > new \Datetime($auth_token->modified)) {
          $expires= new \Datetime('+14 days');
          $auth_token->expires= $expires->format('Y-m-d H:i:s');
          $auth_token->save();

          self::generateAuthCookie($selector, $validator, $expires);
        }

        return $auth_token->person_id;
      }
    }
    return false;
  }

  function issue_auth_token($f3, $person_id) {
    $selector= bin2hex(random_bytes(6));
    $validator= base64_encode(random_bytes(24));
    $token= hash('sha256', $validator);

    $expires= new \Datetime('+14 days');

    $db= $f3->get('DBH');
    $auth_token= new DB\SQL\Mapper($db, 'auth_token');
    $auth_token->selector= $selector;
    $auth_token->token= $token;
    $auth_token->person_id= $person_id;
    $auth_token->expires= $expires->format('Y-m-d H:i:s');

    $auth_token->save();

    self::generateAuthCookie($selector, $validator, $expires);
  }

  static function generateAuthCookie($selector, $validator, $expires) {
    $domain= ($_SERVER['HTTP_HOST'] != 'localhost' ?
              $_SERVER['HTTP_HOST'] : false);

    SetCookie('loginToken', "$selector:$validator", $expires->format('U'),
              '/', $domain, true, true);
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

  function account($f3, $args) {
    $person_id= self::authenticated_user($f3);

    if (!$person_id) {
      $f3->reroute('/login');
    }

    $db= $f3->get('DBH');

    $person= new DB\SQL\Mapper($db, 'person');
    $person->load(array('id = ?', $person_id));
    $person->copyTo('person');

    echo Template::instance()->render('account.html');
  }

  function logout($f3, $args) {
    $domain= ($_SERVER['HTTP_HOST'] != 'localhost' ?
              $_SERVER['HTTP_HOST'] : false);

    SetCookie('loginToken', "", (new \Datetime("-24 hours"))->format("U"),
              '/', $domain, true, true);
    $f3->reroute('/login');
  }
}
