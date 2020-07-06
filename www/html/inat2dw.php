<?php

// Converts one iNat observation to DW format


function observationInat2Dw($inat) {

//  print_r ($inat); // DEBUG

  $dw = Array();

  /*
  This expects that certain observations are filtered out in the API call:
  - Non-Finnish. If this is exapanded to other countries, remove hard-coded country name. Also note that country name may be in any language or abbreviation (Finland, FI, Suomi...).
  - Observations without license
  - Captive/cultivated 

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
    log2("WARNING", "Skipped observation having flag(s): " . $inat['id'], "log/inat-obs-log.log");
    return FALSE;
    // TODO: delete if already in DW?
  }
  if (empty($inat['taxon'])) {
    log2("WARNING", "Skipped observation without identification: " . $inat['id'], "log/inat-obs-log.log");
    return FALSE;
  }
  // TODO: If human, clear description etc.
  /*
  if ("Homo sapiens" == $inat['taxon']['name']) {
    log2("WARNING", "Skipped observation of human(s): " . $inat['id'], "log/inat-obs-log.log");
    return FALSE;
    // TODO: delete if already in DW?
  }
  */
  if (NULL == $inat['observed_on_details']) {
    log2("WARNING", "Skipped observation without date: " . $inat['id'], "log/inat-obs-log.log");
    return FALSE;
  }

  // Filter testaaja's observations
  /*
  $vihkoUsers[] = "testaaja";
  if (in_array($inat['user']['login'], $vihkoUsers)) {
    log2("WARNING", "Skipped observation by Vihko user: user " . $inat['user']['login']. ", obs " . $inat['id'], "log/inat-obs-log.log");
    return FALSE;
  }
  */

  // Check that is from Finland
  if (!in_array(7020, $inat['place_ids'])) {
    log2("WARNING", "Skipped observation not from Finland: " . $inat['id'], "log/inat-obs-log.log");
    return FALSE;
  }

  // Non-wild (captive)
  if (1 == $inat['captive']) {
    $dw['publicDocument']['gatherings'][0]['units'][0]['wild'] = false;
  }

//  echo "HERE GOES obs ".$inat['id'].":\n"; print_r ($inat); // debug

  // Data shared by all observations
  $collectionId = "http://tun.fi/HR.3211"; // Prod: HR.3211 Test: HR.11146
  $dw['collectionId'] = $collectionId;
  $dw['publicDocument']['collectionId'] = $collectionId;
  $dw['sourceId'] = "http://tun.fi/KE.901";
//  $dw['deleteRequest'] = FALSE;
  $dw['schema'] = "laji-etl";
  $dw['publicDocument']['secureLevel'] = "NONE";
  $dw['publicDocument']['concealment'] = "PUBLIC";
  

  $keywordsArr = Array();
  $descArr = Array();
  $factsArr = Array();

  // Debug error handling
//  $foo = $inat['foobar'];

  // Id's
//  $documentId = "https://www.inaturalist.org/observations/" . $inat['id']; // Note: GBIF also uses this as an occurrence ID  
  $documentId = "http://tun.fi/HR.3211/" . $inat['id'];

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
  // Not that the iNat API displays quality metric changes with a delay (maybe 15 mins??)
  $qualityMetricUnreliable = FALSE;
  if ($inat['quality_metrics']) {
    $qualityMetrics = summarizeQualityMetrics($inat['quality_metrics']);
    foreach ($qualityMetrics as $key => $value) {
      $factsArr = factsArrayPush($factsArr, "U", "quality_metrics_" . $key, $value, TRUE);

      // If at least one negative quality metric -> unreliable
      if (-1 == $value) {
        $qualityMetricUnreliable = TRUE;
      }
    }
  }


  // Projects
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
  $dw['publicDocument']['createdDate'] = $inat['created_at_details']['date'];
  $updatedDatePieces = explode("T", $inat['updated_at']);
  $dw['publicDocument']['modifiedDate'] = $updatedDatePieces[0];

  $dw['publicDocument']['gatherings'][0]['eventDate']['begin'] = removeNullFalse($inat['observed_on_details']['date']);
  $dw['publicDocument']['gatherings'][0]['eventDate']['end'] = $dw['publicDocument']['gatherings'][0]['eventDate']['begin']; // End is same as beginning

  $factsArr = factsArrayPush($factsArr, "D", "observedOrCreatedAt", $inat['time_observed_at']); // This is usually datetime observed (taken from image or app), but sometimes datetime created


  // Coordinates
  if ($inat['mappable']) { // ABBA
    $dw['publicDocument']['gatherings'][0]['coordinates']['type'] = "WGS84";


    // TODO: observation without coordinates

    // Obscured observation
    if (TRUE === $inat['obscured']) { // Alternative: ("obscured" == geoprivacy || "obscured" = taxon_geoprivacy)
      echo "HERE1";
      // Bounding box coordinates
      $lon = substr(removeNullFalse($inat['geojson']['coordinates'][0]), 0, 4);
      $lat = substr(removeNullFalse($inat['geojson']['coordinates'][1]), 0, 4);
//      echo "LON:" . $lon;

      // Conservative bounding box calculation: this is less accurate than the box shown on the iNat website, but the true location is definitely inside this box.
      // Todo: Find out how the bounding box on iNat observation page is calculated - how do they know which 0.2x0.2 square to draw? It's not on the API.
      $dw['publicDocument']['gatherings'][0]['coordinates']['lonMin'] = $lon - 0.1;
      $dw['publicDocument']['gatherings'][0]['coordinates']['lonMax'] = $lon + 0.2;
      $dw['publicDocument']['gatherings'][0]['coordinates']['latMin'] = $lat - 0.1;
      $dw['publicDocument']['gatherings'][0]['coordinates']['latMax'] = $lat + 0.2;  

      $dw['publicDocument']['gatherings'][0]['coordinates']['accuracyInMeters'] = 0;
    }

    // Non-obscured observation
    else {
      echo "HERE2";
      if (empty($inat['positional_accuracy'])) {
        $accuracy = 100; // Default for missing values. Mobile app often misses the value, even if the coordinates are accurate.
      }
      elseif ($inat['positional_accuracy'] < 10) {
        $accuracy = 5; // Minimum value
      }
      else {
        $accuracy = round($inat['positional_accuracy'], 0); // Round to one meter
      }
      $dw['publicDocument']['gatherings'][0]['coordinates']['accuracyInMeters'] = $accuracy;

      // Point coordinates
      // Rounding, see https://gis.stackexchange.com/questions/8650/measuring-accuracy-of-latitude-and-longitude/8674#8674
      $lon = round(removeNullFalse($inat['geojson']['coordinates'][0]), 6);
      $lat = round(removeNullFalse($inat['geojson']['coordinates'][1]), 6);

      $dw['publicDocument']['gatherings'][0]['coordinates']['lonMin'] = $lon;
      $dw['publicDocument']['gatherings'][0]['coordinates']['lonMax'] = $lon;
      $dw['publicDocument']['gatherings'][0]['coordinates']['latMin'] = $lat;
      $dw['publicDocument']['gatherings'][0]['coordinates']['latMax'] = $lat;  
    }

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
      
      // Facts
      $factsArr = factsArrayPush($factsArr, "U", "imageId", $photo['photo']['id']); // Photo id
      $factsArr = factsArrayPush($factsArr, "U", "imageUrl", "https://www.inaturalist.org/photos/" . $photo['photo']['id']); // Photo link

      // CC-licensed photos via proxy
      if (!empty($photo['photo']['license_code'])) {
        $squareUrl = $photo['photo']['url'];

        $thumbnailUrl = str_replace("square", "small", $squareUrl);
        $thumbnailUrl = str_replace("https://static.inaturalist.org/photos/", "https://proxy.laji.fi/inaturalist/photos/", $thumbnailUrl); // TODO: refactor into helper

        $fullUrl = str_replace("square", "original", $squareUrl);
        $fullUrl = str_replace("https://static.inaturalist.org/photos/", "https://proxy.laji.fi/inaturalist/photos/", $fullUrl); // TODO: refactor

        $media = Array();

        $media['thumbnailURL'] = $thumbnailUrl;
        $media['copyrightOwner'] = $photo['photo']['attribution'];
        $media['author'] = $photo['photo']['attribution']; // TODO: or simply observer name
        $media['fullURL'] = $fullUrl;
        $media['licenseId'] = getLicenseUrl($photo['photo']['license_code']);
        $media['mediaType'] = "IMAGE";

        $dw['publicDocument']['gatherings'][0]['units'][0]['media'][] = $media;
      }
      else {
        array_push($keywordsArr, "image_arr"); // keyword for all rights reserved -images
      }

    }
    $factsArr = factsArrayPush($factsArr, "U", "imageCount", $photoCount);
  }

  // Sounds
  // Note: if audio is linked via proxy, need to check that cc-license is given 
  $soundCount = count($inat['sounds']);
  if ($soundCount >= 1) {
    array_push($keywordsArr, "has_audio"); // not needed if we use media object
    foreach ($inat['sounds'] as $soundNro => $sound) {
      $factsArr = factsArrayPush($factsArr, "U", "audioId", $sound['id']); // Sound id
      $factsArr = factsArrayPush($factsArr, "U", "audioUrl", $sound['file_url']); // Sound link
    }
    $factsArr = factsArrayPush($factsArr, "U", "audioCount", $soundCount);
  }

  if (FALSE == $soundCount && FALSE == $photoCount) {
    array_push($keywordsArr, "no_media"); // To search for obs which cannot be verified
  }


  // Record basis
  if ($inat['ofvs']) {
    $dw['publicDocument']['gatherings'][0]['units'][0]['recordBasis'] = "PRESERVED_SPECIMEN";
  }
  elseif ($photoCount >= 1) {
    $dw['publicDocument']['gatherings'][0]['units'][0]['recordBasis'] = "HUMAN_OBSERVATION_PHOTO";
  }
  elseif ($soundCount >= 1) {
    $dw['publicDocument']['gatherings'][0]['units'][0]['recordBasis'] = "HUMAN_OBSERVATION_RECORDED_AUDIO";
  }
  else {
    $dw['publicDocument']['gatherings'][0]['units'][0]['recordBasis'] = "HUMAN_OBSERVATION_UNSPECIFIED";
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
  // scientific name iNat interprets this to be, special cases converted by this script to match FinBIF taxonomy
  $dw['publicDocument']['gatherings'][0]['units'][0]['taxonVerbatim'] = handleTaxon($inat['taxon']['name']);

  // name observer or identifiers(?) have given, can be any language
  $factsArr = factsArrayPush($factsArr, "U", "taxonByUser", handleTaxon($inat['species_guess']));

  // scientific name iNat interprets this to be
  $factsArr = factsArrayPush($factsArr, "U", "taxonInterpretationByiNaturalist", $inat['taxon']['name']);


  // Observations fields
  foreach($inat['ofvs'] as $ofvsNro => $ofvs) {
    $factsArr = factsArrayPush($factsArr, "U", $ofvs['name_ci'], $ofvs['value_ci'], TRUE); // This must preserve zero values
  }

  // Observer name
  // Prefer full name over loginname
  if (!empty($inat['user']['name'])) {
    $observer = $inat['user']['name'];
  }
  else {
    $observer = $inat['user']['login'];
  }
  $dw['publicDocument']['gatherings'][0]['team'][0] = $observer;

  // Editor & observer id
  $userId = "inaturalist:" . $inat['user']['id'];
  $dw['publicDocument']['editorUserIds'][0] = $userId;
  $dw['publicDocument']['gatherings'][0]['observerUserIds'][0] = $userId;

  // Orcid
  if (!empty($inat['user']['orcid'])) {
    $factsArr = factsArrayPush($factsArr, "D", "observerOrcid", $inat['user']['orcid'], FALSE);
  }


  // Quality grade
  $factsArr = factsArrayPush($factsArr, "U", "quality_grade", $inat['quality_grade'] . "_grade");
  array_push($keywordsArr, $inat['quality_grade'] . "_grade");

  // License URL's/URI's
  $dw['publicDocument']['licenseId'] = getLicenseUrl($inat['license_code']);

  
  // Misc facts
  $factsArr = factsArrayPush($factsArr, "U", "out_of_range", $inat['out_of_range'], FALSE);
  $factsArr = factsArrayPush($factsArr, "U", "taxon_geoprivacy", $inat['taxon_geoprivacy'], FALSE);
  $factsArr = factsArrayPush($factsArr, "U", "geoprivacy", $inat['geoprivacy'], FALSE); // New 4/2020?
  $factsArr = factsArrayPush($factsArr, "U", "context_geoprivacy", $inat['context_geoprivacy'], FALSE); // Is this used anymore 4/2020?
  $factsArr = factsArrayPush($factsArr, "U", "context_user_geoprivacy", $inat['context_user_geoprivacy'], FALSE); // Is this used anymore 4/2020?
  $factsArr = factsArrayPush($factsArr, "U", "context_taxon_geoprivacy", $inat['context_taxon_geoprivacy'], FALSE); // Is this used anymore 4/2020?
  $factsArr = factsArrayPush($factsArr, "U", "comments_count", $inat['comments_count'], FALSE);
  $factsArr = factsArrayPush($factsArr, "U", "num_identification_agreements", $inat['num_identification_agreements'], FALSE);
  $factsArr = factsArrayPush($factsArr, "U", "num_identification_disagreements", $inat['num_identification_disagreements'], FALSE);
//  $factsArr = factsArrayPush($factsArr, "U", "identifications_most_agree", $inat['identifications_most_agree']);
//  $factsArr = factsArrayPush($factsArr, "U", "identifications_most_disagree", $inat['identifications_most_disagree']);
//  $factsArr = factsArrayPush($factsArr, "U", "observerActivityCount", $inat['user']['activity_count']); // This is problematic because it increases over time -> is affected by *when* the observation was fetched from iNat
  $factsArr = factsArrayPush($factsArr, "U", "owners_identification_from_vision", $inat['owners_identification_from_vision'], FALSE);
//  $factsArr = factsArrayPush($factsArr, "D", "", $inat(['']);


  // DW quality issues & tags

  // TODO:
  // What to do if observation contains 1...n copyright infringement flaged media files, e.g. https://www.inaturalist.org/observations/46356508

  // Handling flagged obs has not been tested!
  if (!empty($inat['flags'])) {
    $dw['publicDocument']['concealment'] = "PRIVATE";

    $dw['publicDocument']['gatherings'][0]['units'][0]['quality']['issue']['issue'] = "REPORTED_UNRELIABLE";
    $dw['publicDocument']['gatherings'][0]['units'][0]['quality']['issue']['source'] = "ORIGINAL_DOCUMENT";

    array_push($keywordsArr, "flagged");
  }

  // Negative quality metrics (thumbs down) 
  if ($qualityMetricUnreliable) {
    $dw['publicDocument']['gatherings'][0]['units'][0]['quality']['issue']['issue'] = "REPORTED_UNRELIABLE";
    $dw['publicDocument']['gatherings'][0]['units'][0]['quality']['issue']['source'] = "ORIGINAL_DOCUMENT";

    array_push($keywordsArr, "quality-metric-unreliable");
  }

  // Humans
  // DW removed/hides humans, so this is not really needed. Saved for possible future use. 
  if ("Homo sapiens" == $inat['taxon']['name']) {
//    $dw['publicDocument']['concealment'] = "PRIVATE";

    // Remove images
    unset($dw['publicDocument']['gatherings'][0]['units'][0]['media']);

    // Clear description
    $descArr = Array();
  }

/*
  // Not yet supported by DW
  if ("research" == $inat['quality_grade']) {
    $dw['publicDocument']['gatherings'][0]['units'][0]['sourceTags'][] = "COMMUNITY_TAG_VERIFIED";
  }
*/

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


  log2("NOTICE", "Converted observation\t" . $inat['id'] . " of " . $inat['taxon']['name'] . " on " . $inat['observed_on_details']['date'], "log/inat-obs-log.log");
//  echo "handled ".$inat['id']."\n"; // debug


  return $dw;
}