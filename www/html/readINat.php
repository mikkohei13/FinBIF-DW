<?php

require_once "inatHelpers.php";
require_once "log2.php";
require_once "inat2dw.php";
require_once "mysql.php";
require_once "_secrets.php";
require_once "postToAPI.php";

echo "<pre>";
echo "START\n";
log2("START", "------------------------------------------", "log/inat-obs-log.log");

// Check that params are set
// todo: key is not needed for newUpdate
if (!isset($_GET['mode']) || !isset($_GET['key']) || !isset($_GET['destination'])) {
  exit ("Exited due to missing parameters.");
}

// Allow dryrun only on single, to avoid misunderstandings
if ("dryrun" == $_GET['destination'] && "single" != $_GET['mode']) {
  exit("Dryrun is only allowed with mode=single");
}

const SLEEP_SECONDS = 2;

$database = new mysqlDb("inat_push");
if (!$database) {
  exit("Exited due to database connection error");
}

// todo: log errors locally, so that I know if some field is missing or something unexpected
// todo try catch for conversion?
/*
Params
- MODE: single | all | since last update | all + delete | delete single
- DESTINATION: dryrun (just display) | test | prod
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


// ------------------------------------------------------------------------------------------------
// SINGLE
// This will push single observation to DW

if ("single" == $_GET['mode']) {
  log2("NOTICE", "Started: single " . $_GET['key'], "log/inat-obs-log.log");

  $dwObservations = Array();

  $data = getObsArr_singleId($_GET['key']);

  $obs = $data['results'][0]; // In this case just one observation
  $dwObs = observationInat2Dw($obs);
  if ($dwObs) {
    $dwObservations[] = $dwObs;
    $databaseObservations[] = $obs;
  }

  $dwJson = compileDwJson($dwObservations);
  pushFactory($dwJson, $_GET['destination']);

  // This needs to only handle observations submitted to DW, after they have been submitted
  logObservationsToDatabase($databaseObservations, 0, $database);

//  print_r ($dwObservations);
//  echo hashInatObservation($data['results'][0]);
}

// ------------------------------------------------------------------------------------------------
// DELETESINGLE
// This will delete a single observation from DW

elseif ("deleteSingle" == $_GET['mode']) {
  log2("NOTICE", "Started: deleteSingle " . $_GET['key'], "log/inat-obs-log.log");

  // Delete from API
  $documentId = "http://tun.fi/HR.3211/" . $_GET['key'];
  deleteFactory($documentId, $_GET['destination']);

  // Trash from database
  $database->updateStatus($_GET['key'], -1);

//  log2("NOTICE", "Going through observations to be deleted", "log/inat-obs-log.log");

//  $numberOfDeleted = deleteNonUpdated($database); // todo: add idAbove & limit (based on page*perpage count), to avoid deleting too much while testing
//  log2("NOTICE", "Finished deleting $numberOfDeleted observations missing from source", "log/inat-obs-log.log");
}

// ------------------------------------------------------------------------------------------------
// MANUAL
// This will run based on manually defined limit, which is based on $getLimit * $perPage, or until no more observations are available from iNat

elseif ("manual" == $_GET['mode']) {

  $perPage = 2;
  $getLimit = 2;

  log2("NOTICE", "Started: manual with perPage $perPage, getLimit $getLimit, key " . $_GET['key'], "log/inat-obs-log.log");

  $idAbove = $_GET['key'];

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
      $dwObs = observationInat2Dw($obs);
      if ($dwObs) {
        $dwObservations[] = $dwObs;
        $databaseObservations[] = $obs;
      }
    
      // Prepare for next observation
      $idAbove = $obs['id'];
    }

    $dwJson = compileDwJson($dwObservations);
    pushFactory($dwJson, $_GET['destination']);

    // Log after push if successful
    logObservationsToDatabase($databaseObservations, 0, $database); // todo: 0 = first upload, 1 = update

    // Prepare for next round
    $i++;
    sleep(SLEEP_SECONDS); // improve: deduct time it took to run conversion & POST from the target sleep time
  }
}

// ------------------------------------------------------------------------------------------------
// NEWUPDATE
// This will run until $limit or when no more observations available from iNat
// If limit is reached, it will not update the updated time in database. Therefore next run will reupdate everything, which ensures that everything is handled (unless $limit is reached again), but places more burden on the DW.

elseif ("newUpdate" == $_GET['mode']) {

  $perPage = 10;

  $limit = 1000;// High limit in production, should be enough if this is run daily
  $limit = 10; // debug

  log2("NOTICE", "Started: newUpdate", "log/inat-obs-log.log");

  // Need to generate update time here, since observations are coming from the API in random order -> cannot use their times
  // todo: timezone depends on server time settings?!
  //  $updatedSince = "2019-09-26T00:00:00+03:00"; // debug
  $updateStartedTime = date("Y-m-d") . "T" . date("H:i:s") . "+03:00";
  $updateStartedTime = date("Y-m-d") . "T" . date("H:i:s") . "+00:00"; // Works with the Docker setup

  $updatedSince = $database->getLatestUpdate();

  $idAbove = 0; // start value

  $i = 1;

  // Per GET
  while ($i <= $limit) {

    $dwObservations = Array();
    log2("D", "$idAbove, $perPage, $updatedSince", "log/inat-obs-log.log");

    $data = getObsArr_basedOnUpdatedSince($idAbove, $perPage, $updatedSince);

    // If no more observations
    if (0 === $data['total_results']) {
      log2("NOTICE", "No more results from API with updatedSince " . $updatedSince, "log/inat-obs-log.log");
      $database->setLatestUpdate($idAbove, $updateStartedTime);
      break;
    }

    // Per observation
    foreach ($data['results'] as $nro => $obs) {

      // todo: check if already in database with unchanged hash -> don't update

      // Convert
      // Todo: log and exit() if error converting
      $dwObs = observationInat2Dw($obs);
      if ($dwObs) {
        $dwObservations[] = $dwObs;
        $databaseObservations[] = $obs;
      }

      // Prepare for next observation
      $idAbove = $obs['id'];
    }

    $dwJson = compileDwJson($dwObservations);
    pushFactory($dwJson, $_GET['destination']);

    // Log after push if successful
    logObservationsToDatabase($databaseObservations, 0, $database); // todo: 0 = first upload, 1 = update

    // Prepare for next round
    $i++;
    sleep(SLEEP_SECONDS); // improve: deduct time it took to run conversion & POST from the target sleep time
  }
}

echo "\n\nEND\n";
log2("END", "", "log/inat-obs-log.log");

$database->close();


// ------------------------------------------------------------------------------------------------
// FUNCTIONS

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

function deleteFactory($data, $destination) {
  if ("dryrun" == $destination) {
    pushToEcho($data);
  }
  elseif ("test" == $destination) {
    deleteFromApiTest($data);
  }
  // Todo here: Push to production
  else {
    exit("Exited due to unknown destination value");
  }
  return NULL;
}

function pushFactory($data, $destination) {
  log2("D", "pushFactory called: destination $destination", "log/inat-obs-log.log");

  if ("dryrun" == $destination) {
    pushToEcho($data);
  }
  elseif ("test" == $destination) {
    postToAPItest($data);
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

/*
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
*/

