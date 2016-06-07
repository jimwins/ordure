<?php

class Auth {

  static function addRoutes($f3) {
    $f3->route("GET|HEAD /login", 'Auth->viewLoginForm');
    $f3->route("POST /login", 'Auth->login');
    $f3->route("GET|HEAD /register", 'Auth->viewRegisterForm');
    $f3->route("POST /register", 'Auth->register');
  }

  static function prehash($password) {
    return base64_encode(hash('sha256', $password, true));
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
        !$auth->load(array('person=?', $person->id)))
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

        // XXX do something

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
      $auth->copyfrom(array('person' => $person->get('id'),
                            'password_hash' => $hash));
      $auth->save();

      $f3->reroute('/login?email=' . rawurlencode($email));
    }

    echo Template::instance()->render('register.html');
  }

}
