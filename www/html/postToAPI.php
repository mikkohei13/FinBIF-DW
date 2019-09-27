<?php

function postToAPItest($json) {
  
  GLOBAL $apitestAccessToken;

  $apiURL = "https://apitest.laji.fi/v0/warehouse/push?access_token=" . $apitestAccessToken;

  $ch = curl_init($apiURL);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = json_encode(curl_exec($ch));
  $curlInfo = curl_getinfo($ch);
  curl_close($ch);

//  print_r ($curlInfo); // debug

  if (200 != $curlInfo['http_code']) {
    log2("ERROR", "API responded " . $curlInfo['http_code'] . " / " . $response, "log/inat-obs-log.log");
  }
  else {
    log2("NOTICE", "API responded " . $curlInfo['http_code'] . " / " . $response, "log/inat-obs-log.log");
  }
  return $curlInfo;
}

function deleteFromApiTest($documentId) {

  GLOBAL $apitestAccessToken;
  $json = json_encode(Array()); // todo: is this needed?

  $apiURL = "https://apitest.laji.fi/v0/warehouse/push?documentId=" . $documentId . "&access_token=" . $apitestAccessToken;
  echo $apiURL; // debug

  $ch = curl_init($apiURL);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $json); // todo: is this needed?
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = json_encode(curl_exec($ch));
  $curlInfo = curl_getinfo($ch);
  curl_close($ch);

//  print_r ($curlInfo); // debug

  if (200 != $curlInfo['http_code']) {
    log2("ERROR", "API responded " . $curlInfo['http_code'] . " / " . $response, "log/inat-obs-log.log");
  }
  else {
    log2("NOTICE", "API responded " . $curlInfo['http_code'] . " / " . $response, "log/inat-obs-log.log");
  }
  return $curlInfo;
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

