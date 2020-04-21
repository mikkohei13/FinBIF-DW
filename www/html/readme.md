
Tools for ETL from iNaturalist to Laji.fi data warehouse (DW).

## Deployment

- Set limit variables in readiNat.php to production values
- Set timezone to what is used in the container/server

## Usage

Setup:

- In all cases, make sure hardcoded debug settings are first removed.
- Timezone depends on server time settings. Change if needed (readINat.php).
- Upload observations using mode=manual
- Set up crontabs


# How this works?

readInt.php
- gets params
- orverall process handling

inatHelpers.php
- Varous conversion helper functions

log2.php
- Logger function

inat2dw.php
- Converts an observation from iNat API format to FinBIF API format, returns it as [json?]

mysql.php
- Logs handled observations and last fetching date to database

_secrets.php

postToAPI.php
- Posts json data to FinBIF API using CURL



## Pushing logic

FRESH PUSH
- Fetch obs from API, with params (Finland, CC-licensed, wild) and sorted by id ascending
- Foreach obs
  - Calculate hash
  - Convert to DW format
  - Push to DW
  - Push to db (id, hash, timestamp), making an insert or update as needed [ready]

DAILY UPDATE
- Fetch obs updated since update timg read from db
- Foreach obs as above
- Save update time to db [ready]

MONTHLY UPDATE
* This can be run monthly or as needed
* Purpose is to handle:
  * Deletions
  * Changed licenses
  * Changed quality_metrics

- Fetch all obs from API
- Foreach obs
  - Calculate hash
  - Check if id-hash -pair found in db [ready]
    - If found
      - Push to db (status = 1), to mark that has been handled [ready]
    - If not found
      - Convert to DW format
      - Push to DW
      - Push to db (status = 1), to mark that has been handled [ready]

MONTHLY DELETION
- Get from database where status != 1. These are those that were nt handeld in monthly update because they were deleted from iNat [TODO]
- Foreach
  - Delete from DW
  - Delete from database (to prevent unneeded deletions later) [TODO] (Or set status = -1 and change all other methods accordingly)


Database method return values
- Failure: NULL, error on message variable
- Success: something else, can be arr, string or FALSE

Todo better: try/catch [TODO]


How to handle problems during the process?

FRESH
- If fails, stop the process
- Restart manually, by giving the largest pushed id (this can be found rom the database), or simply by restarting the process

DAILY
- If fails, stop the process, without saving the updated time to file
- Next day, start over, thus handling n+1 days. This means that some of the observations will be updated twice, but this is unavoidable, since we cannot sort API results by modified date.

MONTHLY UPDATE
- If fails, stop the process
- Restart manually, from the beginning. We cannot start from the last pushed id, sincebecause then we would skip earlier id's, even if they have been updated.

MONTHLY DELETION
- If fails, stop the process
- Restart manually, from the beginning


# Notes

Error handling:

- If error happens, log the error, which also exits the script
- Note: having no observations to submit is not an error, because processing must continue from the next page.

Test values:

- normal observation 33084315; (Violettiseitikki submitted on 20.9.2019)
- deleted observation 33586301 (Hypoxylaceae observed & submitted 29.9.2019)
- without date & taxon: 30092946

Problems when downloading full set to test-DW (1.10.2019)
- iNat API did not respond in 30 sec -> timeout with PHP -> fatal error (?)
- Temp error in DNS resolution of the iNat API -> script error & exit

# Todo

- Convert to a terminal command? Set modes to _params.php
- include copyrighted observations, but without images
- Include own observations
- Include image links for cc-images via proxy
- Use new quality fields
- Check user linking
- Map vague names (Taraxacum officinale, sananjalka ... check Raino's message)
- Compare Finnish names to Laji.fi names
  - Compare iNat Finnish names to Laji.fi Finnish names, by connecting them with sciname
  - How many of Finnish species are found in the taxonony, and how many of them have names?


- Quality metrics & quality grade (casual, research) affecting quality fields in DW
- Add check: $database->set_latest_update expects that there is entry with id = 1, and silently fails if there is not.
- Monitor the process:
  - Monitor errors on log
  - Monitor observations waiting for deletion in database (status = 2)
  - Decide how to continue (possible problems, amount of logs, how much observations to be deleted?)


Possibly todo later:

- Check:
  - Find out why more entries in database than in DW? (4 missing from database compared to DW)
  - Check if traditional and collection projects can have duplicate id's? Low chance of collision with Finnish observations?

- Content:
  - Create a FInBIF project on iNat, ask to share observations to that project. Then authenticate when fetching the data, using a user that has adming rights to the project. This way we could get exact coordinates of obscured observations. Then these must be secured on the FinBIF API also.
  - Locality names from place_ids. Probably too much hassle.
  - Remove suspended user's observations
  - Orcid
  - Download cc-licenced observation photos of research_grade observations (with no disagreeing identifications) 
  - Observation time (hh:mm), though this can be unreliable? (Android app inserts upload time, at least in some cases?)

- Code:
  - For monthly update, go through the db and get observations from iNat api by their id's
    - Fetching 100 obs by id's: 16 seconds
    - Note that /observations/{id} endpoint returns different format than /observations -> need to have different conversion script and different hashing script
  - More thorough way to check which params are used/allowed
  - Catch conversion warnings etc. and stop processing (or at least log the problem)
  - Human filtering via API: Add to all get url's "&without_taxon_id=43584" [human id], then remove human filter from inat2dw, then test.


