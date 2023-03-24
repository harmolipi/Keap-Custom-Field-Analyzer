<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once '../vendor/autoload.php';

use Infusionsoft\Infusionsoft;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$cache = new FilesystemAdapter();

session_start();

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$infusionsoft = new Infusionsoft(array(
  'clientId' => $_ENV['CLIENT_ID'],
  'clientSecret' => $_ENV['CLIENT_SECRET'],
  'redirectUri' => $_ENV['REDIRECT_URI'],
));

// Clear session variables
if (isset($_GET['clear'])) {
  unset($_SESSION['token']);
}

// Clear cache
if (isset($_GET['clear_cache'])) {
  $cache->clear('custom_field_counts');
}

if (isset($_SESSION['token'])) {
  $infusionsoft->setToken(unserialize($_SESSION['token']));
}

if (isset($_GET['code']) and !$infusionsoft->getToken()) {
  $infusionsoft->requestAccessToken($_GET['code']);
}

if ($infusionsoft->getToken()) {
  $_SESSION['token'] = serialize($infusionsoft->getToken());

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
  $upto = 20000;

  if ($cache->hasItem('custom_field_counts')) {
    $custom_field_counts = $cache->getItem('custom_field_counts')->get();
    $offset = $custom_field_counts['offset'];
    $counts = $custom_field_counts['counts'];
  } else {
    $offset = 0;
    $counts = array();
  }



  if ($offset <= $upto) {
    do {
      $contacts = $infusionsoft->contacts()->with('custom_fields')->where('limit', $limit)->where('offset', $offset)->get()->toArray();

      if (empty($contacts)) {
        break;
      }

      // Go through each custom field of each contact, and increment the count for that field in the $custom_field_counts array
      foreach ($contacts as $index => $contact) {
        if (array_key_exists('custom_fields', $contact->getAttributes())) {
          foreach ($contact->getAttributes()['custom_fields'] as $custom_field) {
            if (!isset($counts[$custom_field['id']])) {
              $counts[$custom_field['id']] = 0;
            }
            if (!is_null($custom_field['content'])) {
              $counts[$custom_field['id']]++;
            }
          }
        }
      }

      $offset += $limit;

      // Add contacts to cache
      $custom_field_counts = [
        'offset' => $offset,
        'counts' => $counts,
      ];

      $cache_item = $cache->getItem('custom_field_counts')->set($custom_field_counts);
      $cache->save($cache_item);
    } while ($offset <= $upto && count($contacts) == $limit);
  }

  $cache->getItem('custom_field_counts_running')->set(false);
  $cache->save($cache->getItem('custom_field_counts_running'));

} else {
  echo '<a rel="nofollow" href="' . $infusionsoft->getAuthorizationUrl() . '">Click here to authorize</a>';
}
