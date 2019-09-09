<?php
/*
 Mockup endpoint for testing how to POST data into an API.

 This receives POST message, and saves into a file.
 https://api.laji.fi/explorer/#!/Warehouse/post_warehouse_push
 - Data is given in request body.
 - Use error-api to get feedback about processing failures. [Do I need to?]
 - UTF-8
 - Success 200
 - Failure 

 documents
 sourceId

*/
$data = Array();


$responseCode = 200;

if (isset($_GET['DEBUG'])) {
  // Test
  $data['sourceId'] = "This data comes from test API. ";
  $data['documents'] = "Documents shall go here.";
}
else {
  // POST
  $data['sourceId'] = $_POST['sourceId'];
  $data['documents'] = $_POST['documents'];
}


// ------------

$bytes = saveToFile($data);


http_response_code($responseCode);

if (FALSE == $bytes) {
  echo "Error saving data to a file.";
}
else {
  // This should be same as laji.fi's success response
  echo "Saved data to file, $bytes bytes";
}


function saveToFile($json) {
  $filename = "log/" . date("Ymd-His") . ".json";
  $bytes = file_put_contents($filename, $json);
  return $bytes;
}

