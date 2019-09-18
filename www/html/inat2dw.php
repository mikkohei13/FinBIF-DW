<?php

// Converts one iNat observation to DW format


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
  - It seems that list quesy has different formats for annotations & projects (at least): they don't have the labels
    Examples:
    - annotations: 32818382
    - project_observations: 20561213
    - non_traditional_projects: ??
    Options:
      a) fetch projects, annotations from api
      b) hard-code annotations
      c) leave labels out for now, just use keywords for project id's and facts for other info
  - Check that empty fields are ok when data is json
  - Quality metrics & quality grade (casual, research) affecting quality fields in DW
  - Filter mikkohei13 observations (will be duplicates, but have images...)

  Ask Esko:
  - Mitä jos vain taxon verbatim saatavilla? Käytetäänkö taksonina, jätetäänkö taksoni tyhjäksi, vai laitetaanko taksoniksi "biota"?
  - Millainen document id pitää luoda? Mihin laitetaan alkuperäislähteen id? Tallennetaanko GBIF:n käyttämä id jonnekin, siltä varalta että dataa yhdistetään jossain vaiheessa? GBIF käyttää iNat urlia tunnisteena, nyt tämä faktassa.
  - Is there field in DW for
    - date updated?
    - outgoing links (obs in inat, obs in gbif, photos in inat, sounds in inat)
  - What if a field is left empty? Is that ok, or should I avoid empty fields? (Note: the iNat JSON might have some elements missing, e.g. taxon is missing of there is not any identificationsor just a placeholder.)
  - All the todo's here mentioning Esko
    
  Ask iNat:
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

//  echo "HERE GOES obs ".$inat['id'].":\n"; print_r ($inat); // debug ABBA

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
  if (!empty($inat['description'])) {
    $desc = strip_tags($inat['description']); // This also removes whitespace created by tags, e.g. <br> or </p><p>
    $desc = mb_substr($desc, 0, 1000); // Limit to max 1000 chars
    array_push($descArr, $desc);
  } 

  // Quality metrics
  //todo: esko: where to put these?
  if ($inat['quality_metrics']) {
    $qualityMetrics = summarizeQualityMetrics($inat['quality_metrics']);

    //    print_r ($qualityMetrics); // DEBUG
    foreach ($qualityMetrics as $key => $value) {
      $factsArr = factsArrayPush($factsArr, "quality_metrics_" . $key, $value, TRUE);
    }
  }

  // Projects
  // todo: do we need to store whether the project is trad/non-trad? Do they share identifier namespace?
  // Non-traditional (automatic)
  if (isset($inat['non_traditional_projects'])) {
    foreach($inat['non_traditional_projects'] as $projectNro => $project) {
      //    print_r($project); // debug
      array_push($keywordsArr, "project-" . $project['project_id']);
      $factsArr = factsArrayPush($factsArr, "projectTitle", $project['project']['title']);
      $factsArr = factsArrayPush($factsArr, "projectId", $project['project_id']);
    }
  }

  // Traditional (manual)
  if (isset($inat['project_observations'])) {
    foreach($inat['project_observations'] as $projectNro => $project) {
//         echo "\nPROJECT NRO ".$projectNro.":\n"; print_r($project); // debug
      array_push($keywordsArr, "project-" . $project['project_id']);
      $factsArr = factsArrayPush($factsArr, "projectTitle", $project['project']['title']);   
      $factsArr = factsArrayPush($factsArr, "projectId", $project['project_id']);
    }
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
  if (!empty($inat['annotations'])) {
    foreach ($inat['annotations'] as $annotationNro => $annotation) {
      $anno = handleAnnotation($annotation);
      print_r ($anno); // debug
      $factsArr = factsArrayPush($factsArr, $anno['attribute'], $anno['value']);
      
      // todo: esko: is sex ok here? values?
      if (isset($anno['dwLifeStage'])) {
        $dw['publicDocument']['gatherings'][0]['units'][0]['lifeStage'] = $anno['dwLifeStage'];
      }
      if (isset($anno['dwSex'])) {
        $dw['publicDocument']['gatherings'][0]['units'][0]['sex'] = $anno['dwSex'];
      }
    }
  }

  // Taxon
  $dw['publicDocument']['gatherings'][0]['units'][0]['taxonVerbatim'] = handleTaxon($inat['species_guess']); // todo: keep this? This can(?) be in any language, unclear what is based on - seems often be dependent on the language of the latest identifier.
  $dw['publicDocument']['gatherings'][0]['units'][0]['taxon'] = handleTaxon($inat['taxon']['name']); // todo: esko: this is the real taxon, where to put this?


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

  // TODO: FIELDS TODO TO DW or MISSING FROM EXAMPLE

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
  echo "handled ".$inat['id']."\n"; // debug
  return $dw;
}