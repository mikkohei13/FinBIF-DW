

Transaction-log -based ETL tool from biodiversity data sources to FinBIF DW.

Optimal plan:
- Data connector extract scripts (iNat, eBird, DwC... file-based, api-based...)
- Format validator / change monitor
- Scheduler
- Tabular to JSON -converter
- Transaction log
- Atom feed of changes
- Transformer (for each data format)
- Id endpoint, which is read by DW

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

