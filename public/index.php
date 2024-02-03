<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once '../vendor/autoload.php';

use Infusionsoft\Infusionsoft;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$cache = new FilesystemAdapter();

session_start();

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$infusionsoft = new Infusionsoft(
    array(
    'clientId' => $_ENV['CLIENT_ID'],
    'clientSecret' => $_ENV['CLIENT_SECRET'],
    'redirectUri' => $_ENV['REDIRECT_URI'],
    )
);

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
    $scope = $infusionsoft->getToken()->getExtraInfo()['scope'];
    $subdomain = explode('.', explode('|', $scope)[1])[0];
    $cache_key = "custom_field_counts_{$subdomain}";

    $customFields = getCustomFields($infusionsoft);
    $tags = getAllTags($infusionsoft);

    $offset = 0;
    $limit = 1000;
    $upto = 5000;

    if ($cache->hasItem($cache_key)) {
        $custom_field_counts = $cache->getItem($cache_key)->get();
        $offset = $custom_field_counts['offset'];
        $counts = $custom_field_counts['counts'];
    } else {
        $offset = 0;
        $counts = array();
    }

    $running_flag = $cache->getItem('custom_field_counts_running')->get();

    if ($running_flag) {
        d('Process is already running. Please try again later.');
        exit();
    } else {
        $cache->getItem('custom_field_counts_running')->set(true);
        $cache->save($cache->getItem('custom_field_counts_running'));
    }

    if ($offset < $upto) {
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

            $cache_item = $cache->getItem($cache_key)->set($custom_field_counts);
            $cache->save($cache_item);
        } while ($offset < $upto && count($contacts) == $limit);
    }

    $cache->getItem('custom_field_counts_running')->set(false);
    $cache->save($cache->getItem('custom_field_counts_running'));

    // Create array of custom field names and counts
    $custom_field_counts_by_name = array();
    foreach ($custom_field_counts['counts'] as $id => $count) {
        $custom_field_counts_by_name[$customFields[$id]['Name']] = $count;
    }

    $unused_fields = array_filter(
        $custom_field_counts_by_name,
        function ($count) {
            return $count == 0;
        }
    );

    $rarely_used_fields = array_filter(
        $custom_field_counts_by_name,
        function ($count) {
            return $count > 0 && $count < 10;
        }
    );

    asort($custom_field_counts_by_name);

    // Output:
    echo '<a rel="nofollow" href="?clear">Click here to logout</a>'; // Deauthorize app
    echo '<br>';
    echo '<a rel="nofollow" href="?clear_cache">Click here to clear the cache</a>'; // Clear cache
    d($unused_fields);
    d($rarely_used_fields);
    d($custom_field_counts_by_name);
    d($tags);
} else {
    echo '<a rel="nofollow" href="' . $infusionsoft->getAuthorizationUrl() . '">Click here to authorize</a>';
}

function getCustomFields(Infusionsoft $infusionsoft): array
{
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
    $customFields = array_combine($custom_field_ids, $customFields);

    return $customFields;
}

function getAllTags(Infusionsoft $infusionsoft): array
{
    $tags = $infusionsoft->tags()->get()->toArray();
    $formatted_tags = [];

    foreach ($tags as $tag) {
        $category_name = '';
        if (isset($tag->category) && isset($tag->category['name'])) {
            $category_name = $tag->category['name'];
        }

        $formatted_tags[$tag->id] = ['name' => $tag->name, 'category' => $category_name];
    }

    return $formatted_tags;
}
