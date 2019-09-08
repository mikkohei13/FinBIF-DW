<?php

require_once "mysql.php";

$database = new mysqlDb();
$database->connect();
$database->insert();
//print_r ($database->conn->info);
$database->close();

echo " / END";

