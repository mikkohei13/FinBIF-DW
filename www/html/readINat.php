<?php
//require_once "readINatAssociatedData.php";

echo "<pre>";

// Finnish, CC, wild
// 10 per page, page 1

$url = "http://api.inaturalist.org/v1/observations?captive=false&license=cc-by%2Ccc-by-nc%2Ccc-by-nd%2Ccc-by-sa%2Ccc-by-nc-nd%2Ccc-by-nc-sa%2Ccc0&place_id=7020&page=1&per_page=10&order=desc&order_by=created_at";

// Koivunpunikkitatti, testihavainto joss projekti ja tageja
// Onko datamalli sama kuin observations-haussa?

$url = "http://api.inaturalist.org/v1/observations/32469823?include_new_projects=true";


$json = file_get_contents($url);
//echo $json;
$data = json_decode($json, TRUE);

$meta['totalResults'] = $data['total_results']; 
$meta['page'] = $data['page'];
$meta['perPage'] = $data['per_page'];
$meta['pagesTotal'] = ceil($meta['totalResults'] / $meta['perPage']);
$meta['pagesToGo'] = $meta['pagesTotal'] - $meta['page'];

print_r ($meta);

// Foreach observation
foreach ($data['results']  as $nro => $obs) {
  $dwObservations[] = observationInat2Dw($obs);
}

$dwRoot = Array();
$dwRoot['schema'] = "laji-etl";
$dwRoot['roots'] = $dwObservations;

print_r ($dwRoot);

//--------------------------------------------------------------------------

function observationInat2Dw($inat) {

  $dw = Array();

  // basic structure. todo: is this needed?
  /*
  $dw['publicDocument'] = Array();
  $dw['publicDocument']['gatherings'][0] = Array();
    */

  // Data shared by all observations
  $dw['collectionId'] = "http://tun.fi/HR.3211";
  $dw['publicDocument']['collectionId'] = "http://tun.fi/HR.3211"; // todo: Esko: why collectionId twice?
  $dw['sourceId'] = "http://tun.fi/HR.3211";
  $dw['deleteRequest'] = FALSE;
  $dw['schema'] = "laji-etl";
  $dw['publicDocument']['secureLevel'] = "NONE";
  $dw['publicDocument']['concealment'] = "PUBLIC";

  $keywordsArr = Array();
  $descArr = Array();

  // Observation
  $documentId = "http://tun.fi/HR.3211/" . $inat['id']; // todo: Esko: based on KE-identifier? 
  $dw['documentId'] = $documentId;
  $dw['publicDocument']['documentId'] = $documentId; // todo: Esko: why documentId twice?


  // Projects
  foreach($inat['non_traditional_projects'] as $projectNro => $project) {
//    print_r($project); // debug
    array_push($keywordsArr, "inaturalist-project-" . $project['project_id']);
    array_push($descArr, $project['project']['title']);
  }

  // Dates
  $dw['createdDate'] = $inat['created_at_details']['date'];

  // Coordinates
  $dw['publicDocument']['gatherings'][0]['coordinates']['type'] = "wgs84";

  if (empty($inat['positional_accuracy'])) {
    $accuracy = 1000; // Default for missing values
  }
  elseif ($inat['positional_accuracy'] < 10) {
    $accuracy = 10; // Minimum value
  }
  else {
    $accuracy = round($inat['positional_accuracy'], 0);
  }
  $dw['publicDocument']['gatherings'][0]['coordinates']['accuracyInMeters'] = $accuracy;
  $dw['publicDocument']['gatherings'][0]['coordinates']['lon'] = $inat['geojson']['coordinates'][0]; // todo: Esko: is this correct for point coords?
  $dw['publicDocument']['gatherings'][0]['coordinates']['lat'] = $inat['geojson']['coordinates'][1];


  // Handle temporary arrays 
  $dw['publicDocument']['keywords'] = $keywordsArr;
  $dw['publicDocument']['description'] = implode(" / ", $descArr); // todo: name and level of this field?

  return $dw;
}
