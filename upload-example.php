<?php
/**
 * File upload example
 */
require_once 'vendor/autoload.php';

$token = 'your token';
$url = 'https://your.moodle.server';

$moodle = new \UniGe\MoodleWSClient($url, $token);  // /webservice/rest/server.php
$moodle->setupProxy('your proxy',8080);

$site_info = $moodle->core_webservice_get_site_info();
echo "You are connected to {$site_info->sitename}\n";

$result = $moodle->upload('the file path');
print_r($result);