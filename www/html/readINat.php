<?php
require_once "inatHelpers.php";

// todo: log errors locally, so that I know if some field is missing or something unexpected

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
  $dw['publicDocument']['gatherings'][0]['units'][0]['superRecordBasis'] = "HUMAN_OBSERVATION_UNSPECIFIED";
  $dw['publicDocument']['gatherings'][0]['units'][0]['recordBasis'] = "HUMAN_OBSERVATION_UNSPECIFIED";
  $dw['publicDocument']['gatherings'][0]['units'][0]['typeSpecimen'] = false; // todo: esko: is this needed?

  $keywordsArr = Array();
  $descArr = Array();
  $factsArr = Array();


  // Observation
  $documentId = "http://tun.fi/HR.3211/" . $inat['id']; // todo: Esko: based on KE-identifier? 
  $dw['documentId'] = $documentId;
  $dw['publicDocument']['documentId'] = $documentId; // todo: Esko: why documentId twice?


  // Description
  if (!empty($inat['description'])) { // Does this handle NULLs?
    array_push($descArr, $inat['description']);
  } 


  // Keywords-id
  array_push($keywordsArr, $inat['id']); // todo: Esko: is this the correct place?


  // Projects
  foreach($inat['non_traditional_projects'] as $projectNro => $project) {
//    print_r($project); // debug
    array_push($keywordsArr, "inaturalist-project-" . $project['project_id']);
    $factsArr = factsArrayPush($factsArr, "projectTitle", $project['project']['title']);

//    array_push($descArr, "project: " . $project['project']['title']); // todo: is this needed?
  }


  // Dates
  $dw['createdDate'] = $inat['created_at_details']['date'];
  $dw['eventDate']['begin'] = $inat['observed_on_details']['date'];

  $factsArr = factsArrayPush($factsArr, "observationCreatedAt", $inat['time_observed_at']);


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


  // Locality
  $locality = stringReverse($inat['place_guess']);

  // Remove FI from the beginning
  // todo: test that this really works
  if (0 === strpos($locality, "FI,")) {
    $locality = substr($locality, 3);
  }
  $locality = trim($locality, ", ");
 
  $dw['publicDocument']['gatherings'][0]['locality'] = $locality;
  $dw['publicDocument']['gatherings'][0]['country'] = "Finland"; // NOTE: This expects that only Finnish observations are fecthed


  // Photos and other media
  $dw['publicDocument']['gatherings'][0]['units'][0]['mediaCount'] = count($inat['observation_photos']); // todo: Esko: should media count be set, if media is on external server


  // Taxon
  $dw['publicDocument']['gatherings'][0]['units'][0]['taxonVerbatim'] = $inat['species_guess']; // This can(?) be in any language?
  $dw['publicDocument']['gatherings'][0]['units'][0]['taxonVerbatim'] = $inat['taxon']['name']; // todo: esko: this is the real taxon, where to put this?


  // Observations fields
  foreach($inat['ofvs'] as $ofvsNro => $ofvs) {
    $ofvsName = sanitizeOfvsName($ofvs['name_ci']);
    $factsArr = factsArrayPush($factsArr, $ofvsName, $ofvs['value_ci']);
  }


  // Misc facts
  // todo: are all of these needed / valuable?
  $factsArr = factsArrayPush($factsArr, "out_of_range", $inat['out_of_range']);
  $factsArr = factsArrayPush($factsArr, "quality_grade", $inat['quality_grade']);
  $factsArr = factsArrayPush($factsArr, "quality_grade", $inat['quality_grade']);
  $factsArr = factsArrayPush($factsArr, "taxon_geoprivacy", $inat['taxon_geoprivacy']);
  $factsArr = factsArrayPush($factsArr, "context_geoprivacy", $inat['context_geoprivacy']);
  $factsArr = factsArrayPush($factsArr, "context_user_geoprivacy", $inat['context_user_geoprivacy']);
  $factsArr = factsArrayPush($factsArr, "context_taxon_geoprivacy", $inat['context_taxon_geoprivacy']);
  $factsArr = factsArrayPush($factsArr, "comments_count", $inat['comments_count']);
  $factsArr = factsArrayPush($factsArr, "num_identification_agreements", $inat['num_identification_agreements']);
//  $factsArr = factsArrayPush($factsArr, "identifications_most_agree", $inat['identifications_most_agree']);
//  $factsArr = factsArrayPush($factsArr, "identifications_most_disagree", $inat['identifications_most_disagree']);
  $factsArr = factsArrayPush($factsArr, "observerActivityCount", $inat['user']['activity_count']);



  // FIELDS TODO TO DW or MISSING FROM EXAMPLE
  $dw['license'] = $inat['license_code'];
  $dw['url'] = $inat['uri'];

  // Prefer full name over loginname
  if ($inat['user']['name']) {
    $observer = $inat['user']['name'];
  }
  else {
    $observer = $inat['user']['login'];
  }
  $dw['observer'] = $observer;
  $dw['observerId'] = $inat['user']['id'];
  $dw['observerOrcid'] = $inat['user']['orcid'];



  // Handle temporary arrays 
  $dw['publicDocument']['keywords'] = $keywordsArr; // todo: or to unit level?
  $dw['publicDocument']['gatherings'][0]['notes'] = implode(" / ", $descArr);
  $dw['publicDocument']['gatherings'][0]['units'][0]['facts'] = $factsArr;

  return $dw;
}
