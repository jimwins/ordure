<?php

class Auth {

  static function addRoutes($f3) {
    $f3->route("GET|HEAD /login", 'Auth->login');
  }

  function login($f3) {
    $db= $f3->get('DBH');

    $f3->set('email', '');
    $f3->set('password', '');
    $f3->set('createNew', '0');
    $f3->set('rememberMe', '0');

    $f3->set('LOGIN_FAILED', false);

    echo Template::instance()->render('login.html');
  }
}
