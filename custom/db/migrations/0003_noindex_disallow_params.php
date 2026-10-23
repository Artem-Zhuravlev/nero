<?php
declare(strict_types=1);

// Migration 0003: works around an ocStore 3.0.3.7 core bug found in the first live run (SPEC-034).
//
// catalog/controller/product/{category,search,manufacturer_info,special}.php call
//   in_array('sort', $disallow_params, true)
// but only define $disallow_params when `config_noindex_disallow_params` is non-empty, so any request with
// sort/order/page/limit on a fresh install logs "Undefined variable" + an in_array() warning (a TypeError on PHP 8).
// The setting lists the GET parameters that must NOT get <meta robots noindex>; `page` stays indexable
// (paginated listings are crawlable, SPEC-013), sort/order/limit/filter stay noindex.
// Rollback: delete the setting (the core bug returns).

return function (\Ocs\Migrate\Context $m): void {
    if ($m->setting->get('config', 'config_noindex_disallow_params') === null || $m->setting->get('config', 'config_noindex_disallow_params') === '') {
        $m->setting->set('config', 'config_noindex_disallow_params', 'page');
    }
};
