<?php

require_once "mysql.php";

$database = new mysqlDb();
$database->connect("inat_push");

//$database->doesIDExist(1234);

$database->push(3, "hash3", 0);

$database->set_latest_update();
echo $database->get_latest_update();


//print_r ($database->conn->info);
$database->close();

echo " / END";

