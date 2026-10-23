<?php
declare(strict_types=1);

// ocs_newsletter 001: subscriber table, module registration with status 0 (REQ-EXT-031) (SPEC-033 REQ-HOM-030).
// Rollback: set module_ocs_newsletter_status to 0; the list is kept (REQ-EXT-052).

return function (\Ocs\Migrate\Context $m): void {
    $m->createTable('ocs_subscriber', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_subscriber (
        subscriber_id INT NOT NULL AUTO_INCREMENT,
        email VARCHAR(96) NOT NULL,
        language_id INT NOT NULL DEFAULT 0,
        confirmed TINYINT NOT NULL DEFAULT 0,
        token VARCHAR(32) NOT NULL DEFAULT '',
        ip VARCHAR(40) NOT NULL DEFAULT '',
        date_added DATETIME NOT NULL,
        PRIMARY KEY (subscriber_id),
        UNIQUE KEY email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->extension->register('module', 'ocs_newsletter');
    $m->permission->grant(1, 'extension/module/ocs_newsletter');

    $defaults = [
        'module_ocs_newsletter_status' => 0,
        'module_ocs_newsletter_double_optin' => 0,
        'module_ocs_newsletter_l' => [],
    ];
    foreach ($defaults as $key => $value) {
        if ($m->setting->get('module_ocs_newsletter', $key) === null) {
            $m->setting->set('module_ocs_newsletter', $key, $value);
        }
    }
};
