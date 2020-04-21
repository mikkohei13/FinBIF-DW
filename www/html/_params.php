<?php

/*

THIS WILL BE USED IF THE SCRIPT IS CONVERTED TO A TERMINAL COMMAND WITHOUT PARAMS.

Params:

- MODE: single | deleteSingle | manual | newUpdate | fullUpdate
- DESTINATION: dryrun (just display) | test | prod
- KEY: id or time to begin *after*

*/

// ----------- DRY -----------

// Inspect conversion of a single observation without pushing to DW
// For debugging single observation
$mode = "single";
$key = 33484833;
$destination = "dryrun";


// ----------- TEST -----------

// Push everything to test-DW
// Do only when all observations need to be (re)loaded to DW
$mode = "manual";
$key = 0;
$destination = "test";

// Push new observations since last update to test-DW:
// Run automatically ~daily or hourly
$mode = "newUpdate";
$key = 0; // has no effect
$destination = "test";

// Push all changed observations to test-DW, using the database (UNTESTED!):
// Run manually e.g. monthly
$mode = "fullUpdate";
$key = 0;
$destination = "test";

// Delete a single observation from test-DW:
// Run manually when needed
$mode = "deleteSingle";
$key = 33484833;
$destination = "test";


// ----------- PRODUCTION -----------

// Push new observations since last update to PRODUCTION-DW:
$mode = "newUpdate";
$key = 0; // has no effect
$destination = "production";
