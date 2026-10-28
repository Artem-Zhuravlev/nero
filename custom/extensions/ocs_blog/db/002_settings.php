<?php
declare(strict_types=1);

// ocs_blog 002: multilingual blog settings (SPEC-084 REQ-BLG-020..022): old single configblog_* values are copied to every language
// once into configblog_description; admin and storefront events and the settings form OCMOD.
// Rollback: unregister the events; the old configblog_* keys are never removed.

return function (\Ocs\Migrate\Context $m): void {
    if ($m->setting->get('configblog', 'configblog_description') === null) {
        $legacy = [];
        foreach (['name', 'html_h1', 'meta_title', 'meta_description', 'meta_keyword'] as $key) {
            $legacy[$key] = (string)($m->setting->get('configblog', 'configblog_' . $key) ?? '');
        }

        $all = [];
        foreach ($m->rows('SELECT language_id FROM {{PREFIX}}language') as $row) {
            $all[(int)$row['language_id']] = $legacy;
        }

        $m->setting->set('configblog', 'configblog_description', json_encode($all, JSON_UNESCAPED_UNICODE));
    }

    $events = [
        'ocs_blog_setting_form' => ['admin/view/blog/setting/before', 'extension/ocs_blog/events/beforeSettingForm'],
        'ocs_blog_setting_save' => ['admin/model/setting/setting/editSetting/before', 'extension/ocs_blog/events/beforeEditSetting'],
        'ocs_blog_localize_latest' => ['catalog/controller/blog/latest/before', 'extension/ocs_blog/events/localize'],
        'ocs_blog_localize_category' => ['catalog/controller/blog/category/before', 'extension/ocs_blog/events/localize'],
        'ocs_blog_localize_article' => ['catalog/controller/blog/article/before', 'extension/ocs_blog/events/localize'],
        'ocs_blog_localize_menu' => ['catalog/controller/blog/menu/before', 'extension/ocs_blog/events/localize'],
    ];
    foreach ($events as $code => [$trigger, $action]) {
        $m->event->register($code, $trigger, $action, 5);
    }

    $m->ocmod->install('custom/ocmod/ocs_blog_setting.ocmod.xml');
};
