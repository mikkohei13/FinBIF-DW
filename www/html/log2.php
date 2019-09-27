<?php

// Note: this is temporarily copied to mysql class.
function log2($type, $message, $filename = "log/log.txt") {
  // https://stackoverflow.com/questions/1252529/get-code-line-and-file-thats-executing-the-current-function-in-php
  $bt = debug_backtrace();
  $caller = array_shift($bt);
  // echo $caller['file'];
  // echo $caller['line'];

  $message = date("Y-m-d H:i:s") . "\t" . $type . "\t" . $caller['file'] . "\t" . $caller['line'] . "\t" . $message . "\n";

  $bytes = file_put_contents($filename, $message, FILE_APPEND);

  if ("ERROR" == $type) {
    exit("Exited through logger");
  }

  return $bytes;
}
