# FinBIF-DW
Tools for accessing Finnish Biodiversity Information Facility (FinBIF) data warehouse

## Docker

Based on https://github.com/mikkohei13/Lastuja/tree/master/devenv

## Notes

To be used on log-type database:

SELECT a.id, a.ts, a.status, a.data
FROM logtest a
INNER JOIN (
    SELECT id, MAX(ts) ts
    FROM logtest
    GROUP BY id
) b ON a.id = b.id AND a.ts = b.ts
WHERE indw = 0

Note: must mark all log entries as handled (consumed), if this info is managed in the log db. Maybe shoud not?

