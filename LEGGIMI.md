# Un client "magico" per i web services di Moodle

== Uso ==

Il client invoca i metodi di Moodle come se fossero propri dell'oggetto.

Esempio:

require_once 'MoodleWSClient.php';

$token = ...;

$moodle = new \UniGe\MoodleWSClient('https://2018.aulaweb.unige.it', $token);  // /webservice/rest/server.php

$site_info = $moodle->core_webservice_get_site_info();
echo "Sei collegato al sito {$site_info->sitename}\n";


Se non si dispone di un token:

$moodle = new \UniGe\MoodleWSClient('https://2018.aulaweb.unige.it');
$token = $moodle->newToken(nome utente, password, servizio);
$moodle->setToken($token);