<?php
require_once "inatHelpers.php";

// todo: log errors locally, so that I know if some field is missing or something unexpected

echo "<pre>";

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

}



// ------------------------------------------------------------------------------------------------

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
  /*
  Todo:
  - sounds
  - quality metrics
  - conflicting id's
  - Is there field in DW for updated date?
  - project_observations
  */

  // Data shared by all observations
  $dw['collectionId'] = "http://tun.fi/HR.3211";
  $dw['publicDocument']['collectionId'] = "http://tun.fi/HR.3211"; // todo: Esko: why collectionId twice?
  $dw['sourceId'] = "http://tun.fi/HR.3211";
  $dw['deleteRequest'] = FALSE;
  $dw['schema'] = "laji-etl";
  $dw['publicDocument']['secureLevel'] = "NONE";
  $dw['publicDocument']['concealment'] = "PUBLIC";
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
  // todo: do we need to store whether the project is trad/non-trad? Do they share identifier namespace?
  // Non-traditional (automatic)
  foreach($inat['non_traditional_projects'] as $projectNro => $project) {
    //    print_r($project); // debug
    array_push($keywordsArr, "project-" . $project['project_id']);
    $factsArr = factsArrayPush($factsArr, "projectTitle", $project['project']['title']);
    $factsArr = factsArrayPush($factsArr, "projectId", $project['project_id']);
  }

  // Traditional (manual)
  foreach($inat['project_observations'] as $projectNro => $project) {
    //    print_r($tradProject); // debug
    array_push($keywordsArr, "project-" . $project['project_id']);
    $factsArr = factsArrayPush($factsArr, "projectTitle", $project['project']['title']);   
    $factsArr = factsArrayPush($factsArr, "projectId", $project['project_id']);
  }


  // Dates
  $dw['createdDate'] = $inat['created_at_details']['date'];
  $dw['eventDate']['begin'] = $inat['observed_on_details']['date'];

  $factsArr = factsArrayPush($factsArr, "observedOrCreatedAt", $inat['time_observed_at']);


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

  // Remove FI or Finland from the beginning
  if (0 === strpos($locality, "FI,")) {
    $locality = substr($locality, 3);
  }
  elseif (0 === strpos($locality, "Finland,")) {
    $locality = substr($locality, 8);
  }
  $locality = trim($locality, ", ");
 
  $dw['publicDocument']['gatherings'][0]['locality'] = $locality;
  $dw['publicDocument']['gatherings'][0]['country'] = "Finland"; // NOTE: This expects that only Finnish observations are fecthed


  // Photos
  $photoCount = count($inat['observation_photos']); // todo: does this fail
  if ($photoCount >= 1) {
    array_push($keywordsArr, "has_photos");
    foreach ($inat['observation_photos'] as $photoNro => $photo) {
      $factsArr = factsArrayPush($factsArr, "photoId", $photo['photo']['id']);
    }
  }
  else {
    array_push($keywordsArr, "no_photos");
  }
  $factsArr = factsArrayPush($factsArr, "photoCount", $photoCount);


  // Sounds
  $soundCount = count($inat['sounds']); // todo: does this fail
  if ($soundCount >= 1) {
    array_push($keywordsArr, "has_sounds");
    foreach ($inat['sounds'] as $soundNro => $sound) {
      $factsArr = factsArrayPush($factsArr, "soundId", $sound['id']);
      array_push($descArr, "sound file: " . $sound['file_url']); // todo: esko: where this to? outgoing links, urls, description field? Do the same for photos, if they have static urls?
    }
  }
  else {
    array_push($keywordsArr, "no_sounds");
  }
  $factsArr = factsArrayPush($factsArr, "soundCount", $soundCount);


  // Tags
  if (!empty($inat['tags'])) {
    foreach ($inat['tags'] as $tagNro => $tag) {
      array_push($keywordsArr, $tag);
    }
  }


  // Annotations
  /*
  Annotations describe two features: Life stage and Sex.
  The logic seems to be a bit difficult, if I uncderstand it correctly:
    - Person B creates an observation
    - Person A creates an annotation, e.g. saying Life stage is Adult
    - Person B can vote for or against the annotation
    - Person C, D etc. can vote for or against the annotation
    - Person cannot create a new annotation about the same thing. He can (probably) remove person A's annotation and then create a new annotation. What then happens to the votes of B, C etc. ?

    Because this is complicated, let's just save the original annotation as a fact for now. 
    Todo: Find out the logic and decide what to do. Prepare also to the possibility that allowed values may change over time. 
  */
  if (!empty($inat['annotations'])) {
    foreach ($inat['annotations'] as $annotationNro => $annotation) {
      $factsArr = factsArrayPush($factsArr, $annotation['controlled_attribute']['label'], $annotation['controlled_value']['label']);
    }
  }

  // Taxon
  $dw['publicDocument']['gatherings'][0]['units'][0]['taxonVerbatim'] = $inat['species_guess']; // This can(?) be in any language?
  $dw['publicDocument']['gatherings'][0]['units'][0]['taxonVerbatim'] = $inat['taxon']['name']; // todo: esko: this is the real taxon, where to put this?


  // Observations fields
  foreach($inat['ofvs'] as $ofvsNro => $ofvs) {
    $factsArr = factsArrayPush($factsArr, $ofvs['name_ci'], $ofvs['value_ci']);
  }

  // Quality grade
  $factsArr = factsArrayPush($factsArr, "quality_grade", $inat['quality_grade']);
  array_push($keywordsArr, $inat['quality_grade']);

  // Misc facts
  // todo: are all of these needed / valuable?
  $factsArr = factsArrayPush($factsArr, "out_of_range", $inat['out_of_range']);
  $factsArr = factsArrayPush($factsArr, "taxon_geoprivacy", $inat['taxon_geoprivacy']);
  $factsArr = factsArrayPush($factsArr, "context_geoprivacy", $inat['context_geoprivacy']);
  $factsArr = factsArrayPush($factsArr, "context_user_geoprivacy", $inat['context_user_geoprivacy']);
  $factsArr = factsArrayPush($factsArr, "context_taxon_geoprivacy", $inat['context_taxon_geoprivacy']);
  $factsArr = factsArrayPush($factsArr, "comments_count", $inat['comments_count']);
  $factsArr = factsArrayPush($factsArr, "num_identification_agreements", $inat['num_identification_agreements']);
  $factsArr = factsArrayPush($factsArr, "num_identification_disagreements", $inat['num_identification_disagreements']);
