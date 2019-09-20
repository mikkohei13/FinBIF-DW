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
  $url = "https://api.inaturalist.org/v1/observations?id=" . $_GET['debug'] . "&order=desc&order_by=created_at&include_new_projects=true"; // multi
  echo $url . "\n";

  log2("DEBUG", "fetched url $url", "log/inat-obs-log.log");

  $observationsJson = file_get_contents($url);
  log2("DEBUG", "fetched id ".$_GET['debug'], "log/inat-obs-log.log");

  $data = json_decode($observationsJson, TRUE);
  $dwObservations[] = observationInat2Dw($data['results'][0]);
}
// PRODUCTION
else {
  $perPage = 5;
  $pagesLimit = 1;
  $sleepSecondsBetweenPages = 5; // iNat limit: ... keep it to 60 requests per minute or lower, and to keep under 10,000 requests per day
  
  $page = 1; // Start value

  $pagesLimit = $pagesLimit + $page;
  
  // Per page
  while ($page <= $pagesLimit) {
    $observationsJson = getObservationsJson($page, $perPage);
    $data = json_decode($observationsJson, TRUE);
  
    /*
    $meta['totalResults'] = $data['total_results']; 
    $meta['page'] = $data['page'];
    $meta['perPage'] = $data['per_page'];
    $meta['pagesTotal'] = ceil($meta['totalResults'] / $meta['perPage']);
    $meta['pagesToGo'] = $meta['pagesTotal'] - $meta['page'];
    
    //print_r ($meta);
    */
  
    // Per observation
    // Convert from iNat to DW format
    foreach ($data['results']  as $nro => $obs) {
      $dwObservations[] = observationInat2Dw($obs);
    }
  
    $page++;
    sleep($sleepSecondsBetweenPages);
  }  
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

print_r ($dwRoot); // debug
print_r (json_encode($dwRoot)); // debug




log2("NOTICE", "finished", "log/inat-obs-log.log");

//--------------------------------------------------------------------------


function getObservationsJson($page, $perPage) {

  $url = "http://api.inaturalist.org/v1/observations?captive=false&license=cc-by%2Ccc-by-nc%2Ccc-by-nd%2Ccc-by-sa%2Ccc-by-nc-nd%2Ccc-by-nc-sa%2Ccc0&place_id=7020&page=" . $page . "&per_page=" . $perPage . "&order=desc&order_by=created_at";

  $observationsJson = file_get_contents($url);
  log2("NOTICE", "fetched page $page", "log/inat-obs-log.log");

  //echo $json;

  // todo: handle end of data

  return $observationsJson;
}

