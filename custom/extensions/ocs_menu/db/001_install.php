<?php
declare(strict_types=1);

// ocs_menu 001: table with per-category menu extras (SPEC-027 Data requirements). Safe to re-run (IF NOT EXISTS).

return function (\Ocs\Migrate\Context $m): void {
    $m->createTable('ocs_menu_category', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_menu_category (
        category_id INT NOT NULL,
        icon VARCHAR(128) NOT NULL DEFAULT '',
        popular TINYINT NOT NULL DEFAULT 0,
        banner_image VARCHAR(255) NOT NULL DEFAULT '',
        banner_url VARCHAR(255) NOT NULL DEFAULT '',
        PRIMARY KEY (category_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
};
