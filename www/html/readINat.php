<?php

require_once "inatHelpers.php";
require_once "log2.php";
require_once "inat2dw.php";
require_once "mysql.php";
require_once "_secrets.php";
require_once "postToAPI.php";

echo "START\n";
log2("START", "------------------------------------------", "log/inat-obs-log.log");

$database = new mysqlDb("inat_push");
if (!$database) {
  exit("Exited due to database connection error");
}

// todo: log errors locally, so that I know if some field is missing or something unexpected
// todo try catch for conversion?
/*
Params
- MODE: single | all | since last update | all + delete | delete single
- DESTINATION: dryrun (just display) | save to test | save to prod
- KEY: id or time to begin *after*

Error handling
- If error happens:
  - Log error
  - Stop processing with exit()

Test values
key = 33084315; // Violettiseitikki submitted on 20.9.2019

TODO:
- See push plan in readme
- Plan options and logic for fullUpload, latestUpload, fullUpdate, delete
- FIND OUT WHY ID 33068 PERSISTS?!
- Check that all exit's have logging
- Add since last update -mode, first using last update time as param
- Then handle last update time in database
...
- all + delete
- delete single


*/

echo "<pre>";

// ------------------------------------------------------------------------------------------------

// Check that params are set
if (!isset($_GET['mode']) || !isset($_GET['key']) || !isset($_GET['destination'])) {
  exit ("Exited due to missing parameters.");
}

// SINGLE
if ("single" == $_GET['mode']) {
  log2("NOTICE", "Started: single " . $_GET['key'], "log/inat-obs-log.log");

  $dwObservations = Array();

  $data = getObsArr_singleId($_GET['key']);
  $dwObservations[] = observationInat2Dw($data['results'][0]);

  pushFactory($dwObservations, $_GET['destination']);
  logObservationsToDatabase($data['results'], 0, $database);

//  print_r ($dwObservations);
//  echo hashInatObservation($data['results'][0]);
}

// DELETESINGLE
elseif ("deleteSingle" == $_GET['mode']) {
  log2("NOTICE", "Started: deleteSingle " . $_GET['key'], "log/inat-obs-log.log");

  // Delete from API
  $documentId = "http://tun.fi/HR.3211/" . $_GET['key'];
  deleteFactory($documentId, $_GET['destination']);

  // Trash from database
  if ("dryrun" != $_GET['destination']) {
    $database->updateStatus($_GET['key'], -1);
  }

//  log2("NOTICE", "Going through observations to be deleted", "log/inat-obs-log.log");

//  $numberOfDeleted = deleteNonUpdated($database); // todo: add idAbove & limit (based on page*perpage count), to avoid deleting too much while testing
//  log2("NOTICE", "Finished deleting $numberOfDeleted observations missing from source", "log/inat-obs-log.log");

}

// ALL
elseif ("all" == $_GET['mode']) {
  log2("NOTICE", "Started: all " . $_GET['key'], "log/inat-obs-log.log");
  // See max 10k observations bug: https://github.com/inaturalist/iNaturalistAPI/issues/134

  $perPage = 2;
  $getLimit = 1;
  $idAbove = $_GET['key'];
  $sleepSecondsBetweenGets = 2; // iNat limit: ... keep it to 60 requests per minute or lower, and to keep under 10,000 requests per day

  $i = 1;

  // Per GET
  while ($i <= $getLimit) {
    $dwObservations = Array();

    $data = getObsArr_basedOnId($idAbove, $perPage);

    if (0 === $data['total_results']) {
      log2("NOTICE", "No more results from API with idAbove " . $idAbove, "log/inat-obs-log.log");
      break;
    }

    // Per observation
    foreach ($data['results'] as $nro => $obs) {

      // todo: check if already in database with unchanged hash -> don't update

      // Convert
      // Todo: log and exit() if error converting
      $dwObservations[] = observationInat2Dw($obs);

      // Prepare for next observation
      $idAbove = $obs['id'];
    }

    pushFactory($dwObservations, $_GET['destination']);

    // Log after push if successful
    logObservationsToDatabase($data['results'], 0, $database); // todo: 0 = first upload, 1 = update

    // Prepare for next round
    $i++;
    sleep($sleepSecondsBetweenGets); // improve: deduct time it took to run conversion & POST from the target sleep time
  }
}

echo "END\n";
log2("END", "", "log/inat-obs-log.log");

$database->close();


//--------------------------------------------------------------------------

