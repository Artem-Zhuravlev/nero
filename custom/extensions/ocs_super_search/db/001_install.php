<?php
declare(strict_types=1);

// ocs_super_search 001: token index and vocabulary tables, module registration with status 0 (REQ-EXT-031), settings, events (SPEC-079).
// Only new tables: no core table is altered (REQ-DB-030). The index is empty until "Rebuild index" (admin) or scripts/search-reindex.php runs;
// until then (built_at empty) the catalog events do nothing and core search stays in charge.
// Rollback: set module_ocs_super_search_status to 0; the core search works as before. The tables are left alone (REQ-EXT-052).

return function (\Ocs\Migrate\Context $m): void {
    $m->createTable('ocs_search_token', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_search_token (
        token VARCHAR(48) NOT NULL,
        product_id INT NOT NULL,
        weight TINYINT NOT NULL,
        PRIMARY KEY (token, product_id),
        KEY product_id (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->createTable('ocs_search_vocab', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_search_vocab (
        term VARCHAR(48) NOT NULL,
        phon VARCHAR(48) NOT NULL,
        len TINYINT NOT NULL,
        df INT NOT NULL,
        PRIMARY KEY (term),
        KEY phon (phon),
        KEY len_df (len, df)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->extension->register('module', 'ocs_super_search');
    $m->permission->grant(1, 'extension/module/ocs_super_search');

    $defaults = [
        'module_ocs_super_search_status' => 0,
        'module_ocs_super_search_layout' => 1,
        'module_ocs_super_search_translit' => 1,
        'module_ocs_super_search_typo' => 1,
        'module_ocs_super_search_min_length' => 2,
        'module_ocs_super_search_cache_ttl' => 300,
        'module_ocs_super_search_synonyms' => '',
        'module_ocs_super_search_generation' => '0',
        'module_ocs_super_search_built_at' => '',
    ];
    foreach ($defaults as $key => $value) {
        if ($m->setting->get('module_ocs_super_search', $key) === null) {
            $m->setting->set('module_ocs_super_search', $key, $value);
        }
    }

    // storefront: results come from the index when the module is on and built
    $m->event->register('ocs_super_search_products', 'catalog/model/catalog/product/getProducts/before', 'extension/ocs_super_search/events/beforeGetProducts', 1);
    $m->event->register('ocs_super_search_total', 'catalog/model/catalog/product/getTotalProducts/before', 'extension/ocs_super_search/events/beforeGetTotalProducts', 1);
    // admin: the index follows product saves
    $m->event->register('ocs_super_search_product_add', 'admin/model/catalog/product/addProduct/after', 'extension/ocs_super_search/events/afterProductAdd', 1);
    $m->event->register('ocs_super_search_product_edit', 'admin/model/catalog/product/editProduct/after', 'extension/ocs_super_search/events/afterProductEdit', 1);
    $m->event->register('ocs_super_search_product_delete', 'admin/model/catalog/product/deleteProduct/after', 'extension/ocs_super_search/events/afterProductDelete', 1);
};
