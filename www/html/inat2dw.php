<?php

// Converts one iNat observation to DW format


function observationInat2Dw($inat) {

  $dw = Array();

  /*
  Example obs:
  - without date and taxon: https://www.inaturalist.org/observations/30092946
  
  This expects that certain observations are filtered out in the API call:
  - Non-Finnish. If this is exapanded to other countries, remove hard-coded country name. Also note that country name may be in any language or abbreviation (Finland, FI, Suomi...).
  - Observations without license
  - Captive/cultivated 
  - without_taxon_id: [human id] (todo: remove human filter here)
  
  Todo:
  - Decide what to do with unit and gathering id's. Gut feeling (24.9.2019: Create a new, since iNat has not officially declared that the uri is an identifier. GBIF just acts like it is.)
  - Check that all fields are shown on dw
  - Check that all non-meta data is under public document
  - Quality metrics & quality grade (casual, research) affecting quality fields in DW
  - Filter mikkohei13 observations (will be duplicates, but have images...)
  - Edited date? yyyy-mm-dd

  Ask Esko:
  - Showing license uri on viewer, list, file download?

  Notes:
  - Samalla nimellä voi olla monta faktaa
  - Faktat ovat stringejä
  - Kenttiä voi jättää tyhjiksi, se vain kasattaa json:in kokoa.
  - Enum-arvot ovat all-caps
  - Ei käytä media-objektia, koska kuviin viittaaminen kuitenkin ylittäisi api-limiitin

  Ask iNat:
  - How to have FinBIF here (not important, mostly curious...):
  - This observation is featured on 1 site

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
  if (NULL == $inat['observed_on_details']) {
    log2("NOTICE", "skipped observation without date\t" . $inat['id'], "log/inat-obs-log.log");
    return FALSE;
  }

//  echo "HERE GOES obs ".$inat['id'].":\n"; print_r ($inat); // debug

  // Data shared by all observations
  $collectionId = "http://tun.fi/HR.3211"; // Prod: HR.3211 Test: HR.11146
  $dw['collectionId'] = $collectionId;
  $dw['publicDocument']['collectionId'] = $collectionId;
  $dw['sourceId'] = "http://tun.fi/KE.901";
  $dw['deleteRequest'] = FALSE;
  $dw['schema'] = "laji-etl";
  $dw['publicDocument']['secureLevel'] = "NONE";
  $dw['publicDocument']['concealment'] = "PUBLIC";
  $dw['publicDocument']['gatherings'][0]['units'][0]['recordBasis'] = "HUMAN_OBSERVATION_UNSPECIFIED";

  $keywordsArr = Array();
  $descArr = Array();
  $factsArr = Array();


  // Debug error handling
//  $foo = $inat['foobar'];


  // Id's
  $documentId = "https://www.inaturalist.org/observations/" . $inat['id']; // Note: GBIF also uses this as an occurrence ID  
//  $documentId = "http://tun.fi/HR.3211/" . $inat['id']; // Test

  $dw['documentId'] = $documentId;
  $dw['publicDocument']['documentId'] = $documentId;
  $dw['publicDocument']['gatherings'][0]['gatheringId'] = $documentId . "-G";
  $dw['publicDocument']['gatherings'][0]['units'][0]['unitId'] = $documentId . "-U";

  $factsArr = factsArrayPush($factsArr, "D", "catalogueNumber", $inat['id'], TRUE);
  $factsArr = factsArrayPush($factsArr, "D", "referenceURI", $inat['uri'], TRUE);
  array_push($keywordsArr, strval($inat['id'])); // id has to be string


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
    foreach ($qualityMetrics as $key => $value) {
      $factsArr = factsArrayPush($factsArr, "D", "quality_metrics_" . $key, $value, TRUE);
    }
  }


  // Projects
  // todo: do we need to store whether the project is trad/non-trad? Do they share identifier namespace?
  // Non-traditional / Collection (automatic)
  if (isset($inat['non_traditional_projects'])) {
    foreach($inat['non_traditional_projects'] as $projectNro => $project) {
      array_push($keywordsArr, "project-" . $project['project_id']);
      $factsArr = factsArrayPush($factsArr, "D", "collectionProjectId", $project['project_id']);
    }
  }

  // Traditional (manual)
  if (isset($inat['project_observations'])) {
    foreach($inat['project_observations'] as $projectNro => $project) {
      array_push($keywordsArr, "project-" . $project['project']['id']);
      $factsArr = factsArrayPush($factsArr, "D", "traditionalProjectId", $project['project']['id']);
    }
  }


  // Dates
  $dw['publicDocument']['createdDate'] = $inat['created_at_details']['date']; // todo: esko: onko tämä oikea taso?
  $updatedDatePieces = explode("T", $inat['updated_at']);
  $dw['publicDocument']['modifiedDate'] = $updatedDatePieces[0]; // todo: esko: onko tämä oikea taso?

  $dw['publicDocument']['gatherings'][0]['eventDate']['begin'] = removeNullFalse($inat['observed_on_details']['date']);
  $dw['publicDocument']['gatherings'][0]['eventDate']['end'] = $dw['publicDocument']['gatherings'][0]['eventDate']['begin']; // End is same as beginning

  $factsArr = factsArrayPush($factsArr, "D", "observedOrCreatedAt", $inat['time_observed_at']); // This is usually datetime observed (taken from image or app), but sometimes datetime created


  // Coordinates
  if ($inat['mappable']) {
    // Coordinate accuracy
    $dw['publicDocument']['gatherings'][0]['coordinates']['type'] = "WGS84";

    if (empty($inat['positional_accuracy'])) {
      $accuracy = 1000; // Default for missing values
    }
    elseif ($inat['positional_accuracy'] < 10) {
      $accuracy = 10; // Minimum value
    }
    else {
      $accuracy = round($inat['positional_accuracy'], 0); // Round to one meter
    }
    $dw['publicDocument']['gatherings'][0]['coordinates']['accuracyInMeters'] = $accuracy;

    // Coordinates
    // Rounding, see https://gis.stackexchange.com/questions/8650/measuring-accuracy-of-latitude-and-longitude/8674#8674
    $lon = round(removeNullFalse($inat['geojson']['coordinates'][0]), 6);
    $lat = round(removeNullFalse($inat['geojson']['coordinates'][1]), 6);

    $dw['publicDocument']['gatherings'][0]['coordinates']['lonMin'] = $lon;
    $dw['publicDocument']['gatherings'][0]['coordinates']['lonMax'] = $lon;
    $dw['publicDocument']['gatherings'][0]['coordinates']['latMin'] = $lat;
    $dw['publicDocument']['gatherings'][0]['coordinates']['latMax'] = $lat;
  }


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
  $photoCount = count($inat['observation_photos']);
  if ($photoCount >= 1) {
    array_push($keywordsArr, "has_images"); // not needed if we use media object
    foreach ($inat['observation_photos'] as $photoNro => $photo) {
      $factsArr = factsArrayPush($factsArr, "U", "imageId", $photo['photo']['id']); // Photo id
      $factsArr = factsArrayPush($factsArr, "U", "imageUrl", "https://www.inaturalist.org/photos/" . $photo['photo']['id']); // Photo link
    }
    $factsArr = factsArrayPush($factsArr, "U", "imageCount", $photoCount);
  }


  // Sounds
  $soundCount = count($inat['sounds']);
  if ($soundCount >= 1) {
    array_push($keywordsArr, "has_audio"); // not needed if we use media object
    foreach ($inat['sounds'] as $soundNro => $sound) {
      $factsArr = factsArrayPush($factsArr, "U", "audioId", $sound['id']); // Sound id
      $factsArr = factsArrayPush($factsArr, "U", "audioUrl", $sound['file_url']); // Sound link
    }
    $factsArr = factsArrayPush($factsArr, "U", "audioCount", $soundCount);
  }


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
      $factsArr = factsArrayPush($factsArr, "U", $anno['attribute'], $anno['value']);
      
      if (isset($anno['dwLifeStage'])) {
        $dw['publicDocument']['gatherings'][0]['units'][0]['lifeStage'] = $anno['dwLifeStage'];
      }
      if (isset($anno['dwSex'])) {
        $dw['publicDocument']['gatherings'][0]['units'][0]['sex'] = $anno['dwSex'];
      }
    }
  }


  // Taxon
  $dw['publicDocument']['gatherings'][0]['units'][0]['taxonVerbatim'] = handleTaxon($inat['taxon']['name']);
  $factsArr = factsArrayPush($factsArr, "U", "taxonVerbatim", handleTaxon($inat['species_guess']));


  // Observations fields
  foreach($inat['ofvs'] as $ofvsNro => $ofvs) {
    $factsArr = factsArrayPush($factsArr, "U", $ofvs['name_ci'], $ofvs['value_ci'], TRUE); // This must preserve zero values
  }

  // Observer name
  // Prefer full name over loginname
  if (isset($inat['user']['name'])) {
    $observer = $inat['user']['name'];
  }
  else {
    $observer = $inat['user']['login'];
  }
  $dw['publicDocument']['gatherings'][0]['team'][0] = $observer;

  // Editor & observer id
  $userId = "KE.901:" . $inat['user']['id'];
  $dw['publicDocument']['editorUserIds'][0] = $userId;
  $dw['publicDocument']['gatherings'][0]['observerUserIds'][0] = $userId;

  // Orcid
  if (!empty($inat['user']['orcid'])) {
    $factsArr = factsArrayPush($factsArr, "D", "observerOrcid", $inat['user']['orcid'], FALSE);
  }


  // Quality grade
  $factsArr = factsArrayPush($factsArr, "D", "quality_grade", $inat['quality_grade']);
  array_push($keywordsArr, $inat['quality_grade'] . "_grade");


  // License URL's/URI's
  $dw['publicDocument']['licenseId'] = getLicenseUrl($inat['license_code']);

  
  // Misc facts
  $factsArr = factsArrayPush($factsArr, "G", "out_of_range", $inat['out_of_range'], FALSE);
  $factsArr = factsArrayPush($factsArr, "D", "taxon_geoprivacy", $inat['taxon_geoprivacy'], FALSE);
  $factsArr = factsArrayPush($factsArr, "D", "context_geoprivacy", $inat['context_geoprivacy'], FALSE);
  $factsArr = factsArrayPush($factsArr, "D", "context_user_geoprivacy", $inat['context_user_geoprivacy'], FALSE);
  $factsArr = factsArrayPush($factsArr, "D", "context_taxon_geoprivacy", $inat['context_taxon_geoprivacy'], FALSE);
  $factsArr = factsArrayPush($factsArr, "D", "comments_count", $inat['comments_count'], FALSE);
  $factsArr = factsArrayPush($factsArr, "U", "num_identification_agreements", $inat['num_identification_agreements'], FALSE);
  $factsArr = factsArrayPush($factsArr, "U", "num_identification_disagreements", $inat['num_identification_disagreements'], FALSE);
//  $factsArr = factsArrayPush($factsArr, "U", "identifications_most_agree", $inat['identifications_most_agree']);
//  $factsArr = factsArrayPush($factsArr, "U", "identifications_most_disagree", $inat['identifications_most_disagree']);
//  $factsArr = factsArrayPush($factsArr, "U", "observerActivityCount", $inat['user']['activity_count']); // This is problematic because it increases over time -> is affected by *when* the observation was fetched from iNat
  $factsArr = factsArrayPush($factsArr, "U", "owners_identification_from_vision", $inat['owners_identification_from_vision'], FALSE);
//  $factsArr = factsArrayPush($factsArr, "D", "", $inat(['']);



  // ----------------------------------------------------------------------------------------

  // Handle temporary arrays 
  $dw['publicDocument']['keywords'] = $keywordsArr;
  $dw['publicDocument']['gatherings'][0]['notes'] = implode(" / ", $descArr);

  if (!empty($factsArr['D'])) {
    $dw['publicDocument']['facts'] = $factsArr['D'];
  }
  if (!empty($factsArr['G'])) {
    $dw['publicDocument']['gatherings'][0]['facts'] = $factsArr['G'];
  }
  if (!empty($factsArr['U'])) {
    $dw['publicDocument']['gatherings'][0]['units'][0]['facts'] = $factsArr['U'];
  }


  log2("SUCCESS", "Converted observation\t" . $inat['id'] . " of " . $inat['taxon']['name'] . " on " . $inat['observed_on_details']['date'], "log/inat-obs-log.log");
  echo "handled ".$inat['id']."\n"; // debug


  return $dw;
}