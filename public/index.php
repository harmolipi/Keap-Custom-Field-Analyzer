<?php

require_once '../vendor/autoload.php';

use Infusionsoft\Infusionsoft;

session_start();

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$infusionsoft = new Infusionsoft(array(
  'clientId' => $_ENV['CLIENT_ID'],
  'clientSecret' => $_ENV['CLIENT_SECRET'],
  'redirectUri' => $_ENV['REDIRECT_URI'],
));

// clear session variables
if (isset($_GET['clear'])) {
  unset($_SESSION['token']);
}

if (isset($_SESSION['token'])) {
  $infusionsoft->setToken(unserialize($_SESSION['token']));
}

if (isset($_GET['code']) and !$infusionsoft->getToken()) {
  $infusionsoft->requestAccessToken($_GET['code']);
}

if ($infusionsoft->getToken()) {
  $_SESSION['token'] = serialize($infusionsoft->getToken());
} else {
  echo '<a rel="nofollow" href="' . $infusionsoft->getAuthorizationUrl() . '">Click here to authorize</a>';
}
