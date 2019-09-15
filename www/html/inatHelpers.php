<?php

function stringReverse($string) {
  $pieces = explode(", ", $string);
  $pieces = array_reverse($pieces);
  $reversedString = implode(", ", $pieces);
  return $reversedString;
}

function factsArrayPush($factsArr, $fact, $value, $preserveZeroAndFalse = FALSE) {

  echo $fact .":". $value; // debug

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
  $newArr['value'] = $value;

  $factsArr[] = $newArr;
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

/*
$projectKeywords = inatProjects2keywords($inat['non_traditional_projects']);

function inatProjects2keywords($projects) {
  foreach ($projects as $nro => $project) {
    $project['project_id'];
  }
  $url = "";
  $json = file_get_contents($url);
  $data = json_decode($json, TRUE);
  print_r ($data);
}

*/