function logObservationsToDatabase($observations, $status, $database) {

  foreach ($observations as $nro => $obs) {
    $hash = hashInatObservation($obs);
    $result = $database->push($obs['id'], $hash, $status);
    if (!$result) {
      log2("ERROR", "Database error " . $database->error, "log/inat-obs-log.log");
    }
  }

  return NULL;
}

function compileDwJson($dwObservations) {
  if (empty($dwObservations)) {
    log2("ERROR", "No observations to send ", "log/inat-obs-log.log");
  }

  $dwRoot = Array();
  $dwRoot['schema'] = "laji-etl";
  $dwRoot['roots'] = $dwObservations;
  $dwJson = json_encode($dwRoot);

//  echo "\nHERE'S JSON\n" . $dwJson . "\n"; // debug
  return $dwJson;
}

function getObsArr_basedOnUpdatedSince($idAbove, $perPage, $updatedSince) {

//  https://api.inaturalist.org/v1/observations?updated_since=2019-09-25T18%3A00%3A00&order=desc&order_by=created_at

//  $updatedSince = str_replace(":", "%3A", $updatedSince);

  $updatedSince = urlencode($updatedSince);

  $url = "http://api.inaturalist.org/v1/observations?captive=false&license=cc-by%2Ccc-by-nc%2Ccc-by-nd%2Ccc-by-sa%2Ccc-by-nc-nd%2Ccc-by-nc-sa%2Ccc0&place_id=7020&page=1&per_page=" . $perPage . "&order=asc&order_by=id&updated_since=" . $updatedSince . "&id_above=" . $idAbove;
  log2("NOTICE", $url, "log/inat-obs-log.log");

  log2("NOTICE", "Fetching $perPage obs with updatedSince $updatedSince", "log/inat-obs-log.log");

  $observationsJson = file_get_contents($url);
  log2("NOTICE", "Fetch complete", "log/inat-obs-log.log");

  return json_decode($observationsJson, TRUE);
}

function getObsArr_basedOnId($idAbove, $perPage) {
  $url = "http://api.inaturalist.org/v1/observations?captive=false&license=cc-by%2Ccc-by-nc%2Ccc-by-nd%2Ccc-by-sa%2Ccc-by-nc-nd%2Ccc-by-nc-sa%2Ccc0&place_id=7020&page=1&per_page=" . $perPage . "&order=asc&order_by=id&id_above=" . $idAbove;
  log2("NOTICE", $url, "log/inat-obs-log.log");

  log2("NOTICE", "Fetching $perPage obs with idAbove $idAbove", "log/inat-obs-log.log");

  $observationsJson = file_get_contents($url);
  log2("NOTICE", "Fetch complete", "log/inat-obs-log.log");

  return json_decode($observationsJson, TRUE);
}

function getObsArr_singleId($id) {
  $url = "https://api.inaturalist.org/v1/observations?id=" . $id . "&order=desc&order_by=created_at&include_new_projects=true";
  log2("NOTICE", $url, "log/inat-obs-log.log");

  log2("DEBUG", "fetched url $url", "log/inat-obs-log.log");

  $observationsJson = file_get_contents($url);
  log2("NOTICE", "Fetch complete", "log/inat-obs-log.log");

  return json_decode($observationsJson, TRUE);
}
