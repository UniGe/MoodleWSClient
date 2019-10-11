<?php
/*
This library is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This library  is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Nome-Programma.  If not, see <http://www.gnu.org/licenses/>.

 * @copyright  2019 Università di Genova, Italy
 * @copyright partial Dongsheng Cai <dongsheng@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace UniGe;

class MoodleWSException extends \Exception {

    function __construct(string $message = '', int $code = 0, \Throwable $previous = NULL) {
        parent::__construct($message, $code, $previous);
    }

}

class MoodleWSClient
{

    use \Psr\Log\LoggerAwareTrait;

    protected $curl_options = [
        CURLOPT_USERAGENT => 'MoodleWsClient/0.1',
        CURLOPT_HEADER => 0,
        CURLOPT_NOBODY => 0,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_ENCODING => '',
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_BINARYTRANSFER =>  0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 30,
    ];

    private $site;
    private $endpoint = '/webservice/rest/server.php';
    private $token;

    /**
     * Create a Moodle stub object
     *
     * @param type $site The Moodle site root url
     * @param type $token A security token
     * @param type $endpoint A non standard (/webservice/rest/server.php) location for the web services endpoint
     */
    public function __construct($site, $token = null, $endpoint = null)
    {
        $this->logger = new \Psr\Log\NullLogger();

        $this->site = rtrim($site, '/\\');
        if ($token) {
            $this->setToken($token);
        }
        if ($endpoint) {
            $this->endpoint = '/' . ltrim($endpoint, '/\\');
        }
    }

    /**
     * Setup proxy
     * 
     * @param string $host
     * @param int $port
     * @param string $user Optional.
     * @param string $pass Optional.
     */
    public function setupProxy($host, $port, $user = NULL, $pass = NULL)
    {
        $this->curl_options[CURLOPT_PROXYTYPE] = 'HTTP';
        $this->curl_options[CURLOPT_PROXY] = $host;
        $this->curl_options[CURLOPT_PROXYPORT] = $port;
        if ($user && $pass) {
            $this->curl_options[CURLOPT_PROXYUSERPWD] = $user . ":" . $pass;
        }
    }

    public function getToken() {
        return $this->token;
    }

    public function setToken($token) {
        $this->token = $token;
    }

    /**
     * Recursive function formating an array in POST parameter
     *
     * From https://github.com/moodlehq/sample-ws-clients/blob/master/PHP-REST/curl.php
     *
     * @param array $arraydata - the array that we are going to format and add into &$data array
     * @param string $currentdata - a row of the final postdata array at instant T
     *                when finish, it's assign to $data under this format: name[keyname][][]...[]='value'
     * @param array $data - the final data array containing all POST parameters : 1 row = 1 parameter
     */
    private function format_array_postdata_for_curlcall($arraydata, $currentdata, &$data)
    {
        foreach ($arraydata as $k=>$v) {
            $newcurrentdata = $currentdata;
            if (is_object($v)) {
                $v = (array) $v;
            }
            if (is_array($v)) { //the value is an array, call the function recursively
                $newcurrentdata = $newcurrentdata.'['.urlencode($k).']';
                $this->format_array_postdata_for_curlcall($v, $newcurrentdata, $data);
            }
            else { //add the POST parameter to the $data array
                $data[] = $newcurrentdata.'['.urlencode($k).']='.urlencode($v);
            }
        }
    }

    /**
     * Transform a PHP array into POST parameter
     * (see the recursive function format_array_postdata_for_curlcall)
     *
     * From https://github.com/moodlehq/sample-ws-clients/blob/master/PHP-REST/curl.php
     *
     * @param array $postdata
     * @return array containing all POST parameters  (1 row = 1 POST parameter)
     */
    private function format_postdata_for_curlcall($postdata)
    {
        if (is_object($postdata)) {
            $postdata = (array) $postdata;
        }
        $data = array();
        foreach ($postdata as $k=>$v) {
            if (is_object($v)) {
                $v = (array) $v;
            }
            if (is_array($v)) {
                $currentdata = urlencode($k);
                $this->format_array_postdata_for_curlcall($v, $currentdata, $data);
            }
            else {
                $data[] = urlencode($k).'='.urlencode($v);
            }
        }
        $convertedpostdata = implode('&', $data);
        return $convertedpostdata;
    }

    /**
     * Perform the curl HTTP POST call to the endpoint
     *
     * @param type $fn
     * @param type $args
     * @return type
     * @throws MoodleWSException
     */
    protected function
            call($fn, $args = [])
    {
        if (empty($this->token)) {
            throw new MoodleWSException("Missing token");
        }

        $ch = curl_init();
        curl_setopt_array($ch, $this->curl_options);

//        $args['moodlewsrestformat'] = 'json';
        $url = $this->site . $this->endpoint . '?wstoken=' . $this->token
                . '&moodlewsrestformat=json&wsfunction=' . $fn;
        $this->logger->debug("Prepare function {fn} at endpoint: {url}",
                ['fn' => $fn, 'url' => $url]);
        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_POST, 1);
        if ( is_array($args) || is_object($args) ) {
            $postbody = $this->format_postdata_for_curlcall($args);
        }
        else {
            $postbody = $args;
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postbody);
        $this->logger->debug("Call function {fn} with arguments: {args}, data: {data}",
                ['fn' => $fn, 'args' => $args, 'data' => $postbody]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $payload = curl_exec($ch);
        $info = curl_getinfo($ch);
        if ( $info['http_code'] != 200 ) {
            $error = curl_error($ch);
            throw new MoodleWSException($error, $info['http_code']);
        }

        $result = json_decode($payload);
        if ( !empty($result->exception) ) {
            if (!empty($result->debuginfo)) {
                $this->logger->debug("Function {fn} exception {debuginfo}", ['fn' => $fn] + (array)$result);
            }
            throw new MoodleWSException($result->message);
        }

        return $result;
    }

    public function __call($name, $arguments)
    {
        return $this->call($name, empty($arguments) ? [] : $arguments[0]);
    }

    protected function get($url) {
        $ch = curl_init();
        curl_setopt_array($ch, $this->curl_options);

        $url = $this->site . $url;
        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $payload = curl_exec($ch);
        $info = curl_getinfo($ch);
        if ( $info['http_code'] != 200 ) {
            $error = curl_error($ch);
            throw new MoodleWSException($error, $info['http_code']);
        }

        $result = json_decode($payload);
        if ( !empty($result->exception) ) {
            if (!empty($result->debuginfo)) {
                $this->logger->debug("Get {url} exception info {debuginfo}", ['url' => $url] + (array)$result);
            }
            throw new MoodleWSException($result->message);
        }

        return $result;
    }

    public function newToken($username, $password, $service = 'admin') {
        $response = $this->get("/login/token.php?username=$username&password=$password&service=$service");

        return $response->token;
    }

    /**
     * Upload a file in the draft area.
     *
     * See: https://docs.moodle.org/dev/Web_services_files_handling
     *
     * @param string $filename The local file name
     * @param string $filepath The remote file path
     */
    public function upload($filename, $filepath = '/') {
        $params = [
            'file_box' => new \CURLFile($filename),
            'filepath' => $filepath,
            'token' => $this->token
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $this->curl_options);

        curl_setopt($ch, CURLOPT_URL, $this->site . '/webservice/upload.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        //curl_setopt($ch, CURLOPT_VERBOSE, true); per debug
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        if ( $info['http_code'] != 200 ) {
            $error = curl_error($ch);
            throw new MoodleWSException($error, $info['http_code']);
        }

        return $response;
    }
}
