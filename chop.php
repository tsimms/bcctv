<?php

header('Access-Control-Allow-Headers: X-CSRF-Token');
header('Access-Control-Allow-Origin: http://unite.bcctv.org', false);

// init
$config = parse_ini_file("chop.ini", true);
$log_path = "/var/www/drupal/drupal-6.28/sites/bcctv.org/api/v4";
$log = "$log_path/chop.log";

//error_log (print_r(array($_SERVER, $_GET), true), 3, $log);

error_log("[REQUST_METHOD]: " . $_SERVER['REQUEST_METHOD'] . "\n", 3, $log);
if (array_key_exists('REQUEST_METHOD', $_SERVER) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  echo json_encode(array("response" => "options"));
  exit;
}


// if it's a GET request, it's an initial auth request
if (count($_GET)) {
  $site = $_GET['site'];
  if ($site_id = getSiteId($site))
  {
    $key = $_GET['key'];
    if ($config[$site]['key'] && $config[$site]['key'] == $key)
    {
      // authenticated
      $log = "$log_path/chop_$site_id.log";
      $event_id = $_GET['event_id'];
      // send token
      $token = md5(time() . $event_id . $key);
      $data = array(
        "response" => "token",
        "token" => $token
      );
      echo json_encode($data);
      $handle = db_connect();
      $query = "insert into log (token, site_id, start, stop, event_id) values (?, ?, now(), now(), ?)";
      $stmt = $handle->prepare($query);
      $stmt->bind_param("sii", $token, $site_id, $event_id);
      $stmt->execute();
      $stmt->close();
      error_log("SQL: $query\n", 3, $log);
    }
  } else {
    // sorry, something's wrong
    error_log("identity mismatch.\n", 3, $log);
    exit;
  }
  exit;
}

// a post is a logging request
if ($site_id = $_POST['site_id'])
{
  $log = "$log_path/chop_$site_id.log";
}
// processing:
$data["timestamp"] = date("Y-m-d H:i:s");
error_log("Log request: " . $data['timestamp'] . "\n", 3, $log);

$data = array();
foreach ($_POST as $key => $value) {
  error_log("POST key = $key, value = $value\n", 3, $log);
  $data[$key] = $value;
}

error_log("Event isLive: " . $data['event_isLive'] . ".\n", 3, $log);
// don't log unless we're in an actual event.  TO-DO: let's make this configurable.  People may want to log pre-service chat or accommodate that with full data.
if ($data['token'] && ($data['event_isLive'] == 'true')) {
  $handle = db_connect();
  $query = "select id from log where token = ? and event_id = ?";
  error_log("searching for eid " . $data['event_id'] . " and token " . $data['token'] . "\n", 3, $log);
  $stmt = $handle->prepare($query);
  $stmt->bind_param("si", $data['token'], $data['event_id']);
  $stmt->execute();
  $stmt->bind_result($log_id);
  while ($stmt->fetch()) {
    error_log("Found $log_id log entry.\n", 3, $log);
  }
// log to db

  if ($log_id) {
    $query = "update log set
      stop = now(),
      session_id = ?,
      user_id = ?,
      email = ?,
      nickname = ?,
      firstName = ?,
      fullName = ?,
      ip = ?,
      lastLogin = ?,
      referrer = ?
      where id = $log_id
    ";
    $stmt = $handle->prepare($query);
    $stmt->bind_param("sisssssss",
      $data['session_id'],
      $data['user_id'],
      $data['email'],
      $data['nickname'],
      $data['firstName'],
      $data['fullName'],
      $data['ip'],
      $data['lastLogin'],
      $data['referrer']
    );
    $stmt->execute();
    echo json_encode(array("response" => "success"));

  } else {
    echo json_encode(array("response" => "reauthenticate"));
  }
  $stmt->close();
}

exit;

function getSiteId($site) {
  global $config;
  if ($site && $config && isset($config[$site])) {
    $site_id = $config[$site]["site_id"];
  }
  return $site_id;
}

function db_connect() {
  global $config;
  $host = $config["database"]["host"];
  $user = $config["database"]["username"];
  $pass = $config["database"]["password"];
  $db = $config["database"]["db"];
  $handle = new mysqli($host, $user, $pass, $db);
  return $handle;
}

?>
