<?php
require_once "inatHelpers.php";
require_once "log2.php";
require_once "inat2dw.php";

// todo: log errors locally, so that I know if some field is missing or something unexpected

echo "<pre>";

// ------------------------------------------------------------------------------------------------
// DEBUG GET URL's

if (isset($_GET['obs_id'])) {
  $url = "http://api.inaturalist.org/v1/observations/" . $_GET['obs_id'] . "?include_new_projects=true";
}
else {
  // Finnish, CC, wild
  // 10 per page, page 1
  $url = "http://api.inaturalist.org/v1/observations?captive=false&license=cc-by%2Ccc-by-nc%2Ccc-by-nd%2Ccc-by-sa%2Ccc-by-nc-nd%2Ccc-by-nc-sa%2Ccc0&place_id=7020&page=1&per_page=10&order=desc&order_by=created_at";

  // Koivunpunikkitatti, testihavainto joss projekti ja tageja
  // Onko datamalli sama kuin observations-haussa?
  $url = "http://api.inaturalist.org/v1/observations/32469823?include_new_projects=true";

  // Silokka, 2 kuvaa
  $url = "http://api.inaturalist.org/v1/observations/32325167?include_new_projects=true";

  // Danaus chrysippus, 5 id's, charset issue in tags field
  // project_observations, annotations
  $url = "http://api.inaturalist.org/v1/observations/20830621?include_new_projects=true";

  // Spam
  $url = "http://api.inaturalist.org/v1/observations/32589022?include_new_projects=true";

  // Maveric Anser
  $url = "http://api.inaturalist.org/v1/observations/17937851?include_new_projects=true";
}



// ------------------------------------------------------------------------------------------------

$dwObservations = Array();


// DEBUG
if (isset($_GET['debug'])) {
//  $url = "http://api.inaturalist.org/v1/observations/" . $_GET['debug'] . "?include_new_projects=true"; // single
  $url = "https://api.inaturalist.org/v1/observations?id=" . $_GET['debug'] . "&order=desc&order_by=created_at&include_new_projects=true"; // multi

  $observationsJson = file_get_contents($url);
  log2("DEBUG", "fetched id ".$_GET['debug'], "log/inat-obs-log.log");

  $data = json_decode($observationsJson, TRUE);
  $dwObservations[] = observationInat2Dw($data['results'][0]);
}
// PRODUCTION
else {
  $perPage = 60;
  $pagesLimit = 1;
  $sleepSecondsBetweenPages = 1; // iNat limit: ... keep it to 60 requests per minute or lower, and to keep under 10,000 requests per day
  
  $page = 1; // Start value
  
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

print_r ($dwRoot);
print_r (json_encode($dwRoot));

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

