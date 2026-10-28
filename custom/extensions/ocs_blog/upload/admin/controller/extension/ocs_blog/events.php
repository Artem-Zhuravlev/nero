<?php
// Extension: ocs_blog. Admin events: menu entry, author and date in the article form, multilingual blog settings, cleanup. Owner: SPEC-084.
// Handlers never throw to the admin user (REQ-EXT-062).

class ControllerExtensionOcsBlogEvents extends Controller {
	/**
	 * admin/view/common/column_left/before: "Authors" under the Blog menu (REQ-BLG-001).
	 */
	public function beforeMenu(&$route, &$data) {
		if (!is_array($data) || empty($data['menus']) || !$this->user->hasPermission('access', 'extension/ocs_blog/author')) {
			return;
		}

		try {
			$this->load->language('extension/ocs_blog/author');

			foreach ($data['menus'] as $i => $menu) {
				if (isset($menu['id']) && $menu['id'] == 'menu-blog') {
					$data['menus'][$i]['children'][] = array(
						'name'     => $this->language->get('heading_title'),
						'href'     => $this->url->link('extension/ocs_blog/author', 'user_token=' . $this->session->data['user_token'], true),
						'children' => array()
					);
				}
			}
		} catch (\Exception $e) {
			$this->log->write('[ocs_blog] ' . $e->getMessage());
		}
	}

	/**
	 * admin/view/blog/article_form/before: author select, publication date and reaction counters, printed by the OCMOD as {{ ocs_blog_article_extra }}.
	 */
	public function beforeArticleForm(&$route, &$data) {
		if (!is_array($data)) {
			return;
		}

		try {
			$strings = $this->load->language('extension/ocs_blog/author');
			$strings = is_array($strings) ? $strings : array();

			$this->load->model('extension/ocs_blog/author');

			$model = $this->model_extension_ocs_blog_author;
			$article_id = isset($this->request->get['article_id']) ? (int)$this->request->get['article_id'] : 0;
			$extra = $model->getArticleExtra($article_id);

			$author_id = isset($this->request->post['ocs_blog_author_id']) ? (int)$this->request->post['ocs_blog_author_id'] : (int)$extra['author_id'];
			$date = isset($this->request->post['ocs_blog_date']) ? (string)$this->request->post['ocs_blog_date'] : ($article_id ? (string)$model->getArticleDate($article_id) : date('Y-m-d'));

			if (strpos($date, '0000-00-00') === 0) {
				$date = date('Y-m-d');
			}

			$view = array(
				'entry_author'       => $this->language->get('entry_article_author'),
				'entry_date'         => $this->language->get('entry_article_date'),
				'help_date'          => $this->language->get('help_article_date'),
				'text_none'          => $this->language->get('text_none'),
				'text_reactions'     => $this->language->get('text_reactions'),
				'author_id'          => $author_id,
				'date'               => $date,
				'likes'              => (int)$extra['likes'],
				'dislikes'           => (int)$extra['dislikes'],
				'show_reactions'     => $article_id > 0,
				'authors'            => array()
			);

			foreach ($model->getAuthors() as $row) {
				if ($row['status'] || (int)$row['author_id'] === $author_id) {
					$view['authors'][] = array('author_id' => $row['author_id'], 'name' => $row['name'] . ($row['position'] ? ' — ' . $row['position'] : ''));
				}
			}

			$data['ocs_blog_article_extra'] = $this->load->view('extension/ocs_blog/article_extra', $view);
		} catch (\Exception $e) {
			$this->log->write('[ocs_blog] ' . $e->getMessage());
		}
	}

	/**
	 * admin/model/blog/article/addArticle/after: $output is the new article id, $args[0] the posted form.
	 */
	public function afterAddArticle(&$route, &$args, &$output) {
		if (is_array($args) && !empty($args[0]) && (int)$output > 0) {
			$this->saveExtra((int)$output, $args[0]);
		}
	}

	/**
	 * admin/model/blog/article/editArticle/after: $args = array(article_id, data).
	 */
	public function afterEditArticle(&$route, &$args, &$output) {
		if (is_array($args) && !empty($args[0]) && !empty($args[1]) && is_array($args[1])) {
			$this->saveExtra((int)$args[0], $args[1]);
		}
	}

