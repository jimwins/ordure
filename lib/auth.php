<?php

class Auth {

  static function addRoutes($f3) {
    $f3->route("GET|HEAD /login", 'Auth->viewLoginForm');
    $f3->route("POST /login", 'Auth->login');
    $f3->route("GET|HEAD /register", 'Auth->viewRegisterForm');
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
}