//  $factsArr = factsArrayPush($factsArr, "identifications_most_agree", $inat['identifications_most_agree']);
//  $factsArr = factsArrayPush($factsArr, "identifications_most_disagree", $inat['identifications_most_disagree']);
  $factsArr = factsArrayPush($factsArr, "observerActivityCount", $inat['user']['activity_count']);
  $factsArr = factsArrayPush($factsArr, "owners_identification_from_vision", $inat['owners_identification_from_vision']);
//  $factsArr = factsArrayPush($factsArr, "", $inat(['']);


  // ----------------------------------------------------------------------------------------

  // FIELDS TODO TO DW or MISSING FROM EXAMPLE

  // Observer
  // Prefer full name over loginname
  if ($inat['user']['name']) {
    $observer = $inat['user']['name'];
  }
  else {
    $observer = $inat['user']['login'];
  }
  $dw['observer'] = $observer;
  $dw['observerId'] = "inaturalist:" . $inat['user']['id'];
  if (!empty($inat['user']['orcid'])) {
    $dw['observerOrcid'] = $inat['user']['orcid'];
  }


  $dw['license'] = $inat['license_code'];
  $dw['url'] = $inat['uri'];


  // ----------------------------------------------------------------------------------------

  // Handle temporary arrays 
  $dw['publicDocument']['keywords'] = $keywordsArr; // todo: or to unit level?
  $dw['publicDocument']['gatherings'][0]['notes'] = implode(" / ", $descArr);
  $dw['publicDocument']['gatherings'][0]['units'][0]['facts'] = $factsArr;

  return $dw;
}
