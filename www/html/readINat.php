<?php
echo "<pre>";

// Finnish, CC, wild
// 10 per page, page 1

$url = "http://api.inaturalist.org/v1/observations?captive=false&license=cc-by%2Ccc-by-nc%2Ccc-by-nd%2Ccc-by-sa%2Ccc-by-nc-nd%2Ccc-by-nc-sa%2Ccc0&place_id=7020&page=1&per_page=10&order=desc&order_by=created_at";

// Koivunpunikkitatti, testihavainto joss projekti ja tageja
// Onko datamalli sama kuin observations-haussa?

$url = "http://api.inaturalist.org/v1/observations/32469823";


$json = file_get_contents($url);
//echo $json;
$data = json_decode($json, TRUE);

$meta['totalResults'] = $data['total_results']; 
$meta['page'] = $data['page'];
$meta['perPage'] = $data['per_page'];
$meta['pagesTotal'] = ceil($meta['totalResults'] / $meta['perPage']);
$meta['pagesToGo'] = $meta['pagesTotal'] - $meta['page'];

print_r ($meta);

foreach ($data['results']  as $nro => $obs) {
  $dwObservations[] = observationInat2Dw($obs);
}

$dwRoot = Array();
$dwRoot['schema'] = "laji-etl";
$dwRoot['roots'] = $dwObservations;

print_r ($dwRoot);

function observationInat2Dw($inat) {
  $dw = Array();
  $dw['collectionId'] = "http://tun.fi/HR.3211";
  $dw['sourceId'] = "http://tun.fi/HR.3211";
  $dw['deleteRequest'] = FALSE;
  $dw['schema'] = "laji-etl";


  // Obs
  $dw['documentId'] = "http://tun.fi/HR.3211/" . $inat['id']; // todo: Esko: based on KE-identifier? 
  $dw['publicDocument']['secureLevel'] = "NONE";

  // Keywords
//  $dw['publicDocument']['keywords'] // todo: Esko: does this have to be id's?

  return $dw;
}
