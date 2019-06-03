<?php
namespace UniGe;

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

    public function setToken($token) {
        $this->token = $token;
    }

    /**
     * Recursive function formating an array in POST parameter
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

    protected function call($fn, $args = [])
    {
        if (empty($this->token)) {
            throw new \Exception("Manca il token");
        }

        $ch = curl_init();
        curl_setopt_array($ch, $this->curl_options);

//        $args['moodlewsrestformat'] = 'json';
        $url = $this->site . $this->endpoint . '?wstoken=' . $this->token
                . '&moodlewsrestformat=json&wsfunction=' . $fn;
        $this->logger->debug("WS endpoint: {url}", ['url' => $url]);
        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_POST, 1);
        if ( is_array($args) || is_object($args) ) {
            $postbody = $this->format_postdata_for_curlcall($args);
        }
        else {
            $postbody = $args;
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postbody);
        $this->logger->debug("WS POST args: {args}, data: {data}", ['args' => $args, 'data' => $postbody]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $payload = curl_exec($ch);
        $info = curl_getinfo($ch);
        if ( $info['http_code'] != 200 ) {
            $error = curl_error($ch);
            throw new \Exception($error, $info['http_code']);
        }

        $result = json_decode($payload);
        if ( !empty($result->exception) ) {
            if (!empty($result->debuginfo)) {
                $this->logger->debug("WS exception info {debuginfo}", (array)$result);
            }
            throw new \Exception($result->message);
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
            throw new Exception($error, $info['http_code']);
        }

        $result = json_decode($payload);
        if ( !empty($result->exception) ) {
            if (!empty($result->debuginfo)) {
                $this->logger->debug("WS exception info {debuginfo}", (array)$result);
            }
            throw new Exception($result->message);
        }

        return $result;
    }

    public function newToken($username, $password, $service = 'admin') {
        $response = $this->get("/login/token.php?username=$username&password=$password&service=$service");

        return $response->token;
    }

    /**
     * Vedi
     *   https://docs.moodle.org/dev/Web_services_files_handling
     *
     * @param type $file
     */
    public function upload($filename) {
        $filepath = '/compiti/';

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
            throw new \Exception($error, $info['http_code']);
        }

        return $response;
    }
}
