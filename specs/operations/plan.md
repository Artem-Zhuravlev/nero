# PLAN — SPEC-014 Operations

## Approach

Host-agnostic CLI tools in `scripts/` plus one small extension `ocs_ops` (health endpoint, Logger,
debug event). Jobs are data (`jobs.json`) run by one locking runner so any scheduler can drive them.
Runbooks are tested in `local` before being declared done.

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| Dump tool | PHP script using store DB class | no `mysqldump` dependency on the host |
| Scheduling | external scheduler → single runner | core has no cron |
| Mail capture | local SMTP sink | no core `Mail` replacement |
| Cache engine | stay `file` | changing it means patching core config |
| Monitoring contract | health endpoint + log watch | vendor-neutral |
| PII handling | declared in `extension.json` | verifiable per extension |

## Affected files

`scripts/{backup,restore,rotate-logs,log-watch,jobs-run,jobs-crontab,cache-clear,maintenance,dsr,mail-sink}.*`,
`scripts/db-dump.php`, `scripts/db-restore.php`, `custom/ops/jobs.json`, `custom/extensions/ocs_ops/**`,
`docs/runbooks/*`, `docs/retention.md`.

## Risks

- Lock-based consistent dumps may stall writes on big tables -> chunked groups, off-peak schedule.
- PII declaration discipline relies on verify (SPEC-004 `personalData` check, to be added).
