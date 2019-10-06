

# Transaction-log -based ETL tool from biodiversity data sources to FinBIF DW

Prototype of an idea how to use a transaction log to manage updates, cnages and deletions.

Basic idea:

- Fetch data from the source. Separate code for different data sources & formats (file, API ...). (DONE for simple DwC file format)
- Compare hash to database. If new or updated, *insert* an entry to the transactionlog-database. This way we store all the change history in the db. (DONE)
- Insert both original data (as json) and data converted to DW-format to the db, together with timestamp. (DONE)
- Provide either push or pull interface to DW. These should only provide the latest available log entry (unless it is deletion, and the record has not been pushed to the DW yet.). SQL code for this below.
  - Pull with atom feed of changes and a single record endpoint.
  - Push with scheduling and batches. Keep track of what has been pushed.

SQL example for getting latest log entry, based on timestamp (ts):

  SELECT a.id, a.ts, a.status, a.data
  FROM logtest a
  INNER JOIN (
      SELECT id, MAX(ts) ts
      FROM logtest
      GROUP BY id
  ) b ON a.id = b.id AND a.ts = b.ts
  WHERE pushed_to_dw = 0

Note: must mark all log entries as handled (consumed), if this info is managed in the log db. Maybe shoud not?

## Plans

### Optimal plan

- Data connector extract scripts (iNat, eBird, DwC... file-based, api-based...)
- Format validator / change monitor
- Transformer (for each data format)
- Scheduler
- Tabular to JSON -converter
- Transaction log
- Either or
  - Pull: Atom feed of changes, single record endpoint
  - Push logic

### Modes

A) First upload (manual)

B) Update latest

C) Update all

D) Deletions
* Assume that so much observation data, that all id's cannot be loaded to memory
* 1 M id's take about 40 MB of memory. Memory consumption of PHP seems to increase in steps

Da) Avoid memory limit
- Download all / GET all from API as observations
- Get MAX from log as logextract (this must fit into memory, unless db API does not handle this dynamically)
- Filter out deletions from logextract (result of this must fit into memory)
- Foreach observations
  - Remove from logextract
- Foreach logextract
  - Delete from DW

Db) Memory limiting (so can handle max ~2 M records with 128 M of memory)
- Download all / GET all from API as observations
- Load all id's to memory as observations
- Get MAX from log as logextract
- Foreach logextract
  - Check if id found in observations
    - If false, delete from DW

