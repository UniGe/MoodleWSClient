# A "magic" Moodle web services client

The class MoodleWSClient is a stub to a remote Moodle server. The server must be
configured to expose web services using REST protocol. See Moodle documentation
for this topic.
You can invoke Moodle web services functions as methods of the objec, e.g.:  

```
require_once 'vendor/autoload.php';

$token = ...;  // user token, see Administration Block -> Plugins -> Web Services -> Manage tokens

$moodle = new \UniGe\MoodleWSClient('https://yourmoodle/', $token);

$site_info = $moodle->core_webservice_get_site_info();
echo "Your are connected to {$site_info->sitename}\n";

$courses = $moodle->core_course_get_courses();
foreach ($courses as $course) {
    echo $course->idnumber . " " . $course->fullname . "n";
}

```

## Usage

Install it using composer, searching for unige/moodlewsclient.

To instantiate a client stub:

```
$moodle = new \UniGe\MoodleWSClient($moodle_url);
```

The class implements \Psr\Log\Logger, so you can setup any PSR-3-compatible logger.

If you have a security token, you can pass it to the constructor:

```
$moodle = new \UniGe\MoodleWSClient($moodle_url, $token);
```
or 
```
$moodle = new \UniGe\MoodleWSClient($moodle_url);
$moodle->setToken($token);
```

otherwise, you can obtain one from Moodle:

```
$moodle = new \UniGe\MoodleWSClient($moodle_url);
$token = $moodle->newToken($username, $password, $service);
$moodle->setToken($token);
```

If your network requires a proxy, you can set it before invoking methods:
```
$moodle->setupProxy($host, $port, $user = NULL, $pass = NULL)

```

## Functions and arguments

Using PHP magic method __call(), a MoodleWSClient exposes remote API as local
methods: e.g. a Moodle web service function core_fun() are invoked as $moodle->core_fun().

However, this client is not aware of the functions effectively exposed by the
server because plugins can add own functions and Moodle service definition
can limit the core functions available to users. Thus this class accept any 
function name and raise an Exception if the server cannot understand it.

As a Moodle administrator, you can have a list of functions available on your
server from Administration Block -> Plugins -> Web Services -> API Documentation

Another issue is that Moodle web services requires arguments by name, and this
is emulated in PHP using associative array. Sometimes arguments required by
a function is a little not-intuitive from the documentation.
The MoodleWSClient class automatically serialize objects to the required format,
so you a more fluent style can be use. E.g., to create a course:

```
$course = new stdClass();
$course->fullname = $info->fullname;

$course->shortname = $info->shortname;
$course->categoryid = $category->id;
$course->idnumber = $info->idnumber;
$course->visible = 0;
$course->summary = "<p>{$captions['codins']} {$info->codice_ins}</p>";
// ... other course field

$moodle->core_course_create_courses(['courses' => [$course]]);
```

Please that web service connection setup has a significative overhead;
you can speedup operation passing multiple arguments to functions every
times it is possible. Fortunately, Moodle functions usually operate on arrays
of elements, for instance core_course_create_courses() wants an array of courses
to create in one shot.

## File upload

Even if there is a web service function to upload files, it has poor performances.
Moodle offers an alternative efficient way to upload files using HTTP POST.
The MoodleWSClient class implements it in the method upload().

