
Tools for ETL from iNaturalist to Laji.fi data warehouse (DW).

## Deployment

- Set limit variables in readiNat.php to production values
- Set timezone to what is used in the container/server

## Usage

Setup:

- In all cases, make sure hardcded debug settings are first removed.
- Timezone depends on server time settings. Change if needed (readINat.php).
- Upload observations using mode=manual
- Set up crontabs

Modes:

Inspect conversion of a single observation without pusging to DW:
- http://localhost/readINat.php?mode=single&key=33484833&destination=dryrun
- Only for debug

Push everything to test-DW:
- http://localhost/readINat.php?mode=manual&key=0&destination=test
- Only once

Push new observations since last update to test-DW:
- http://localhost/readINat.php?mode=newUpdate&key=0&destination=test
- Crontab 1 hour?

Push all changed observations to test-DW (e.g. monthly):
- http://localhost/readINat.php?mode=fullUpdate&key=0&destination=test
- Crontab 1 month?

Delete a single observation from test-DW:
- http://localhost/readINat.php?mode=deleteSingle&key=33484833&destination=dryrun
- Only for manual use

Params:

- MODE: single | deleteSingle | manual | newUpdate | fullUpdate
- DESTINATION: dryrun (just display) | test | prod
- KEY: id or time to begin *after*

## Notes

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

## Todo

TODO:

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


## Licenses

What to do if user changes their observation license?

A) Follow the new license, and delete the observation if new license is ARR
  - Downside: we lose observations which we legally could keep
B) If license changes towards more strict (either within CC or from CC to ARR), don't update the record
  - Downside: we don't get updates, like error corrections

- CURRENTLY: 
  - License change removes the observation from fullUpdate dataset -> will be marked for deletion. (=A)
  - Option B woud need that for each observation gone missing from the iNat API we should check whether it's found without the licence filter 

## Data quality fields

out_of_range (true | false | empty): is the observation outside "known range" of the taxon

- ideally would create an annotation needsChecking

quality_grade

- research = community convincing, has media
- needs id = neutral grade, has media
- casual = neutral grade, no media

## Observations in iNaturalist

Observations, all grades (25.8.2019)

- Finland 18722 (c. 5% marked as captive/cultivated - do we want these also? No.)
- Estonia 3425
- Sweden 35828
- Norway 30872
- Russia (are these areas overlapping?)
  - Republic of Karelia 6587 (bounding box, includes some of Finland also)
  - Murmansk Oblast 1223
  - Leningrad Oblast 10641 (bounding box) 
- Total c. 107,000

Finland 18976 obs

Photos, Finland, through UI (filter out spam & non-verifiable obs)

- Total 18976
- CC0 196
- CC-BY 843
- CC-BY-NC 12383
- CC-BY-SA 8
- CC-BY-ND 0
- CC-BY-NC-SA 602
- CC-BY-NC-ND 28
- no license (calculated from total) 4916 (26%)

Observations, Finland, through API

- Total 20205
- CC0 730
- CC-BY 600
- CC-BY-NC 12525
- CC-BY-SA 4
- CC-BY-ND 0
- CC-BY-NC-SA 595
- CC-BY-NC-ND 30
- no license (calculated from total) 5721 (28%)


## Feedback about iNat API

Or is this information available somewhere?

Describe meaning and possible values of the different fields. Things like:

- What are the meaning of different fields? (e.g. species_guess is the verbatim taxon (and not always a species), taxon->name is the interpreted/community id)
- How fields are usually used? (e.g. quality metrics are usually used to mark observations as suspicious, but not as confirmed)
- What languages fields can contain? (e.g. species_quess seems to contain text in many different languages, description can contain anything the user writes)
- Which fields can contain HTML tags? (e.g. description)
- Which fields can be empty or null, and when?
- Which fields are deprecated and/or cannot be used anymore (id_please seems to be always false, is this feature removed?)
- Which kind of special cases should be prepared for? (e.g. what if observer uses a placeholder name, external name provider, or leaves the taxon unidentified?)


Also examples of some complicated observations would be helpful, e.g. with multiple photos and sound files, conflicting identifications, observation fields, quality metrics, tags, annotations, flags, traditional projects, non-traditional projects, faves etc...


## Notes about the iNaturalist API

There are two API's:

### Write-Read API

https://www.inaturalist.org/pages/api+reference

- More functionality, used internally
- Here's instructions how to get OAuth access token
- Ruby on Rails

### Read-only API v1

https://api.inaturalist.org/v1/docs/

- Read-only observations and stats, recommended for this
- Faster, more consistent responses
- Node
- Max 60 (100) requests / minute, under 10,000 requests / day

Multiple values for a single URL parameter should be separated by commas, e.g. taxon_id=1,2,3.

Authentication in the Node API is handled via JSON Web Tokens (JWT). To obtain one, make an OAuth-authenticated request to https://www.inaturalist.org/users/api_token. Each JWT will expire after 24 hours. Authentication required for all PUT and POST requests. Some GET requests will also include private information like hidden coordinates if the authenticated user has permission to view them.

API is intended to support application development, not data scraping.

https://api.inaturalist.org/v1/docs/#!/Observations/get_observations

### Misc notes

- Data export tool: https://www.inaturalist.org/observations/export
   - filters: quality_grade=any&identifications=any&captive=false&place_id=118841
- Annotated photos (5,089 species, 675,000 images) for computer vision training etc.: https://www.kaggle.com/c/inaturalist-challenge-at-fgvc-2017
- DwCA (400+ MB) of research-grade observations, updated weekly http://www.inaturalist.org/observations/gbif-observations-dwca.zip

Finland place_id 7020

API does not seem to allow sorting based on when observation was modified. Therefore cannot go through the observations in order. Therefore must handle all observations since last fetch in a single transaction.

API allows fetching observations which have been updated since timestamp. However, what is counted as an update?

- Edit obs details - YES
- Adding YES or removing id YES
- Adding YES or removing annotations YES
- Upvoting NO!!! or downvoting quality grade annotations
  -> If this is true, we must re-index everything once in a while, to update quality_metrics




