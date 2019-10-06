<?php

class dwcObs
{
  public $row = NULL;
  public $colNames = NULL;

  public function __construct($row, $colNames) {
      $this->row = $row;
      $this->colNames = $colNames;

  }

}