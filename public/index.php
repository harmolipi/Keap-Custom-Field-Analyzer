<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

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
  $custom_field_counts = array();
  $customFieldService = $infusionsoft->customFields();

  $dataService = $infusionsoft->data();
  $query = array('FormId' => -1);
  $select = array('Id', 'Label', 'Name', 'Values', 'FormId');
  $orderBy = 'Label';
  $limit = 1000;
  $page = 0;
  $customFields = array();
  $ascending = true;

  do {
    $results = $dataService->query('DataFormField', $limit, $page, $query, $select, $orderBy, $ascending);
    $customFields = array_merge($customFields, $results);
    $page++;
  } while (count($results) == $limit);

  // Set each custom field's key as its id:
  $custom_field_ids = array_column($customFields, 'Id');
  $customFieldsArray = new ArrayObject($customFields);
  $customFields = array_combine($custom_field_ids, $customFields);

  $offset = 0;
  $limit = 1000;

  if (empty($custom_field_counts)) {



  if (empty($custom_field_counts)) {
    do {
      $contacts = $infusionsoft->contacts()->with('custom_fields')->where('limit', $limit)->where('offset', $offset)->get()->toArray();
      $total_count += count($contacts);
      if (empty($contacts)) {
        break;
      }
      // Go through each custom field of each contact, and increment the count for that field in the $custom_field_counts array
      foreach ($contacts as $index => $contact) {
        if (array_key_exists('custom_fields', $contact->getAttributes())) {
          foreach ($contact->getAttributes()['custom_fields'] as $custom_field) {
            if (!isset($custom_field_counts[$custom_field['id']])) {
              $custom_field_counts[$custom_field['id']] = 0;
            }
            if (!is_null($custom_field['content'])) {
              $custom_field_counts[$custom_field['id']]++;
            }
          }
        }
      }

      $offset += $limit;
    } while (count($contacts) == $limit);
    $_SESSION['custom_field_counts'] = $custom_field_counts;
  }

} else {
  echo '<a rel="nofollow" href="' . $infusionsoft->getAuthorizationUrl() . '">Click here to authorize</a>';
}
