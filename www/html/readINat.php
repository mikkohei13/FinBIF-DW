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
// todo: key is not needed for newUpdate & fullUpdate
if (!isset($_GET['mode']) || !isset($_GET['key']) || !isset($_GET['destination'])) {
  log2("ERROR", "Missing parameters", "log/inat-obs-log.log");
}

// Allow dryrun only on single & deleteSingle, to avoid misunderstandings
if ("dryrun" == $_GET['destination']) {
  if ("single" != $_GET['mode'] && "deleteSingle" != $_GET['mode'])
  log2("ERROR", "Dryrun is only allowed with mode=single|deleteSingle", "log/inat-obs-log.log");
}

const SLEEP_SECONDS = 5;

$database = new mysqlDb("inat_push");

/*
Params
- MODE: single | deleteSingle | manual | newUpdate | fullUpdate
- DESTINATION: dryrun (just display) | test | prod
- KEY: id or time to begin *after*

Error handling
- If error happens, log the error, which also exits the script
- Note: having no observations to submit is not an error, because processing must continue from the next page.

Test values
- normal observation 33084315; (Violettiseitikki submitted on 20.9.2019)
- deleted observation 33586301 (Hypoxylaceae observed & submitted 29.9.2019)
- without date & taxon: 30092946

TODO:
- Test if while doing newUpdate FinBIF DW responds other than 200, will the obs be pushed again later?
- FIND OUT WHY ID 33068 PERSISTS?!
- See todo's in conversion function
- create a prod database, select this when connecting to db. thus not needed in pushFactory & deleteFactory
- Licence change should trigger hash change

*/


// ------------------------------------------------------------------------------------------------
// SINGLE
// This will push single observation to DW

if ("single" == $_GET['mode']) {
  log2("NOTICE", "Started: single " . $_GET['key'], "log/inat-obs-log.log");

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
  $getLimit = 2;

  log2("NOTICE", "Started: manual with perPage $perPage, getLimit $getLimit, key " . $_GET['key'], "log/inat-obs-log.log");

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
// This will run until $getLimit or when no more observations available from iNat
// If getLimit is reached, it will not update the updated time in database. Therefore next run will reupdate everything, which ensures that everything is handled (unless $getLimit is reached again), but places more burden on the DW.

elseif ("newUpdate" == $_GET['mode']) {

  $perPage = 100;

  $getLimit = 100;// High getLimit in production, should be enough for a long time if this is run daily
  $getLimit = 10; // Debug, must always be >1, otherwise database time will not be set

  log2("NOTICE", "Started: newUpdate with perPage $perPage, getLimit $getLimit", "log/inat-obs-log.log");

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
// FULLUPDATE
// This will run through all observations, and A) updates only changed obs and 2) Marks which observations have been deleted.
// This mode is the only one to get updates of old observations which have switched to open licenses

elseif ("fullUpdate" == $_GET['mode']) {
  $allHandled = FALSE; // todo: use this in all modes?

  $perPage = 100; // Production
  $perPage = 10; // Debug

  $getLimit = 10000000; // Production: no getLimit
  $getLimit = 10; // Debug

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

    // PUSH
    $dwJson = compileDwJson($dwObservations);
    pushFactory($dwJson, $_GET['destination']);

    // Log after push if successful
    logObservationsToDatabase($databaseObservations, 1, $database); // todo: 0 = first upload, 1 = update

    // Prepare for next round
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

$database->close();


// ------------------------------------------------------------------------------------------------
// FUNCTIONS

function deleteFactory($documentId, $destination) {
  if ("dryrun" == $destination) {
    pushToEcho($documentId);
  }
  elseif ("test" == $destination) {
    deleteFromApiTest($documentId);
  }
  // Todo here: Push to production
  else {
    log2("ERROR", "Unknown destination value", "log/inat-obs-log.log");
  }
  return NULL;
}

function pushFactory($data, $destination) {
  // todo: is there more efficient way to do this? Move json encoding here, to avoid first encoding and then decoding?
  $arr = json_decode($data, TRUE);
  if (empty($arr['roots'])) {
    log2("NOTICE", "No observations to push ", "log/inat-obs-log.log");
    return FALSE;
  }

  log2("D", "pushFactory called: destination $destination", "log/inat-obs-log.log");

  if ("dryrun" == $destination) {
    pushToEcho($data);
  }
  elseif ("test" == $destination) {
    postToAPItest($data);
  }
  // Todo here: Push to production
  else {
    log2("ERROR", "Unknown destination value", "log/inat-obs-log.log");
  }
  return NULL;
}

function pushToEcho($data) {
  // $data might be json or plain string. We want to display json as an array using print_r()
  log2("NOTICE", "Dryrun", "log/inat-obs-log.log");
  echo "DRYRUN...\n\n";

  $decoded = json_decode($data, TRUE);
  if (is_array($decoded)) {
    print_r ($decoded);
  }
  else {
    echo $data;
  }
  return NULL;
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

  $dwRoot['schema'] = "laji-etl";
  $dwRoot['roots'] = $dwObservations;
  $dwJson = json_encode($dwRoot);

  return $dwJson;
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