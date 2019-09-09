<?php

require_once "mysql.php";

$database = new mysqlDb();
$database->connect("inat_push");
$database->doesIDExist(123);
//$database->insert(123, "hash", 456);
//print_r ($database->conn->info);
$database->close();

echo " / END";

