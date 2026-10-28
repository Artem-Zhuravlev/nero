<?php
declare(strict_types=1);

// ocs_blog 001: authors, article and comment satellites, votes, permissions, events and the article form OCMOD (SPEC-084).
// Rollback: unregister the events; the tables are kept (REQ-EXT-052).

return function (\Ocs\Migrate\Context $m): void {
    $m->createTable('ocs_blog_author', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_blog_author (
        author_id INT NOT NULL AUTO_INCREMENT,
        image VARCHAR(255) NOT NULL DEFAULT '',
        social TEXT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        status TINYINT NOT NULL DEFAULT 1,
        PRIMARY KEY (author_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->createTable('ocs_blog_author_description', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_blog_author_description (
        author_id INT NOT NULL,
        language_id INT NOT NULL,
        name VARCHAR(128) NOT NULL DEFAULT '',
        position VARCHAR(128) NOT NULL DEFAULT '',
        description TEXT NOT NULL,
        PRIMARY KEY (author_id, language_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->createTable('ocs_blog_article', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_blog_article (
        article_id INT NOT NULL,
        author_id INT NOT NULL DEFAULT 0,
        likes INT NOT NULL DEFAULT 0,
        dislikes INT NOT NULL DEFAULT 0,
        PRIMARY KEY (article_id),
        KEY author_id (author_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->createTable('ocs_blog_comment', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_blog_comment (
        review_article_id INT NOT NULL,
        likes INT NOT NULL DEFAULT 0,
        dislikes INT NOT NULL DEFAULT 0,
        PRIMARY KEY (review_article_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->createTable('ocs_blog_vote', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_blog_vote (
        target_type VARCHAR(8) NOT NULL,
        target_id INT NOT NULL,
        voter_key VARCHAR(32) NOT NULL,
        value TINYINT NOT NULL,
        date_added DATETIME NOT NULL,
        PRIMARY KEY (target_type, target_id, voter_key),
        KEY voter_time (voter_key, date_added)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->permission->grant(1, 'extension/ocs_blog/author');

    $events = [
        'ocs_blog_menu' => ['admin/view/common/column_left/before', 'extension/ocs_blog/events/beforeMenu'],
        'ocs_blog_article_form' => ['admin/view/blog/article_form/before', 'extension/ocs_blog/events/beforeArticleForm'],
        'ocs_blog_article_add' => ['admin/model/blog/article/addArticle/after', 'extension/ocs_blog/events/afterAddArticle'],
        'ocs_blog_article_edit' => ['admin/model/blog/article/editArticle/after', 'extension/ocs_blog/events/afterEditArticle'],
        'ocs_blog_article_delete' => ['admin/model/blog/article/deleteArticle/before', 'extension/ocs_blog/events/beforeDeleteArticle'],
        'ocs_blog_review_delete' => ['admin/model/blog/review/deleteReview/before', 'extension/ocs_blog/events/beforeDeleteReview'],
    ];
    foreach ($events as $code => [$trigger, $action]) {
        $m->event->register($code, $trigger, $action, 5);
    }

    $m->ocmod->install('custom/ocmod/ocs_blog_article_form.ocmod.xml');
};
