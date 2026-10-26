<?php
// Extension: ocs_product_extras. Adds key attributes, section bar, questions, accessories, similar products and JSON-LD to the product view data. Owner: SPEC-035.
// The handler never throws to the customer (REQ-EXT-062).

class ControllerExtensionOcsProductExtrasEvents extends Controller {
	/**
	 * Every storefront view: product rows (listings, modules, carousels) get has_video with one query per view (SPEC-048 REQ-VID-003).
	 */
	public function markVideo(&$route, &$data) {
		if (!is_array($data) || $this->config->get('config_theme') != 'ocs' || !$this->config->get('module_ocs_product_extras_status')) {
			return;
		}

		try {
			$ids = \Ocs\Video::rowIds($data);

			if (!$ids) {
				return;
			}

			$this->load->model('extension/ocs_product_extras/extras');

			$with = $this->model_extension_ocs_product_extras_extras->getVideoProductIds($ids);

			if ($with) {
				$this->load->language('extension/module/ocs_product_extras');

				$data['text_video'] = $this->language->get('text_video');
				$data = \Ocs\Video::mark($data, $with);
			}
		} catch (\Exception $e) {
			$this->log->write('[ocs_product_extras] ' . $e->getMessage());
		}
	}

	public function beforeProduct(&$route, &$data) {
		if (!is_array($data) || empty($data['product_id']) || !$this->config->get('module_ocs_product_extras_status') || $this->config->get('config_theme') != 'ocs') {
			return;
		}

		try {
			$this->load->language('extension/module/ocs_product_extras');
			$this->load->model('extension/ocs_product_extras/extras');
			$this->load->model('catalog/product');

			$model = $this->model_extension_ocs_product_extras_extras;
			$product_id = (int)$data['product_id'];
			$product = $this->model_catalog_product->getProduct($product_id);

			if (!$product) {
				return;
			}

			$e = function ($v) {
				return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
			};

			$x = array('sections' => array());

			// REQ-PRD-002: key attributes under the title
			$x['key_attrs'] = \Ocs\ProductExtras::keyAttributes(isset($data['attribute_groups']) ? $data['attribute_groups'] : array(), \Ocs\ProductExtras::ids($this->config->get('module_ocs_product_extras_key_attrs'), 4), 4);

			$x['sections'][] = array('href' => '#tab-description', 'tab' => true, 'text' => $this->language->get('section_about'));

			if (!empty($data['attribute_groups'])) {
				$x['sections'][] = array('href' => '#tab-specification', 'tab' => true, 'text' => $this->language->get('section_specs'));
			}

			if (!empty($data['review_status'])) {
				$x['sections'][] = array('href' => '#tab-review', 'tab' => true, 'text' => $this->language->get('section_reviews'));
			}

			// REQ-PRD-010: questions and answers
			$x['qa'] = false;

			if ($this->config->get('module_ocs_product_extras_questions')) {
				$this->load->model('extension/ocs_request/guard');

				$list = array();

				foreach ($model->questions($product_id) as $q) {
					$list[] = array('name' => $e($q['name']), 'text' => nl2br($e($q['text'])), 'answer' => $q['answer'] !== '' ? nl2br($e($q['answer'])) : '', 'date' => date($this->language->get('date_format_short'), strtotime($q['date_added'])));
				}

				$x['qa'] = array(
					'list'   => $list,
					'total'  => $model->totalQuestions($product_id),
					'action' => $this->url->link('extension/module/ocs_product_extras/ask', '', true),
					'token'  => $this->model_extension_ocs_request_guard->token(),
					'name'   => $this->customer->isLogged() ? $e($this->customer->getFirstName()) : '',
					'email'  => $this->customer->isLogged() ? $e($this->customer->getEmail()) : ''
				);

				$x['qa']['label'] = sprintf($this->language->get('section_questions'), $x['qa']['total']);
				$x['qa']['ask'] = array();

				foreach (array('text_ask_title', 'text_no_questions', 'text_answer', 'entry_name', 'entry_email', 'entry_question', 'button_ask', 'error_generic') as $key) {
					$x['qa']['ask'][$key] = $this->language->get($key);
				}

				$x['sections'][] = array('href' => '#tab-questions', 'tab' => true, 'text' => $x['qa']['label']);
			}

			// REQ-PRD-020 / 021: carousels
			$x['accessories'] = array();
			$x['similar'] = array();

			if ($this->config->get('module_ocs_product_extras_accessories')) {
				$x['accessories'] = $model->cards($model->accessoryIds($product_id));
			}

			if ($this->config->get('module_ocs_product_extras_similar')) {
				$limit = max(1, min(20, (int)$this->config->get('module_ocs_product_extras_similar_limit') ?: 10));
				$x['similar'] = $model->cards($model->similarIds($product_id, $product['manufacturer_id'], !is_null($product['special']) ? $product['special'] : $product['price'], $limit));
			}

			if ($x['accessories']) {
				$x['sections'][] = array('href' => '#ocs-accessories', 'tab' => false, 'text' => $this->language->get('section_accessories'));
			}

			if ($x['similar']) {
				$x['sections'][] = array('href' => '#ocs-similar', 'tab' => false, 'text' => $this->language->get('section_similar'));
			}

			foreach (array('text_accessories', 'text_similar', 'text_prev', 'text_next', 'text_key_attrs', 'text_code') as $key) {
				$x[$key] = $this->language->get($key);
			}

			// REQ-PRD-040: Schema.org
			$this->load->model('catalog/review');

			$x['schema'] = \Ocs\ProductExtras::schema(array(
				'name'        => $product['name'],
				'url'         => html_entity_decode($this->url->link('product/product', 'product_id=' . $product_id), ENT_QUOTES, 'UTF-8'),
				'image'       => isset($data['thumb']) ? $data['thumb'] : '',
				'sku'         => $product['model'],
				'brand'       => $product['manufacturer'],
				'description' => utf8_substr(trim(strip_tags(html_entity_decode($product['description'], ENT_QUOTES, 'UTF-8'))), 0, 300),
				'price'       => $this->customer->isLogged() || !$this->config->get('config_customer_price') ? $this->tax->calculate(!is_null($product['special']) ? $product['special'] : $product['price'], $product['tax_class_id'], $this->config->get('config_tax')) : false,
				'currency'    => $this->session->data['currency'],
				'in_stock'    => (int)$product['quantity'] > 0,
				'rating'      => (int)$product['rating'],
				'reviews'     => $this->config->get('config_review_status') ? $this->model_catalog_review->getTotalReviewsByProductId($product_id) : 0
			), isset($data['breadcrumbs']) ? $data['breadcrumbs'] : array());

			// REQ-PRD-030: the first image is the LCP image, so it gets its size up front
			$x['thumb_w'] = (int)$this->config->get('theme_ocs_image_thumb_width');
			$x['thumb_h'] = (int)$this->config->get('theme_ocs_image_thumb_height');

			// REQ-VIS-006: review summary with the star distribution
			$x['stars'] = array('total' => 0, 'avg' => 0, 'rows' => array());

			if ($this->config->get('config_review_status')) {
				$counts = array(5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0);

				foreach ($this->db->query("SELECT rating, COUNT(*) AS n FROM " . DB_PREFIX . "review WHERE product_id = '" . $product_id . "' AND status = '1' GROUP BY rating")->rows as $row) {
					if (isset($counts[(int)$row['rating']])) {
						$counts[(int)$row['rating']] = (int)$row['n'];
					}
				}

				$x['stars'] = \Ocs\ProductExtras::stars($counts);
				$x['stars']['title'] = $this->language->get('text_reviews_summary');
			}

			// REQ-SWP-004: every image in the main size for the swipeable gallery (core gives only small thumbs for the additional ones)
			$this->load->model('tool/image');

			$size = function ($role, $w, $h) {
				return array((int)$this->config->get('theme_ocs_image_' . $role . '_width') ?: $w, (int)$this->config->get('theme_ocs_image_' . $role . '_height') ?: $h);
			};

			list($mw, $mh) = $size('thumb', 500, 500);
			list($pw, $ph) = $size('popup', 1000, 1000);
			list($aw, $ah) = $size('additional', 74, 74);

			$paths = array();

			if ($product['image']) {
				$paths[] = $product['image'];
			}

			foreach ($this->model_catalog_product->getProductImages($product_id) as $row) {
				$paths[] = $row['image'];
			}

			$x['gallery'] = array();

			foreach ($paths as $path) {
				$x['gallery'][] = array(
					'main'  => $this->model_tool_image->resize($path, $mw, $mh),
					'popup' => $this->model_tool_image->resize($path, $pw, $ph),
					'thumb' => $this->model_tool_image->resize($path, $aw, $ah)
				);
			}

			// REQ-VID-002: the video is the last item of the gallery; the player loads only after a click
			$video = $model->getVideoId($product_id);

			$x['video'] = $video === '' ? false : array(
				'id'     => $video,
				'poster' => \Ocs\Video::poster($video),
				'embed'  => \Ocs\Video::embed($video),
				'label'  => $this->language->get('text_video')
			);

			$x['lb'] = htmlspecialchars(json_encode(array(
				'prev'    => $this->language->get('text_prev'),
				'next'    => $this->language->get('text_next'),
				'close'   => $this->language->get('text_lb_close'),
				'counter' => $this->language->get('text_lb_counter'),
				'gallery' => $this->language->get('text_lb_gallery'),
				'play'    => $this->language->get('text_lb_play'),
				'video'   => $this->language->get('text_video')
			)), ENT_QUOTES, 'UTF-8');

			$x['main_w'] = $mw;
			$x['main_h'] = $mh;

			$data['ocs_px'] = $x;
		} catch (\Exception $ex) {
			$this->log->write('[ocs_product_extras] ' . $ex->getMessage());
		}
	}
}
