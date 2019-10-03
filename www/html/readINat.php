<?php

require_once "inatHelpers.php";
require_once "log2.php";
require_once "inat2dw.php";
require_once "mysql.php";
require_once "_secrets.php";
require_once "postToAPI.php";

ini_set("default_socket_timeout", 90); // Timeout for file_get_contents
const SLEEP_SECONDS = 5;


// ------------------------------------------------------------------------------------------------
// CHECK PARAM VALIDITY

// Check that params are set
// todo: key is not needed for newUpdate & fullUpdate
if (!isset($_GET['mode']) || !isset($_GET['key']) || !isset($_GET['destination'])) {
  log2("ERROR", "Missing parameters", "log/inat-obs-log.log");
}

// Allow dryrun only on single & deleteSingle, to avoid misunderstandings
if ("dryrun" == $_GET['destination']) {
  if ("single" != $_GET['mode'] && "deleteSingle" != $_GET['mode'])
  log2("ERROR", "Dryrun is only allowed with mode=single|deleteSingle", "log/inat-obs-log.log");
}

// ------------------------------------------------------------------------------------------------
// STARTUP

echo "<pre>";

if ("test" == $_GET['destination']) {
  startupMsg("STARTED TEST RUN...");
  $database = new mysqlDb("inat_push");
  $apiRoot = "https://apitest.laji.fi/v0/warehouse/push?access_token=" . APITEST_ACCESS_TOKEN;
}
elseif ("production" == $_GET['destination']) {
  startupMsg("STARTED PRODUCTION RUN...");
  $database = new mysqlDb("inat_push_production");
  $apiRoot = "https://api.laji.fi/v0/warehouse/push?access_token=" . API_ACCESS_TOKEN;
}
elseif ("dryrun" == $_GET['destination']) {
  startupMsg("STARTED DRYRUN...");
}
else {
  startupMsg("STARTED UNKNOWN...");
  log2("ERROR", "Unknown destination value", "log/inat-obs-log.log");
}

// ------------------------------------------------------------------------------------------------
// SINGLE
// This will push single observation to DW

if ("single" == $_GET['mode']) {
  log2("NOTICE", "Started: single " . $_GET['key'], "log/inat-obs-log.log");

  // Needed in case there are no observations
  $dwObservations = Array();
  $databaseObservations = Array();

  $data = getObsArr_singleId($_GET['key']);

  $obs = $data['results'][0]; // In this case just one observation
  $dwObs = observationInat2Dw($obs);
  if ($dwObs) {
    $dwObservations[] = $dwObs;
    $databaseObservations[] = $obs;
  }

  pushFactory($dwObservations, $_GET['destination']);

  // This only handles observations submitted to DW, after they have been submitted
  logObservationsToDatabase($databaseObservations, 0, $database);
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
  if ("dryrun" != $_GET['destination']) {
    $database->updateStatus($_GET['key'], -1);
  }
}

// ------------------------------------------------------------------------------------------------
// MANUAL
// This will run based on manually defined getLimit, which is based on $getLimit * $perPage, or until no more observations are available from iNat

elseif ("manual" == $_GET['mode']) {

  $perPage = 100;
  $getLimit = 1000;

  log2("NOTICE", "Started: manual with perPage $perPage, getLimit $getLimit, key " . $_GET['key'], "log/inat-obs-log.log");

  // Needed in case there are no observations
  $dwObservations = Array();
  $databaseObservations = Array();

  $idAbove = $_GET['key'];

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

      // Convert
      $dwObs = observationInat2Dw($obs);
      if ($dwObs) {
        $dwObservations[] = $dwObs;
        $databaseObservations[] = $obs;
      }
    
      // Prepare for next observation
      $idAbove = $obs['id'];
    }

    pushFactory($dwObservations, $_GET['destination']);

    // Log after push if successful
    logObservationsToDatabase($databaseObservations, 0, $database); // todo: 0 = first upload, 1 = update

    // Prepare for next round
    unset($dwObservations);
    unset($databaseObservations);

    log2("D", "MEM: " . memory_get_usage(), "log/inat-obs-log.log");

    $i++;
    sleep(SLEEP_SECONDS); // improve: deduct time it took to run conversion & POST from the target sleep time
  }
}

// ------------------------------------------------------------------------------------------------
// NEWUPDATE
// This will run until $getLimit or when no more observations available from iNat
// If getLimit is reached, it will not update the updated time in database. Therefore next run will reupdate everything, which ensures that everything is handled (unless $getLimit is reached again), but places more burden on the DW.