function deleteNonUpdated($database) {
  // todo: delete from api, update database
  $count = 0;

  // Get id's that were not updated
  $nonUpdatedIds = $database->getNonUpdatedIds();
  print_r ($nonUpdatedIds); // debug

  foreach ($nonUpdatedIds as $nro => $id) {

    // API delete $id

    $documentId = "http://tun.fi/HR.3211/" . $id;
    deleteFromApiTest($documentId);

    /*
    $dwObservations = Array();
    $documentId = "http://tun.fi/HR.3211/" . $id;
    $documentId = "https://www.inaturalist.org/observations/" . $id; // old format, needed to delete old observations

//    $dwObservations[0]['sourceId'] = "http://tun.fi/KE.901";
//    $dwObservations[0]['documentId'] = $documentId;
    $dwObservations[0]['publicDocument']['documentId'] = $documentId;
    $dwObservations[0]['publicDocument']['deleteRequest'] = TRUE;
    $dwJson = compileDwJson($dwObservations);
    postToAPItest($dwJson);
    */
    log2("NOTICE", "Sent DELETE request to API for " . $documentId, "log/inat-obs-log.log");

    // Set trashed in database
    $database->updateStatus($id, -1);

    $count++;
    break; // debug: break after one observation
  }

  return $count;
}

function deleteFactory($documentId, $destination) {
  if ("dryrun" == $destination) {
    pushToEcho($documentId);
  }
  elseif ("test" == $destination) {
    deleteFromApiTest($documentId);
  }
  // Todo here: Push to production
  else {
    exit("Exited due to unknown destination value");
  }
  return NULL;
}

function pushFactory($dwObservations, $destination) {
  if ("dryrun" == $destination) {
    pushToEcho($documentId);
  }
  elseif ("test" == $destination) {
    deleteFromApiTest($documentId);
  }
  // Todo here: Push to production
  else {
    exit("Exited due to unknown destination value");
  }
  return NULL;
}

function pushToEcho($data) {
  log2("NOTICE", "Dryrun", "log/inat-obs-log.log");
  echo "DRYRUN...\n\n";
  print_r ($data);
}

function pushToTestDw($dwObservations) {
  log2("NOTICE", "Pushing to test DW", "log/inat-obs-log.log");
  echo "PUSHING TO TEST DW...\n\n";

  // Compile json file to be sent
  $dwJson = compileDwJson($dwObservations);

  $response = postToAPItest($dwJson);
  // todo: move error handling to mysql? see delete method.
  if (200 == $response['http_code']) {
    log2("SUCCESS", "API responded " . $response['http_code'], "log/inat-obs-log.log");
  }
  else {
    log2("ERROR", "API responded " . $response['http_code'] . " / " . json_encode($response), "log/inat-obs-log.log");
    exit("Exited due to error POSTing to API. See log for details.");
  }
  return NULL;
}

function logObservationsToDatabase($observations, $status, $database) {

  foreach ($observations as $nro => $obs) {
    $hash = hashInatObservation($obs);
    $result = $database->push($obs['id'], $hash, $status);
    if (!$result) {
      log2("ERROR", "Database error " . $database->error, "log/inat-obs-log.log");
      exit("exited due to database error.");
    }
  }

  return NULL;
}

function compileDwJson($dwObservations) {
  $dwRoot = Array();
  $dwRoot['schema'] = "laji-etl";
  $dwRoot['roots'] = $dwObservations;
  $dwJson = json_encode($dwRoot);

  echo "\n" . $dwJson . "\n"; // debug

  return $dwJson;
}

function getObsArr_basedOnId($idAbove, $perPage) {
  $url = "http://api.inaturalist.org/v1/observations?captive=false&license=cc-by%2Ccc-by-nc%2Ccc-by-nd%2Ccc-by-sa%2Ccc-by-nc-nd%2Ccc-by-nc-sa%2Ccc0&place_id=7020&page=1&per_page=" . $perPage . "&order=asc&order_by=id&id_above=" . $idAbove; // new, to avoid pagination bug

  echo $url . "\n"; // debug
  log2("NOTICE", "Fetching $perPage obs with idAbove $idAbove", "log/inat-obs-log.log");

  $observationsJson = file_get_contents($url);
  log2("NOTICE", "Fetch complete", "log/inat-obs-log.log");

  return json_decode($observationsJson, TRUE);
}

function getObsArr_singleId($id) {
  $url = "https://api.inaturalist.org/v1/observations?id=" . $id . "&order=desc&order_by=created_at&include_new_projects=true"; // Fetch single obs using observations endpoint, in order to get in in consistent format

  echo $url . "\n"; // debug
  log2("DEBUG", "fetched url $url", "log/inat-obs-log.log");

  $observationsJson = file_get_contents($url);
  return json_decode($observationsJson, TRUE);
}
