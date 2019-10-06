<pre>
<?php

require_once "dwcObs.php";
require_once "mysql.php";
require_once "../log2.php";

$file = "sample_data/stockholm-phanerogamic.csv";

log2("START", "---------------------------------------------", "log/transactionlog.log");
log2("NOTICE", "Started handling file $file", "log/transactionlog.log");


$startTime = time(); // todo: const?
$limit = 1000;
$rowNumber = 0;
$database = new mysqlDb("transactionlog_test");

// Read file
if (($handle = fopen($file, "r")) !== FALSE) {

  // Read rows
  while (($row = fgetcsv($handle, 0, "\t")) !== FALSE) {

    // Handle header row differently
    if (0 == $rowNumber) {
      $colNames = setColNames($row);
      validateFormat($colNames);

      $rowNumber++;
      continue;
    }

    $obs = new dwcObs($row, $colNames, $startTime, $database);

    // todo: might do: keep track of which fields contain data

    /*
    $vihkoRow = handleRow($row, $colNames);
    if (isset($vihkoRow['skipped']) && TRUE === $vihkoRow['skipped']) {
      $skippedRowMessages .= $vihkoRow['row'] . " " . $vihkoRow['skippingReason'] . "\n";
      $skippedRowCount++;
    }
    else {
      $vihkoRows[] = $vihkoRow;
      $exportedRowCount++;
    }
    */
    if ($rowNumber >= $limit) {
      echo "Limit reached, stopped reading the file\n"; 
      break;
    }

    $rowNumber++;
  }

  fclose($handle);

  log2("END", "Ended", "log/transactionlog.log");

}
else {
  echo "Reading file failed.";
}

function setColNames($row) {
  $colNames = Array();

  foreach ($row as $i => $colName) {
    $colNames[$i] = $colName;
  }

//  print_r ($colNames); // debug
  return $colNames;
}

function validateFormat($colNames) {
  $expected[] = "gbifID";
  $expected[] = "datasetKey";
  $expected[] = "occurrenceID";
  $expected[] = "kingdom";
  $expected[] = "phylum";
  $expected[] = "class";
  $expected[] = "order";
  $expected[] = "family";
  $expected[] = "genus";
  $expected[] = "species";
  $expected[] = "infraspecificEpithet";
  $expected[] = "taxonRank";
  $expected[] = "scientificName";
  $expected[] = "verbatimScientificName";
  $expected[] = "verbatimScientificNameAuthorship";
  $expected[] = "countryCode";
  $expected[] = "locality";
  $expected[] = "stateProvince";
  $expected[] = "occurrenceStatus";
  $expected[] = "individualCount";
  $expected[] = "publishingOrgKey";
  $expected[] = "decimalLatitude";
  $expected[] = "decimalLongitude";
  $expected[] = "coordinateUncertaintyInMeters";
  $expected[] = "coordinatePrecision";
  $expected[] = "elevation";
  $expected[] = "elevationAccuracy";
  $expected[] = "depth";
  $expected[] = "depthAccuracy";
  $expected[] = "eventDate";
  $expected[] = "day";
  $expected[] = "month";
  $expected[] = "year";
  $expected[] = "taxonKey";
  $expected[] = "speciesKey";
  $expected[] = "basisOfRecord";
  $expected[] = "institutionCode";
  $expected[] = "collectionCode";
  $expected[] = "catalogNumber";
  $expected[] = "recordNumber";
  $expected[] = "identifiedBy";
  $expected[] = "dateIdentified";
  $expected[] = "license";
  $expected[] = "rightsHolder";
  $expected[] = "recordedBy";
  $expected[] = "typeStatus";
  $expected[] = "establishmentMeans";
  $expected[] = "lastInterpreted";
  $expected[] = "mediaType";
  $expected[] = "issue";

  if ($colNames === $expected) {
    log2("NOTICE", "Valid format", "log/transactionlog.log");
    return TRUE;
  }
  else {
    log2("ERROR", "Invalid format", "log/transactionlog.log");
    return FALSE;
  }
}