elseif ("newUpdate" == $_GET['mode']) {

  $perPage = 100;
//  $perPage = 2; // Debug

  $getLimit = 10;// High getLimit in production, should be enough for a long time if this is run daily
//  $getLimit = 2; // Debug, must always be >1, otherwise database time will not be set

  // Needed in case there are no observations
  $dwObservations = Array();
  $databaseObservations = Array();

  log2("NOTICE", "Started: newUpdate with perPage $perPage, getLimit $getLimit", "log/inat-obs-log.log");

  if (isset($_GET['key'])) {
    log2("WARNING", "Note that key param has no effect in this mode.", "log/inat-obs-log.log");
  }

  // Need to generate update time here, since observations are coming from the API in random order -> cannot use their times
  // todo: timezone depends on server time settings?!
  //  $updatedSince = "2019-09-26T00:00:00+03:00"; // debug
  $updateStartedTime = date("Y-m-d") . "T" . date("H:i:s") . "+03:00";
  $updateStartedTime = date("Y-m-d") . "T" . date("H:i:s") . "+00:00"; // Works with the Docker setup

  $updatedSince = $database->getLatestUpdate();

  $idAbove = 0; // start value

  $i = 1;

  // Per GET
  while ($i <= $getLimit) {

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

      // Convert
      $dwObs = observationInat2Dw($obs);
      if ($dwObs) {
        $dwObservations[] = $dwObs;
        $databaseObservations[] = $obs;
      }

      // Prepare for next observation
      $idAbove = $obs['id'];
    }

    pushFactory($dwObservations, $_GET['destination']);

    // Log after push if successful
    logObservationsToDatabase($databaseObservations, 0, $database); // todo: 0 = first upload, 1 = update

    // Prepare for next round
    unset($dwObservations);
    unset($databaseObservations);

    $i++;
    sleep(SLEEP_SECONDS); // improve: deduct time it took to run conversion & POST from the target sleep time
  }
}

// ------------------------------------------------------------------------------------------------
// FULLUPDATE
// This will run through all observations, and A) updates only changed obs and 2) Marks which observations have been deleted.
// This mode is the only one to get updates of old observations which have switched to open licenses

elseif ("fullUpdate" == $_GET['mode']) {
  $allHandled = FALSE;

  $perPage = 100; // Production
//  $perPage = 2; // Debug, must always be >1, otherwise database time will not be set

  $getLimit = 1000; // Production: no getLimit
//  $getLimit = 2; // Debug

  // Needed in case there are no observations
  $dwObservations = Array();
  $databaseObservations = Array();

  log2("NOTICE", "Started: fullUpdate with perPage $perPage, getLimit $getLimit", "log/inat-obs-log.log");

  $i = 1;

  $idAbove = 0; // Production
  $idAbove = $_GET['key']; // Debug

  // Per GET
  while ($i <= $getLimit) {

    $data = getObsArr_basedOnId($idAbove, $perPage);

    if (0 === $data['total_results']) {
      log2("NOTICE", "No more results from API with idAbove " . $idAbove, "log/inat-obs-log.log");
      $allHandled = TRUE;
      break;
    }

    // Per observation
    foreach ($data['results'] as $nro => $obs) {

      $id = $obs['id'];
      $hash = hashInatObservation($obs);
      $obsNotChanged = $database->doesHashExist($id, $hash);

      if ($obsNotChanged) {
        // Just log to db
        $databaseObservations[] = $obs;
        log2("NOTICE", "Observation has NOT changed: " . $obs['id'], "log/inat-obs-log.log");
      }
      else {
        log2("NOTICE", "Observation has changed: " . $obs['id'], "log/inat-obs-log.log");
        // Push obs to DW and log to db
        // This covers both old and new obs in DW

        // Convert
        $dwObs = observationInat2Dw($obs);
        if ($dwObs) {
          $dwObservations[] = $dwObs;
          $databaseObservations[] = $obs;
        }
      }

      // Prepare for next observation
      $idAbove = $obs['id'];
    }

    pushFactory($dwObservations, $_GET['destination']);

    // Log after push if successful
    logObservationsToDatabase($databaseObservations, 1, $database); // todo: 0 = first upload, 1 = update

    // Prepare for next round
    unset($dwObservations);
    unset($databaseObservations);

    $i++;
    sleep(SLEEP_SECONDS); // improve: deduct time it took to run conversion & POST from the target sleep time
  }

  // Show warning /error when debugging, because this needs manual fixing
  if (FALSE == $allHandled) {
    log2("WARNING", "WARNING! Process stopped before all observations handled! Database is now in incostent state. This should ony happen when debugging the system", "log/inat-obs-log.log");
    // Fix database using UPDATE observations SET status = 0 WHERE status = 2;

    if ("production" == $_GET['destination']) {
      log2("ERROR", "Using debug values in production, stopping...", "log/inat-obs-log.log");
    }
  }

  // Updated database values
  $database->set0to2();
  $database->set1to0();

}
else {
  log2("ERROR", "Incorrect mode", "log/inat-obs-log.log");
}

