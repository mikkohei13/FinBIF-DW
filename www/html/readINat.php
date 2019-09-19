<?php

require_once "inatHelpers.php";
require_once "log2.php";
require_once "inat2dw.php";
require_once "mysql.php";
require_once "_secrets.php";
require_once "postToAPI.php";


$database = new mysqlDb();
$database->connect("inat_push"); // todo: prod, test, dryrun 


// todo: log errors locally, so that I know if some field is missing or something unexpected
// todo try catch for conversion?
/*
Options
- single | all | since last update | all + delete | delete single
- dryrun (just display) | save to test | save to prod
*/

echo "<pre>";

// ------------------------------------------------------------------------------------------------

$dwObservations = Array();


// DEBUG
if (isset($_GET['debug'])) {
  $data = getObsArr_singleId($_GET['debug']);
  $dwObservations[] = observationInat2Dw($data['results'][0]);

  echo hashInatObservation($data['results'][0]);
}
// PRODUCTION
else {
  // See max 10k observations bug: https://github.com/inaturalist/iNaturalistAPI/issues/134

  $perPage = 10;
  $getLimit = 2;
  $idAbove = 0; // Start value
  $idAbove = 33084315; // Test value for observation submitted on 20.9.2019

  $sleepSecondsBetweenGets = 2; // iNat limit: ... keep it to 60 requests per minute or lower, and to keep under 10,000 requests per day

  $i = 1;

  // Per GET
  while ($i <= $getLimit) {

    $data = getObsArr_basedOnId($idAbove, $perPage);

    if (0 === $data['total_results']) {
      log2("NOTICE", "No more results from API with idAbove " . $idAbove, "log/inat-obs-log.log");
      break;
    }

    // Per observation
    foreach ($data['results'] as $nro => $obs) {
//      print_r (obs);
//      echo $obs['id'] . "\n"; // debug

      // Convert
      $dwObs = observationInat2Dw($obs);

      // Todo: log and exit() if error

      $dwObservations[] = $dwObs;

      // Log to database
      $hash = hashInatObservation($obs);
      $result = $database->push($obs['id'], $hash, 0);
      if (!$result) {
        echo $database->error . "\n";
      }

      $idAbove = $obs['id'];
    }

    $i++;
    sleep($sleepSecondsBetweenGets);
  }
}

// todo: move this to GET while, so that 15k obs are not sent as one file...
// todo: if error from api, stop processing and log error
// Compile json file to be sent
$dwRoot = Array();
$dwRoot['schema'] = "laji-etl";
$dwRoot['roots'] = $dwObservations;

$dwJson = json_encode($dwRoot);
echo "\n" . $dwJson . "\n";

$response = postToAPItest($dwJson, $apitestAccessToken);
log2("NOTICE", "API responded " . $response['http_code'], "log/inat-obs-log.log");



log2("NOTICE", "finished", "log/inat-obs-log.log");

$database->close();


//--------------------------------------------------------------------------


function getObsArr_basedOnId($idAbove, $perPage) {
  $url = "http://api.inaturalist.org/v1/observations?captive=false&license=cc-by%2Ccc-by-nc%2Ccc-by-nd%2Ccc-by-sa%2Ccc-by-nc-nd%2Ccc-by-nc-sa%2Ccc0&place_id=7020&page=1&per_page=" . $perPage . "&order=asc&order_by=id&id_above=" . $idAbove; // new, to avoid pagination bug

  echo $url . "\n"; // debug
  log2("NOTICE", "Fetched $perPage obs with idAbove $idAbove", "log/inat-obs-log.log");

  $observationsJson = file_get_contents($url);
  return json_decode($observationsJson, TRUE);
}

function getObsArr_singleId($id) {
  $url = "https://api.inaturalist.org/v1/observations?id=" . $id . "&order=desc&order_by=created_at&include_new_projects=true"; // Fetch single obs using observations endpoint, in order to get in in consistent format

  echo $url . "\n"; // debug
  log2("DEBUG", "fetched url $url", "log/inat-obs-log.log");

  $observationsJson = file_get_contents($url);
  return json_decode($observationsJson, TRUE);
}
