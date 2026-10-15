# Architecture overview

Short map; the binding text is [SPEC-001](../specs/project-architecture/spec.md).

```
 core/upload (vanilla ocStore 3.0.3.7, read-only)
        +
 custom/ { overlay, theme/ocs, extensions/ocs_*, ocmod, config tpl }      -- scripts/build.mjs -->  build/www
        +
 .env (APP_URL, DB_*, ADMIN_DIR, STORAGE_DIR)
                                                    custom/db/migrations + seed -- migrate.php --> MariaDB
                                                    var/storage (outside docroot): cache, logs, session, uploads
```

Core version recorded here: **ocStore 3.0.3.7 build 0002 (UK-EN-RU)**.

Mechanism order: settings > theme > extension > event > OCMOD. Core edits are forbidden.
