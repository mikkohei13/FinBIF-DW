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
  - 
  
  Todo:
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

  TODO:
  - Esko tekee swagger-dokumentaation
  - Media-url:it faktoina, mieti yleiskäyttöiset termit
    - (Toinen vaihtoehto on käytää mediaobjektia, jossa ml. lisenssi ja thumbnail-kuvan url) https://bitbucket.org/luomus/laji-etl/src/master/WEB-INF/src/main/fi/laji/datawarehouse/etl/models/dw/MediaObject.java
  - date updated on document-tasolla, ks. esimerkki single-querysta
  - havainnoija gathering.team Array of strings, etunimi sukunimi
  - havainnoijan inat-id kahteen kenttään: documentUserID's ja gatheringUsedID:hen, muodossa KE.[numero]:[inat-käyttäjänumero]
  - observerOrcid faktaksi
  - Lisenssikenttä toteutettu, document.licenceID = lisenssin finbif-URI, tai cc-uri

  Notes:
  - Samalla nimellä voi olla monta faktaa

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

//  echo "HERE GOES obs ".$inat['id'].":\n"; print_r ($inat); // debug

  // Data shared by all observations
  $dw['collectionId'] = "http://tun.fi/HR.3211";
  $dw['publicDocument']['collectionId'] = "http://tun.fi/HR.3211";
  $dw['sourceId'] = "http://tun.fi/HR.3211"; // todo: or is this FinBIF-DW
  $dw['deleteRequest'] = FALSE;
  $dw['schema'] = "laji-etl";
  $dw['publicDocument']['secureLevel'] = "NONE";
  $dw['publicDocument']['concealment'] = "PUBLIC";
  $dw['publicDocument']['gatherings'][0]['units'][0]['recordBasis'] = "HUMAN_OBSERVATION_UNSPECIFIED";

  $keywordsArr = Array();
  $descArr = Array();
  $factsArr = Array();


  // Id's
  $documentId = "https://www.inaturalist.org/observations/" . $inat['id']; // Note: GBIF also uses this as an occurrence ID  
  $dw['documentId'] = $documentId;
  $dw['publicDocument']['documentId'] = $documentId;
  $dw['publicDocument']['gatherings'][0]['gatheringId'] = $documentId . "-G";
  $dw['publicDocument']['gatherings'][0]['units'][0]['unitId'] = $documentId . "-U";

  $factsArr = factsArrayPush($factsArr, "D", "catalogueNumber", $inat['id'], TRUE);
  $factsArr = factsArrayPush($factsArr, "D", "referenceURL", "https://www.inaturalist.org/observations/" . $inat['id'], TRUE);
  array_push($keywordsArr, $inat['id']);


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
  $dw['createdDate'] = $inat['created_at_details']['date'];
  $dw['eventDate']['begin'] = removeNullFalse($inat['observed_on_details']['date']);

  $factsArr = factsArrayPush($factsArr, "D", "observedOrCreatedAt", $inat['time_observed_at']);

  // Coordinates
  if ($inat['mappable']) {
    // Coordinate accuracy
    $dw['publicDocument']['gatherings'][0]['coordinates']['type'] = "wgs84";

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
    array_push($keywordsArr, "has_photos");
    foreach ($inat['observation_photos'] as $photoNro => $photo) {
      $factsArr = factsArrayPush($factsArr, "U", "photoId", $photo['photo']['id']);
    }
    $factsArr = factsArrayPush($factsArr, "U", "photoCount", $photoCount);
  }


  // Sounds
  $soundCount = count($inat['sounds']);
  if ($soundCount >= 1) {
    array_push($keywordsArr, "has_sounds");
    foreach ($inat['sounds'] as $soundNro => $sound) {
      $factsArr = factsArrayPush($factsArr, "U", "soundId", $sound['id']);
      array_push($descArr, "sound file: " . $sound['file_url']); // todo: esko: where this to? outgoing links, urls, description field? Do the same for photos, if they have static urls?
    }
    $factsArr = factsArrayPush($factsArr, "U", "soundCount", $soundCount);
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
  $dw['publicDocument']['gatherings'][0]['units'][0]['taxonVerbatim'] = handleTaxon($inat['taxon']['name']);
  $factsArr = factsArrayPush($factsArr, "U", "taxonVerbatim", handleTaxon($inat['species_guess']));


  // Observations fields
  foreach($inat['ofvs'] as $ofvsNro => $ofvs) {
    $factsArr = factsArrayPush($factsArr, "U", $ofvs['name_ci'], $ofvs['value_ci'], TRUE); // This must preserve zero values
  }

  // Quality grade
  $factsArr = factsArrayPush($factsArr, "D", "quality_grade", $inat['quality_grade']);
  array_push($keywordsArr, $inat['quality_grade']);

  // Misc facts
  // todo: are all of these needed / valuable?
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

  // TODO: FIELDS TODO TO DW or MISSING FROM EXAMPLE

  // Observer
  // Prefer full name over loginname
  if (isset($inat['user']['name'])) {
    $observer = $inat['user']['name'];
  }
  else {
    $observer = $inat['user']['login'];
  }
  $dw['publicDocument']['gatherings'][0]['team'][0] = $observer;

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

  if (!empty($factsArr['D'])) {
    $dw['publicDocument']['facts'] = $factsArr['D'];
  }
  if (!empty($factsArr['G'])) {
    $dw['publicDocument']['gatherings'][0]['facts'] = $factsArr['G'];
  }
  if (!empty($factsArr['U'])) {
    $dw['publicDocument']['gatherings'][0]['units'][0]['facts'] = $factsArr['U'];
  }


  log2("SUCCESS", "handled observation\t" . $inat['id'], "log/inat-obs-log.log");
  echo "handled ".$inat['id']."\n"; // debug
  return $dw;
}