<?php

require_once "_secrets.php";

function postToAPItest($data) {
  
  $apiURL = "https://apitest.laji.fi/v0/warehouse/push?access_token=" . $apitest_access_token;
}


// MOCKUP
function postToMockAPI($data) {
  /*
  // Expects data in format:

  $data = Array();
  $data['sourceId'] = "HR.TEST";
  $data['documents'] = "Json goes here";
  */

  // Inside Docker localhost is 127.0.0.1
  $apiURL = "http://127.0.0.1/mockapi/index.php";

  //$json = json_encode($arr);



  // Prepare POST
  $options = array(
    'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data),
        'ignore_errors' => TRUE   // If not set or set to false, function will not return the response if it encounters an error code
    )
  );
  $context  = stream_context_create($options);

  // POST to API
  $response = @file_get_contents($apiURL, false, $context);

  // Extracting the error code. TODO: Is this reliable?
  $parts = explode(" ", $http_response_header[0]);
  $responseHTTPcode = $parts[1];

  $ret = Array(
    "code" => $responseHTTPcode,
    "response" => $response
  );

  return $ret;

  // Debug
  /*
  echo "<pre>FINISHED\n";
  var_dump($result);
  var_dump($http_response_header);
  */
}

