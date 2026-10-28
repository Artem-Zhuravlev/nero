<?php
// Extension: ocs_blog. Storefront events: per-language blog settings, author / date / reactions of articles, comment ids and reactions,
// author and counters in article listings and blog modules. Owner: SPEC-084.
// Handlers never throw to the customer (REQ-EXT-062).

class ControllerExtensionOcsBlogEvents extends Controller {
	/**
	 * catalog/controller/blog/{latest,category,article,menu}/before: the core controllers read configblog_name, _html_h1 and _meta_*
	 * from the config, so the language values are written there before they run (REQ-BLG-022).
	 */
	public function localize(&$route, &$args) {
		if ($this->config->get('config_theme') != 'ocs' || !$this->config->get('configblog_description')) {
			return;
		}

		try {
			$legacy = array();

			foreach (array('name', 'html_h1', 'meta_title', 'meta_description', 'meta_keyword') as $key) {
				$legacy[$key] = (string)$this->config->get('configblog_' . $key);
			}

			$values = \Ocs\Blog::langSettings($this->config->get('configblog_description'), (int)$this->config->get('config_language_id'), (int)$this->config->get('config_language_id'), $legacy);

			foreach ($values as $key => $value) {
				$this->config->set('configblog_' . $key, $value);
			}
		} catch (\Exception $e) {
			$this->log->write('[ocs_blog] ' . $e->getMessage());
		}
	}

	/**
	 * catalog/view/blog/article/before: author block, date, reactions, strings for the comment popup (REQ-BLG-004, 011, 034, 040).
	 */
	public function viewArticle(&$route, &$data) {
		if (!is_array($data) || empty($data['article_id']) || $this->config->get('config_theme') != 'ocs') {
			return;
		}

		try {
			$strings = $this->load->language('extension/ocs_blog/blog');
			$strings = is_array($strings) ? $strings : array();

			$this->load->model('extension/ocs_blog/blog');
			$this->load->model('tool/image');

			$model = $this->model_extension_ocs_blog_blog;
			$id = (int)$data['article_id'];

			$authors = $model->getAuthors(array($id));
			$meta = $model->getArticleMeta(array($id));
			$meta = isset($meta[$id]) ? $meta[$id] : array('date' => '', 'likes' => 0, 'dislikes' => 0);
			$mine = $model->getMyVotes('article', array($id), $model->voterKey());

			$view = array(
				'article_id' => $id,
				'vote_url'   => $this->url->link('extension/ocs_blog/vote', '', true),
				'likes'      => $meta['likes'],
				'dislikes'   => $meta['dislikes'],
				'mine'       => isset($mine[$id]) ? $mine[$id] : 0,
				'date'       => $meta['date'] ? date($this->language->get('date_format_short'), strtotime($meta['date'])) : '',
				'date_iso'   => $meta['date'] ? date('Y-m-d', strtotime($meta['date'])) : '',
				'author'     => isset($authors[$id]) ? $this->authorView($authors[$id], 96) : null,
				't'          => array()
			);

			foreach ($strings as $key => $value) {
				$view['t'][$key] = $value;
			}

			$data['ocs_blog'] = $view;
		} catch (\Exception $e) {
			$this->log->write('[ocs_blog] ' . $e->getMessage());
		}
	}

	/**
	 * catalog/view/blog/review/before: the core list has no comment ids; the same page is read again to attach them and the reactions (REQ-BLG-032).
	 */
	public function viewReview(&$route, &$data) {
		if (!is_array($data) || empty($data['reviews']) || empty($this->request->get['article_id']) || $this->config->get('config_theme') != 'ocs') {
			return;
		}

		try {
			$this->load->language('extension/ocs_blog/blog');
			$this->load->model('blog/review');
			$this->load->model('extension/ocs_blog/blog');

			$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;
			$rows = array_values($this->model_blog_review->getReviewsByArticleId((int)$this->request->get['article_id'], ($page - 1) * 5, 5));
			$ids = array_map('intval', array_column($rows, 'review_article_id'));

			$counts = $this->model_extension_ocs_blog_blog->getCommentCounts($ids);
			$mine = $this->model_extension_ocs_blog_blog->getMyVotes('comment', $ids, $this->model_extension_ocs_blog_blog->voterKey());

			foreach ($data['reviews'] as $i => $review) {
				if (!isset($rows[$i])) {
					continue;
				}

				$id = (int)$rows[$i]['review_article_id'];

				$data['reviews'][$i]['id'] = $id;
				$data['reviews'][$i]['author'] = htmlspecialchars($review['author'], ENT_QUOTES, 'UTF-8');
				$data['reviews'][$i]['text'] = nl2br(htmlspecialchars($review['text'], ENT_QUOTES, 'UTF-8'));
				$data['reviews'][$i]['likes'] = isset($counts[$id]) ? $counts[$id]['likes'] : 0;
				$data['reviews'][$i]['dislikes'] = isset($counts[$id]) ? $counts[$id]['dislikes'] : 0;
				$data['reviews'][$i]['mine'] = isset($mine[$id]) ? $mine[$id] : 0;
			}

			$data['ocs_vote_url'] = $this->url->link('extension/ocs_blog/vote', '', true);
			$data['text_like'] = $this->language->get('text_like');
			$data['text_dislike'] = $this->language->get('text_dislike');
		} catch (\Exception $e) {
			$this->log->write('[ocs_blog] ' . $e->getMessage());
		}
	}

