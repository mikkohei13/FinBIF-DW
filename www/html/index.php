<?php

require_once "mysql.php";
require_once "readINat.php";
require_once "postToAPI.php";
require_once "log2.php";

log2("ok", "Script started");


// Database

$database = new mysqlDb();
$database->connect("inat_push");

//$database->doesIDExist(1234);

$database->push(3, "hash3", 0);

$database->set_latest_update();
echo $database->get_latest_update();



// Get data from iNat

// Test data
$data = Array();
$data['sourceId'] = "HR.TEST";
$data['documents'] = "Json goes here";


// POST to API
$result = postToAPI($data);

if (200 == $result['code']) {
  echo "Successfully posted to API, which responded 200 and: " . $result['response'];
  log2("ok", "Posted to API");
  // Continue processing as usual
}
else {
  echo "Posting to API failed, with responded " . $result['code'] . " and: " . $result['response'];
  log2("error", "Posting to API failed");
  // Stop processing
}

echo " / END";
$database->close();

