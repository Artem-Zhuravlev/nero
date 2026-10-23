<?php
declare(strict_types=1);

// ocs_home_slider 001: slide tables, module registration with status 0 (REQ-EXT-031) (SPEC-033 REQ-HOM-001).
// Rollback: set module_ocs_home_slider_status to 0; data is kept (REQ-EXT-052).

return function (\Ocs\Migrate\Context $m): void {
    $m->createTable('ocs_slide', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_slide (
        slide_id INT NOT NULL AUTO_INCREMENT,
        image VARCHAR(255) NOT NULL DEFAULT '',
        image_mobile VARCHAR(255) NOT NULL DEFAULT '',
        link VARCHAR(255) NOT NULL DEFAULT '',
        sort_order INT NOT NULL DEFAULT 0,
        status TINYINT NOT NULL DEFAULT 1,
        date_from VARCHAR(10) NOT NULL DEFAULT '',
        date_to VARCHAR(10) NOT NULL DEFAULT '',
        PRIMARY KEY (slide_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->createTable('ocs_slide_description', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_slide_description (
        slide_id INT NOT NULL,
        language_id INT NOT NULL,
        title VARCHAR(128) NOT NULL DEFAULT '',
        text VARCHAR(255) NOT NULL DEFAULT '',
        PRIMARY KEY (slide_id, language_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->extension->register('module', 'ocs_home_slider');
    $m->permission->grant(1, 'extension/module/ocs_home_slider');

    $defaults = [
        'module_ocs_home_slider_status' => 0,
        'module_ocs_home_slider_autoplay' => 6000,
        'module_ocs_home_slider_width' => 1280,
        'module_ocs_home_slider_height' => 480,
        'module_ocs_home_slider_mobile_width' => 640,
        'module_ocs_home_slider_mobile_height' => 480,
    ];
    foreach ($defaults as $key => $value) {
        if ($m->setting->get('module_ocs_home_slider', $key) === null) {
            $m->setting->set('module_ocs_home_slider', $key, $value);
        }
    }
};