	private function saveExtra($article_id, $post) {
		try {
			$this->load->model('extension/ocs_blog/author');

			$model = $this->model_extension_ocs_blog_author;

			if (array_key_exists('ocs_blog_author_id', $post)) {
				$model->saveArticleAuthor($article_id, (int)$post['ocs_blog_author_id']);
			}

			if (array_key_exists('ocs_blog_date', $post)) {
				$date = isset($post['ocs_blog_date']) ? (string)$post['ocs_blog_date'] : '';
				$time = $date !== '' ? strtotime($date) : false;

				$model->saveArticleDate($article_id, $time ? date('Y-m-d', $time) : date('Y-m-d'));
			}
		} catch (\Exception $e) {
			$this->log->write('[ocs_blog] ' . $e->getMessage());
		}
	}

	public function beforeDeleteArticle(&$route, &$args) {
		if (is_array($args) && !empty($args[0])) {
			try {
				$this->load->model('extension/ocs_blog/author');
				$this->model_extension_ocs_blog_author->deleteArticleData((int)$args[0]);
			} catch (\Exception $e) {
				$this->log->write('[ocs_blog] ' . $e->getMessage());
			}
		}
	}

	public function beforeDeleteReview(&$route, &$args) {
		if (is_array($args) && !empty($args[0])) {
			try {
				$this->load->model('extension/ocs_blog/author');
				$this->model_extension_ocs_blog_author->deleteCommentData((int)$args[0]);
			} catch (\Exception $e) {
				$this->log->write('[ocs_blog] ' . $e->getMessage());
			}
		}
	}

	/**
	 * admin/view/blog/setting/before: language tabs with name, H1 and meta fields, printed by the OCMOD as {{ ocs_blog_lang_fields }} (REQ-BLG-020).
	 */
	public function beforeSettingForm(&$route, &$data) {
		if (!is_array($data)) {
			return;
		}

		try {
			$this->load->language('extension/ocs_blog/author');
			$this->load->model('localisation/language');

			$languages = $this->model_localisation_language->getLanguages();
			$default = (int)$this->config->get('config_language_id');

			$legacy = array();

			foreach (array('name', 'html_h1', 'meta_title', 'meta_description', 'meta_keyword') as $key) {
				$legacy[$key] = (string)$this->config->get('configblog_' . $key);
			}

			$values = array();

			if (isset($this->request->post['ocs_blog_desc']) && is_array($this->request->post['ocs_blog_desc'])) {
				$values = \Ocs\Blog::cleanLangSettings($this->request->post['ocs_blog_desc'], array_column($languages, 'language_id'));
			} else {
				foreach ($languages as $language) {
					$values[(int)$language['language_id']] = \Ocs\Blog::langSettings($this->config->get('configblog_description'), (int)$language['language_id'], $default, $legacy);
				}
			}

			$view = array(
				'languages' => $languages,
				'values'    => $values
			);

			foreach (array('entry_name', 'entry_html_h1', 'entry_meta_title', 'entry_meta_description', 'entry_meta_keyword') as $key) {
				$view[$key] = isset($data[$key]) ? $data[$key] : $this->language->get($key);
			}

			$data['ocs_blog_lang_fields'] = $this->load->view('extension/ocs_blog/lang_fields', $view);
		} catch (\Exception $e) {
			$this->log->write('[ocs_blog] ' . $e->getMessage());
		}
	}

	/**
	 * admin/model/setting/setting/editSetting/before: $args = array(code, data, store_id). For the blog settings the posted language
	 * values become configblog_description; the old single keys are refilled from the default language so core readers keep working.
	 */
	public function beforeEditSetting(&$route, &$args) {
		if (!is_array($args) || !isset($args[0]) || $args[0] !== 'configblog' || !isset($args[1]) || !is_array($args[1]) || !isset($args[1]['ocs_blog_desc']) || !is_array($args[1]['ocs_blog_desc'])) {
			return;
		}

		try {
			$this->load->model('localisation/language');

			$languages = $this->model_localisation_language->getLanguages();
			$clean = \Ocs\Blog::cleanLangSettings($args[1]['ocs_blog_desc'], array_column($languages, 'language_id'));

			$default = (int)$this->config->get('config_language_id');
			$first = isset($clean[$default]) ? $clean[$default] : reset($clean);

			foreach (array('name', 'html_h1', 'meta_title', 'meta_description', 'meta_keyword') as $key) {
				$args[1]['configblog_' . $key] = $first ? $first[$key] : '';
			}

			$args[1]['configblog_description'] = json_encode($clean, JSON_UNESCAPED_UNICODE);

			unset($args[1]['ocs_blog_desc']);
		} catch (\Exception $e) {
			$this->log->write('[ocs_blog] ' . $e->getMessage());
		}
	}
}
