<?php
  /*
  create dwc json
  chekc if hash found in db
    convert to dw json
    insert to db
  */


class dwcObs
{
  // todo: private?
  public $row;
  public $colNames;
  public $startTime;
  public $database;

  private $sourceJson;
  private $dwJson;

  public function __construct($row, $colNames, $startTime, $database) {
    // Set variables
    $this->row = $row;
    $this->colNames = $colNames;
    $this->startTime = $startTime;
    $this->database = $database;

    $this->createSourceJson();
  }

  private function createSourceJson() {
    foreach ($this->colNames as $nro => $name) {
      $this->sourceJson[$name] = $this->row[$nro];
    }
    print_r ($this->sourceJson);
  }


  private function createDwJson() {

  }


}