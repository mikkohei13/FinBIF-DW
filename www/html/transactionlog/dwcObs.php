<?php
  /*
  convert to dw json
  check if hash found in db
    insert to db
  */


class dwcObs
{
  // todo: dw -> target

  // todo: private?
  public $row;
  public $colNames;
  public $startTime;
  public $database;

  private $sourceObs;
  private $dwJson;
  private $sourceJson;
  private $hash;
  private $sourceObsId;
  private $targetObsId;

  public function __construct($row, $colNames, $startTime, $database) {
    // Set variables
    $this->row = $row;
    $this->colNames = $colNames;
    $this->startTime = $startTime;
    $this->database = $database;

    // Todo: move these to separate method?
    $this->createSourceObs();
    $this->createDwJson();
    $this->createHash();

    // todo: database method "pushIfNewObservation"
//    $debug = rand(0, 10000); // debug, remove from below also, after the ->hash

//    print_r ($this->sourceObs); // debug
//    print_r ($this->dwJson); // debug

    $targetObsInDatabase = $this->database->obsInDatabase($this->targetObsId, $this->hash);
    if ($targetObsInDatabase) {
      log2("NOTICE", "Skipped " . $this->targetObsId, "log/transactionlog.log");
    }
    else {
      $this->createSourceJson();

      // todo: use array instead of list of vars?
      $this->database->insertObs(
        $this->sourceObsId,
        $this->targetObsId,
        $this->sourceJson,
        $this->dwJson,
        "DEBUG",
        $this->startTime,
        $this->hash
      );

      log2("NOTICE", "Inserted " . $this->targetObsId, "log/transactionlog.log");
    }
  }



  private function createSourceJson() {
    $this->sourceJson = json_encode($this->sourceObs);
  }

  private function createSourceObs() {
    foreach ($this->colNames as $nro => $name) {
      $this->sourceObs[$name] = $this->row[$nro];
    }
  }

  private function createHash() {
    $this->hash = sha1($this->dwJson);
  }

  // The conversion script
  private function createDwJson() {

    $TODO = "TODO"; // todo

    $documentId = $TODO . "/" . $this->sourceObs['occurrenceID']; // http://tun.fi/HR.3211

    $targetObs['collectionId'] = "TODO"; // http://tun.fi/HR.3211
    $targetObs['secureLevel'] = "NONE";
    $targetObs['concealment'] = "PUBLIC";
    $targetObs['documentId'] = $documentId;
    $targetObs['createdDate'] = $this->sourceObs['lastInterpreted']; // todo: is there better field?
    $targetObs['modifiedDate'] = $this->sourceObs['lastInterpreted'];
    $targetObs['licenseId'] = $this->sourceObs['license']; // "http://tun.fi/MZ.intellectualRightsCC-BY-NC-4.0"
    $targetObs['editorUserIds'][0] = "KE.901:" . $TODO; // esko: should this be based on collection? if we change the system which uploads or if same system uploads data from several collections?
      // ebird: recordedBy
    
    print_r ($this->dwJson);

    $this->sourceObsId = $this->sourceObs['occurrenceID'];
    $this->targetObsId = $documentId;
    $this->dwJson = json_encode($targetObs);
  }


}