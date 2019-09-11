<?php

function stringReverse($string) {
  $pieces = explode(", ", $string);
  $pieces = array_reverse($pieces);
  $reversedString = implode(", ", $pieces);
  return $reversedString;
}

function factsArrayPush($factsArr, $fact, $value) {

  // Don't add empty facts, but consider 0 to be non-empty (can be used e.g. in observation fields)
  if (empty($value) && 0 !== $value) {
    return $factsArr;
  }
  
  $newArr = Array();
  $newArr['fact'] = preg_replace('/[^\w]/', '', $fact); // Remove spaces, special chars etc. from fact names. Does this handle all cases? Seems to allow _ but not ÅÄÖåäö-".,;:
  $newArr['value'] = $value;

  $factsArr[] = $newArr;
  return $factsArr;
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