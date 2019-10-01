<?php

function stringReverse($string) {
  $pieces = explode(", ", $string);
  $pieces = array_reverse($pieces);
  $reversedString = implode(", ", $pieces);
  return $reversedString;
}

function factsArrayPush($factsArr, $level, $fact, $value, $preserveZeroAndFalse = FALSE) {

  if ($level !== "D" && $level !== "G" && $level !== "U") {
    log2("ERROR", "Level must be D, G or U", "log/inat-obs-log.log");
  }
//  echo $fact .":". $value; // debug

  // Don't preserve non-set values
  if (!isset($value)) {
    return $factsArr;
  }
  // Don't preserve zero values, unless asked for
  if (0 === $value && FALSE === $preserveZeroAndFalse)
  {
    return $factsArr;
  }
  // Don't preserve FALSE values, unless asked for
  if (FALSE === $value && FALSE === $preserveZeroAndFalse)
  {
    return $factsArr;
  }

  
  $newArr = Array();
  $newArr['fact'] = preg_replace('/[^\w]/', '', $fact); // Remove spaces, special chars etc. from fact names. Does this handle all cases? Seems to allow _ but not ÅÄÖåäö-".,;:
  $newArr['value'] = strval($value); // Must be always string

  $factsArr[$level][] = $newArr;
  return $factsArr;
}

function summarizeQualityMetrics($qmArray) {
  $ret = Array();

  foreach ($qmArray as $nro => $qm) {
    if (TRUE === $qm['agree']) {
      $value = 1;
    }
    elseif (FALSE === $qm['agree']) {
      $value = -1;
    }
    else {
      $value = 0;
    }
    @$ret[$qm['metric']] = $ret[$qm['metric']] + $value; // Note: @ suppressess errors
  }

  return $ret;

}

function removeNullFalse($var) {
  if (FALSE === $var || NULL === $var) {
    return "";
  }
  else {
    return $var;
  }
}

// Handle special taxa
function handleTaxon($taxon) {
  if ("Life" === $taxon || "" === $taxon || "unknown" === $taxon || FALSE === $taxon || NULL === $taxon) {
    return "Biota";
  }
  else {
    return $taxon;
  }
}

function handleAnnotation($annotation) {

  /*
  Annotations describe three features: Life stage, plant phenology and Sex.
  The logic seems to be a bit difficult, if I uncderstand it correctly:
    - Person B creates an observation
    - Person A creates an annotation, e.g. saying Life stage is Adult
    - Person B can vote for or against the annotation
    - Person C, D etc. can vote for or against the annotation
    - Person cannot create a new annotation about the same thing. He can (probably) remove person A's annotation and then create a new annotation. What then happens to the votes of B, C etc. ?

    Because this is complicated, let's just save the original annotation as a fact for now. 
    Todo: Find out the logic and decide what to do. Prepare also to the possibility that allowed values may change over time. 

    Attributes:
    1=Life Stage, 9=Sex, 12=Plant Phenology

    Values:
    Life Stage: 2=Adult, 3=Teneral, 4=Pupa, 5=Nymph, 6=Larva, 7=Egg, 8=Juvenile, 16=Subimago
    Sex: 10=Female, 11=Male
    Plant Phenology: 13=Flowering, 14=Fruiting, 15=Budding

    See more at https://forum.inaturalist.org/t/how-to-use-inaturalists-search-urls-wiki/63

  */

  // Mapping for facts
  $annoAttributeMap[1] = "LifeStage";
  $annoAttributeMap[9] = "Sex";
  $annoAttributeMap[12] = "PlantPhenology";

  $annoValueMap[2] = "Adult";
  $annoValueMap[3] = "Teneral"; // unclear how this matches dw values -> not used
  $annoValueMap[4] = "Pupa";
  $annoValueMap[5] = "Nymph";
  $annoValueMap[6] = "Larva";
  $annoValueMap[7] = "Egg";
  $annoValueMap[8] = "Juvenile";
  $annoValueMap[16] = "Subimago";
  $annoValueMap[10] = "Female";
  $annoValueMap[11] = "Male";
  $annoValueMap[13] = "Flowering";
  $annoValueMap[14] = "Fruiting"; // unclear is this ripe vs. ripening -> not used
  $annoValueMap[15] = "Budding"; // unclear whether this is bud or opened bud -> not used

  $ret = Array();
  $ret['attribute'] = $annoAttributeMap[$annotation['controlled_attribute_id']];
  $ret['value'] = $annoValueMap[$annotation['controlled_value_id']];

  // Mapping for native variables
  switch ($annotation['controlled_value_id']) {
    case 2:
      $ret['dwLifeStage'] = "ADULT";
      break;
    case 4:
      $ret['dwLifeStage'] = "PUPA";
      break;
    case 5:
      $ret['dwLifeStage'] = "NYMPH";
      break;
    case 6:
      $ret['dwLifeStage'] = "LARVA";
      break;
    case 7:
      $ret['dwLifeStage'] = "EGG";
      break;
    case 8:
      $ret['dwLifeStage'] = "JUVENILE";
      break;
    case 16:
      $ret['dwLifeStage'] = "SUBIMAGO";
      break;
    case 13:
      $ret['dwLifeStage'] = "FLOWER";
      break;
    case 10:
      $ret['dwSex'] = "FEMALE";
      break;
    case 11:
      $ret['dwSex'] = "MALE";
      break;
    default:
      break;
  }

  return $ret;
}

function getLicenseUrl($licenseCode) {
  switch ($licenseCode) {
    case "cc0":
      $ret = "http://tun.fi/MZ.intellectualRightsCC0-4.0";
      break;
    case "cc-by":
      $ret = "http://tun.fi/MZ.intellectualRightsCC-BY-4.0";
      break;
    case "cc-by-nc":
      $ret = "http://tun.fi/MZ.intellectualRightsCC-BY-NC-4.0";
      break;
    case "cc-by-nd":
      $ret = "http://tun.fi/MZ.intellectualRightsCC-BY-ND-4.0";
      break;
    case "cc-by-sa":
      $ret = "http://tun.fi/MZ.intellectualRightsCC-BY-SA-4.0";
      break;
    case "cc-by-nc-nd":
      $ret = "http://tun.fi/MZ.intellectualRightsCC-BY-NC-ND-4.0";
      break;
    case "cc-by-nc-sa":
      $ret = "http://tun.fi/MZ.intellectualRightsCC-BY-NC-SA-4.0";
      break;
    default:
      $ret = "http://tun.fi/MZ.intellectualRightsCC-BY-NC-ND-4.0"; // Default to strictest license
      break;
  }
  return $ret;
}

function hashInatObservation($inat) {
  if (!is_array($inat)) {
    return "not an array, " . time();
  }

  unset($inat['place_ids']);

  $inat['moved']['taxonName'] = $inat['taxon']['name'];
  unset($inat['taxon']);

  unset($inat['ident_taxon_ids']);
  unset($inat['faves_count']);

  $inat['moved']['userLogin_exact'] = $inat['user']['login_exact'];
  unset($inat['user']);
  
  unset($inat['identifications']); // Id's always change id count -> detailed information is not needed for hash
  unset($inat['non_owner_ids']);

//  print_r ($inat); exit(); // Debug: show what is included in the hash
  
  // Hash
  return sha1(serialize($inat));
}