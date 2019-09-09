<?php

function log2($type, $message) {
  $filename = "log/log.txt";

  $message = date("Y-m-d H:i:s") . "\t" . $type . "\t" . $message . "\n";

  $bytes = file_put_contents($filename, $message, FILE_APPEND);
  return $bytes;
}