echo "\n\nEND\n";
log2("END", "", "log/inat-obs-log.log");

if (isset($database)) {
  $database->close();
}


// ------------------------------------------------------------------------------------------------
// FUNCTIONS

function deleteFactory($documentId, $destination) {
  if ("dryrun" == $destination) {
    echo $documentId;
  }
  else {
    deleteFromApi($documentId);
  }
  return NULL;
}

function pushFactory($data, $destination) {
  $json = compileDwJson($data);
  if (FALSE == $json) {
    log2("NOTICE", "No observations to push ", "log/inat-obs-log.log");
    return FALSE;
  }
  else {
    if ("dryrun" == $destination) {
      print_r ($data);
    }
    else {
      postToApi($json);
    }
    return NULL;
  }
}

function logObservationsToDatabase($observations, $status, $database) {
  /*
  Statuses:
  0 = Observation copied to DW.
  1 = Temporary value for observations that are still in DW. If this is in db, it meanst that update execution has stopped unexpectedly. In this case, these shoud be set -> 0 manually, and then update re-run.
  2 = Observation deleted frim iNat, but still present in DW, waiting to be removed.
  -1 = Observation deleted from iNat and DW.
  */

  foreach ($observations as $nro => $obs) {
    $hash = hashInatObservation($obs);
    $database->push($obs['id'], $hash, $status);
  }

  return NULL;
}

function compileDwJson($dwObservations) {
  // todo: is there more efficient way to do this? Move json encoding here, to avoid first encoding and then decoding?
  if (empty($dwObservations)) {
    return FALSE;    
  }
  else {
    $dwRoot['schema'] = "laji-etl";
    $dwRoot['roots'] = $dwObservations;
    $dwJson = json_encode($dwRoot);
    return $dwJson;
  }
}

function getObsArr_basedOnUpdatedSince($idAbove, $perPage, $updatedSince) {
//  https://api.inaturalist.org/v1/observations?updated_since=2019-09-25T18%3A00%3A00&order=desc&order_by=created_at

  $updatedSince = urlencode($updatedSince);

  $url = "http://api.inaturalist.org/v1/observations?captive=false&license=cc-by%2Ccc-by-nc%2Ccc-by-nd%2Ccc-by-sa%2Ccc-by-nc-nd%2Ccc-by-nc-sa%2Ccc0&place_id=7020&page=1&per_page=" . $perPage . "&order=asc&order_by=id&updated_since=" . $updatedSince . "&id_above=" . $idAbove;
  log2("NOTICE", $url, "log/inat-obs-log.log");

  log2("NOTICE", "Fetching $perPage obs with updatedSince $updatedSince", "log/inat-obs-log.log");

  $observationsJson = file_get_contents($url);

  return checkInatApiError($observationsJson);
}

function getObsArr_basedOnId($idAbove, $perPage) {
  $url = "http://api.inaturalist.org/v1/observations?captive=false&license=cc-by%2Ccc-by-nc%2Ccc-by-nd%2Ccc-by-sa%2Ccc-by-nc-nd%2Ccc-by-nc-sa%2Ccc0&place_id=7020&page=1&per_page=" . $perPage . "&order=asc&order_by=id&id_above=" . $idAbove;
  log2("NOTICE", $url, "log/inat-obs-log.log");

  log2("NOTICE", "Fetching $perPage obs with idAbove $idAbove", "log/inat-obs-log.log");

  $observationsJson = file_get_contents($url);

  return checkInatApiError($observationsJson);
}

function getObsArr_singleId($id) {
  $url = "https://api.inaturalist.org/v1/observations?id=" . $id . "&order=desc&order_by=created_at&include_new_projects=true";
  log2("NOTICE", $url, "log/inat-obs-log.log");

  log2("DEBUG", "fetched url $url", "log/inat-obs-log.log");

  $observationsJson = file_get_contents($url);

  return checkInatApiError($observationsJson);
}

function checkInatApiError($observationsJson) {
  // Error handling by file_get_contents should be enough
  if (FALSE == $observationsJson) {
    log2("ERROR", "iNat API responded with error, check your params", "log/inat-obs-log.log");
  }
  $observationsArr = json_decode($observationsJson, TRUE);

  // ...but here's code for handling it by error json returned by iNat
  /*
  if (isset($observationsArr['error'])) {
    if ("Error" == $observationsArr['error'] || "200" != $observationsArr['status']) {
      log2("ERROR", "iNat API responded with error " . $observationsArr['status'], "log/inat-obs-log.log");
    }
  }
  */

  return $observationsArr;
}

function startupMsg($msg) {
  echo $msg . "\n";
  log2("NOTICE", " ... " . $msg . " ... ... ... ... ... ... ... ... ...", "log/inat-obs-log.log");
}

