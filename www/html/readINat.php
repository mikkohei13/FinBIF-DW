<?php
require_once "inatHelpers.php";
require_once "log2.php";

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

  // Spam
  $url = "http://api.inaturalist.org/v1/observations/32589022?include_new_projects=true";

  // Maveric Anser
  $url = "http://api.inaturalist.org/v1/observations/17937851?include_new_projects=true";

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

//print_r ($meta);

// Foreach observation
foreach ($data['results']  as $nro => $obs) {
  $dwObservations[] = observationInat2Dw($obs);
}

$dwRoot = Array();
$dwRoot['schema'] = "laji-etl";
$dwRoot['roots'] = $dwObservations;

print_r ($dwRoot);
print_r (json_encode($dwRoot));

//--------------------------------------------------------------------------

function observationInat2Dw($inat) {

  $dw = Array();

  /*
  
  This expects that certain obserations are filtered out in the API call:
  - Non-Finnish. If this is exapanded to other countries, remove hard-coded country name. Also note that country name may be in any language or abbreviation (Finland, FI, Suomi...).
  - Observations without license
  - Captive/cultivated 
  - without_taxon_id: [human id] (todo: remove human filter here)

  Decide:
  - keep observerActivityCount? It will constantly change.
  
  Todo:
  - Check that empty fields are ok when data is json
  - Whatr does id please flag look like?
  - Filter mikkohei13 observations (will be duplicates, but have images...)
  - quality metrics
  - quality_grade
  - check conflicting id's
  - Check that you are using taxon and taxon_guess fields correctlyy using placeholder/unknown/life observations
  - https://www.inaturalist.org/pages/tips_tricks_nz

  Ask Esko:
  - Millainen document id pitää luoda? Mihin laitetaan alkuperäislähteen id? Tallennetaanko GBIF:n käyttämä id jonnekin, siltä varalta että dataa yhdistetään jossain vaiheessa? GBIF käyttää iNat urlia tunnisteena, nyt tämä faktassa.
  - Is there field in DW for
    - date updated?
    - outgoing links (obs in inat, obs in gbif, photos in inat, sounds in inat)
  - What if a field is left empty? Is that ok, or should I avoid empty fields? (Note: the iNat JSON might have some elements missing, e.g. taxon is missing of there is not any identificationsor just a placeholder.)
    

  How to have FinBIF here (not important, mostly curious...):
  This observation is featured on 1 site

  */

  // Flags/spam etc. filtering
  if (!empty($inat['flags'])) {
    log2("NOTICE", "skipped observation having flag(s)\t" . $inat['id'], "log/inat-obs-log.log");
    return FALSE;
  }
  if ("Homo sapiens" == $inat['taxon']['name']) {
    log2("NOTICE", "skipped observation of human(s)\t" . $inat['id'], "log/inat-obs-log.log");
    return FALSE;
  }

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


  // Id's
  $documentId = "http://tun.fi/HR.3211/" . $inat['id']; // todo: Esko: based on KE-identifier? 
  $dw['documentId'] = $documentId;
  $dw['publicDocument']['documentId'] = $documentId; // todo: Esko: why documentId twice?

  $factsArr = factsArrayPush($factsArr, "inaturalistId", $inat['id'], TRUE);
  $factsArr = factsArrayPush($factsArr, "inaturalistUri", "https://www.inaturalist.org/observations/" . $inat['id'], TRUE);
  array_push($keywordsArr, $inat['id']); // todo: Esko: is this the correct place?


  // Description
  if (!empty($inat['description'])) { // Does this handle NULLs?
    array_push($descArr, $inat['description']);
  } 

  // Quality metrics
  //todo: esko: where to put these?
  if ($inat['quality_metrics']) {
    $qualityMetrics = summarizeQualityMetrics($inat['quality_metrics']);
    print_r ($qualityMetrics);
    foreach ($qualityMetrics as $key => $value) {
      $factsArr = factsArrayPush($factsArr, "quality_metrics_" . $key, $value, TRUE);
    }
  }

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
  $dw['eventDate']['begin'] = removeNullFalse($inat['observed_on_details']['date']);

  $factsArr = factsArrayPush($factsArr, "observedOrCreatedAt", $inat['time_observed_at']);


  // Coordinate accuracy
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

  // Coordinates
  $dw['publicDocument']['gatherings'][0]['coordinates']['lon'] = removeNullFalse($inat['geojson']['coordinates'][0]); // todo: Esko: is this correct for point coords?
  $dw['publicDocument']['gatherings'][0]['coordinates']['lat'] = removeNullFalse($inat['geojson']['coordinates'][1]);


  // Locality
  $locality = stringReverse($inat['place_guess']);

  // Remove FI, Finland & Suomi from the beginning
  if (0 === strpos($locality, "FI,")) {
    $locality = substr($locality, 3);
  }
  elseif (0 === strpos($locality, "Finland,")) {
    $locality = substr($locality, 8);
  }
  elseif (0 === strpos($locality, "Suomi,")) {
    $locality = substr($locality, 6);
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

    Annotations:
    1=Life Stage, 9=Sex, 12=Plant Phenology
    see more at https://forum.inaturalist.org/t/how-to-use-inaturalists-search-urls-wiki/63

    Because this is complicated, let's just save the original annotation as a fact for now. 
    Todo: Find out the logic and decide what to do. Prepare also to the possibility that allowed values may change over time. 
  */
  if (!empty($inat['annotations'])) {
    foreach ($inat['annotations'] as $annotationNro => $annotation) {
      $factsArr = factsArrayPush($factsArr, $annotation['controlled_attribute']['label'], $annotation['controlled_value']['label']);
    }
  }

  // Taxon
  $dw['publicDocument']['gatherings'][0]['units'][0]['taxonVerbatim'] = removeNullFalse($inat['species_guess']); // todo: keep this? This can(?) be in any language, unclear what is based on - seems often be dependent on the language of the latest identifier.
  $dw['publicDocument']['gatherings'][0]['units'][0]['taxon'] = removeNullFalse($inat['taxon']['name']); // todo: esko: this is the real taxon, where to put this?


  // Observations fields
  foreach($inat['ofvs'] as $ofvsNro => $ofvs) {
    $factsArr = factsArrayPush($factsArr, $ofvs['name_ci'], $ofvs['value_ci'], TRUE); // This must preserve zero values
  }

  // Quality grade
  $factsArr = factsArrayPush($factsArr, "quality_grade", $inat['quality_grade']);
  array_push($keywordsArr, $inat['quality_grade']);

  // Misc facts
  // todo: are all of these needed / valuable?
  $factsArr = factsArrayPush($factsArr, "out_of_range", $inat['out_of_range'], FALSE);
  $factsArr = factsArrayPush($factsArr, "taxon_geoprivacy", $inat['taxon_geoprivacy'], FALSE);
  $factsArr = factsArrayPush($factsArr, "context_geoprivacy", $inat['context_geoprivacy'], FALSE);
  $factsArr = factsArrayPush($factsArr, "context_user_geoprivacy", $inat['context_user_geoprivacy'], FALSE);
  $factsArr = factsArrayPush($factsArr, "context_taxon_geoprivacy", $inat['context_taxon_geoprivacy'], FALSE);
  $factsArr = factsArrayPush($factsArr, "comments_count", $inat['comments_count'], FALSE);
  $factsArr = factsArrayPush($factsArr, "num_identification_agreements", $inat['num_identification_agreements'], FALSE);
  $factsArr = factsArrayPush($factsArr, "num_identification_disagreements", $inat['num_identification_disagreements'], FALSE);
//  $factsArr = factsArrayPush($factsArr, "identifications_most_agree", $inat['identifications_most_agree']);
//  $factsArr = factsArrayPush($factsArr, "identifications_most_disagree", $inat['identifications_most_disagree']);
  $factsArr = factsArrayPush($factsArr, "observerActivityCount", $inat['user']['activity_count']);
  $factsArr = factsArrayPush($factsArr, "owners_identification_from_vision", $inat['owners_identification_from_vision'], FALSE);
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


  log2("SUCCESS", "handled observation\t" . $inat['id'], "log/inat-obs-log.log");
  return $dw;
}
