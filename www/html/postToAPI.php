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