	/**
	 * blog/latest, blog/category and the blog modules: article rows get ocs_author, ocs_date and ocs_likes in one batch (REQ-BLG-050, 062).
	 */
	public function viewList(&$route, &$data) {
		if (!is_array($data) || empty($data['articles']) || !is_array($data['articles']) || $this->config->get('config_theme') != 'ocs') {
			return;
		}

		try {
			$ids = array();

			foreach ($data['articles'] as $i => $article) {
				$id = isset($article['article_id']) ? (int)$article['article_id'] : (isset($article['href']) && preg_match('/article_id=(\d+)/', $article['href'], $m) ? (int)$m[1] : 0);

				$data['articles'][$i]['ocs_id'] = $id;
				$ids[] = $id;
			}

			$this->load->model('extension/ocs_blog/blog');
			$this->load->model('tool/image');
			$this->load->language('extension/ocs_blog/blog');

			$authors = $this->model_extension_ocs_blog_blog->getAuthors($ids);
			$meta = $this->model_extension_ocs_blog_blog->getArticleMeta($ids);

			foreach ($data['articles'] as $i => $article) {
				$id = $article['ocs_id'];

				$data['articles'][$i]['ocs_author'] = isset($authors[$id]) ? $this->authorView($authors[$id], 40) : null;
				$data['articles'][$i]['ocs_date'] = isset($meta[$id]) && $meta[$id]['date'] ? date($this->language->get('date_format_short'), strtotime($meta[$id]['date'])) : (isset($article['date_added']) ? $article['date_added'] : '');
				$data['articles'][$i]['ocs_date_iso'] = isset($meta[$id]) && $meta[$id]['date'] ? date('Y-m-d', strtotime($meta[$id]['date'])) : '';
				$data['articles'][$i]['ocs_likes'] = isset($meta[$id]) ? $meta[$id]['likes'] : 0;
				$data['articles'][$i]['ocs_name'] = htmlspecialchars(isset($article['name']) ? $article['name'] : '', ENT_QUOTES, 'UTF-8');
			}

			$data['text_read_more'] = $this->language->get('text_read_more');
		} catch (\Exception $e) {
			$this->log->write('[ocs_blog] ' . $e->getMessage());
		}
	}

	/**
	 * Escaped author view for templates: name, position, description, avatar and social icons with the network key as icon name.
	 */
	private function authorView(array $row, $size) {
		$image = $row['image'] && is_file(DIR_IMAGE . $row['image']) ? $row['image'] : '';
		$social = array();

		foreach ($row['social'] as $key => $url) {
			$social[] = array('key' => $key, 'icon' => $key === 'website' ? 'i-globe' : 'i-' . $key, 'url' => htmlspecialchars($url, ENT_QUOTES, 'UTF-8'), 'label' => $this->language->get('text_social_' . $key));
		}

		return array(
			'author_id'   => (int)$row['author_id'],
			'name'        => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
			'position'    => htmlspecialchars($row['position'], ENT_QUOTES, 'UTF-8'),
			'description' => nl2br(htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8')),
			'thumb'       => $image ? $this->model_tool_image->resize($image, $size, $size) : '',
			'initial'     => htmlspecialchars(utf8_strtoupper(utf8_substr($row['name'], 0, 1)), ENT_QUOTES, 'UTF-8'),
			'social'      => $social
		);
	}
}
