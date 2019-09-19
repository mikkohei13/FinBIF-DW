<?php

require_once "inatHelpers.php";
require_once "log2.php";
require_once "inat2dw.php";
require_once "postToAPI.php";

// todo: log errors locally, so that I know if some field is missing or something unexpected
// todo try catch for conversion?

echo "<pre>";

// ------------------------------------------------------------------------------------------------

$dwObservations = Array();


// DEBUG
if (isset($_GET['debug'])) {
  $data = getObsArr_singleId($_GET['debug']);
  $dwObservations[] = observationInat2Dw($data['results'][0]);
}
// PRODUCTION
else {

  // See max 10k observations bug: https://github.com/inaturalist/iNaturalistAPI/issues/134

  $perPage = 10;
  $getLimit = 2;
  $idAbove = 0; // Start value
  $idAbove = 33084315; // Test value for observation submitted on 20.9.2019

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
      echo $obs['id'] . "\n";
      $idAbove = $obs['id'];
//      $dwObservations[] = observationInat2Dw($obs);
    }

    $i++;
  }

  /*
  $perPage = 10;
  $pagesLimit = 2;
  $sleepSecondsBetweenPages = 2; // iNat limit: ... keep it to 60 requests per minute or lower, and to keep under 10,000 requests per day
  
  $page = 1;

  $pagesLimit = $pagesLimit + $page;
  
  // Per page
  while ($page <= $pagesLimit) {
    $observationsJson = getObservationsJson($page, $perPage);
    $data = json_decode($observationsJson, TRUE);
  
//    $meta['totalResults'] = $data['total_results']; 
//    $meta['page'] = $data['page'];
//    $meta['perPage'] = $data['per_page'];
//    $meta['pagesTotal'] = ceil($meta['totalResults'] / $meta['perPage']);
//    $meta['pagesToGo'] = $meta['pagesTotal'] - $meta['page'];
    
    //print_r ($meta);
  
    // Per observation
    // Convert from iNat to DW format
    foreach ($data['results']  as $nro => $obs) {
      $dwObservations[] = observationInat2Dw($obs);
    }
  
    $page++;
    sleep($sleepSecondsBetweenPages);
  }  
  */
}

// Compile json file to be sent
$dwRoot = Array();
$dwRoot['schema'] = "laji-etl";
$dwRoot['roots'] = $dwObservations;

// Send to API
/*
$apiResponse = postToAPI($dwRoot);
log2("NOTICE", "API responded " . $apiResponse['code'], "log/inat-obs-log.log");
*/

//print_r ($dwRoot); // debug
//print_r (json_encode($dwRoot)); // debug




log2("NOTICE", "finished", "log/inat-obs-log.log");

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
