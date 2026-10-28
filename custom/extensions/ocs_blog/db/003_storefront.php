<?php
declare(strict_types=1);

// ocs_blog 003: storefront view events: article page (author, date, reactions, comments), comment list, article listings and blog modules (SPEC-084).
// Rollback: unregister the events; templates fall back to the missing-variable defaults.

return function (\Ocs\Migrate\Context $m): void {
    $events = [
        'ocs_blog_view_article' => ['catalog/view/blog/article/before', 'extension/ocs_blog/events/viewArticle'],
        'ocs_blog_view_review' => ['catalog/view/blog/review/before', 'extension/ocs_blog/events/viewReview'],
        'ocs_blog_view_latest' => ['catalog/view/blog/latest/before', 'extension/ocs_blog/events/viewList'],
        'ocs_blog_view_category' => ['catalog/view/blog/category/before', 'extension/ocs_blog/events/viewList'],
        'ocs_blog_view_module_latest' => ['catalog/view/extension/module/blog_latest/before', 'extension/ocs_blog/events/viewList'],
        'ocs_blog_view_module_featured' => ['catalog/view/extension/module/blog_featured/before', 'extension/ocs_blog/events/viewList'],
    ];
    foreach ($events as $code => [$trigger, $action]) {
        $m->event->register($code, $trigger, $action, 5);
    }
};
