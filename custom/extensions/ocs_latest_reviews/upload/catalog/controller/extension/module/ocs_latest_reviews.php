<?php
// Extension: ocs_latest_reviews. Block of the latest approved reviews for layout positions; several instances (core module table). Owner: SPEC-081 (REQ-LRV-001..006).

class ControllerExtensionModuleOcsLatestReviews extends Controller {
	public function index($setting) {
		if (empty($setting['status']) || !$this->config->get('config_review_status')) {
			return '';
		}

		$limit = max(1, min(20, (int)(isset($setting['limit']) ? $setting['limit'] : 6)));
		$length = max(40, min(1000, (int)(isset($setting['length']) ? $setting['length'] : 200)));
		$min_rating = max(1, min(5, (int)(isset($setting['min_rating']) ? $setting['min_rating'] : 1)));
		$category_id = 0;

		// "this category only" applies on category pages; elsewhere the block shows reviews of the whole store
		if (!empty($setting['by_category']) && isset($this->request->get['path'])) {
			$parts = explode('_', (string)$this->request->get['path']);
			$category_id = (int)end($parts);
		}

		$this->load->model('extension/ocs_latest_reviews/reviews');
		$this->load->model('tool/image');
		$this->load->language('extension/module/ocs_latest_reviews');

		$reviews = array();

		foreach ($this->model_extension_ocs_latest_reviews_reviews->latest($limit, $min_rating, $category_id) as $row) {
			$text = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string)$row['text'], ENT_QUOTES, 'UTF-8'))));

			if (utf8_strlen($text) > $length) {
				$text = rtrim(utf8_substr($text, 0, $length), ' .,;:-') . '…';
			}

			$reviews[] = array(
				'author' => htmlspecialchars(html_entity_decode((string)$row['author'], ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8'),
				'rating' => (int)$row['rating'],
				'text'   => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
				'date'   => date($this->language->get('date_format_short'), strtotime($row['date_added'])),
				'name'   => htmlspecialchars(html_entity_decode((string)$row['name'], ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8'),
				'thumb'  => $this->model_tool_image->resize($row['image'] ? $row['image'] : 'placeholder.png', 80, 80),
				'href'   => $this->url->link('product/product', 'product_id=' . (int)$row['product_id'])
			);
		}

		if (!$reviews) {
			return '';
		}

		$title = '';
		$titles = isset($setting['title']) && is_array($setting['title']) ? $setting['title'] : array();

		foreach ($titles as $lang_id => $text) {
			if ((int)$lang_id === (int)$this->config->get('config_language_id') && trim((string)$text) !== '') {
				$title = $text;
			}
		}

		$data['heading_title'] = htmlspecialchars($title !== '' ? html_entity_decode($title, ENT_QUOTES, 'UTF-8') : $this->language->get('heading_title'), ENT_QUOTES, 'UTF-8');
		$data['reviews'] = $reviews;
		$data['text_prev'] = $this->language->get('text_prev');
		$data['text_next'] = $this->language->get('text_next');

		return $this->load->view('extension/module/ocs_latest_reviews', $data);
	}
}
