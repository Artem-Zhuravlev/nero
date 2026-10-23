<?php
// Extension: ocs_listing. View events for listing pages of the ocs theme. Owner: SPEC-034.
// Adds card data, the sort list, the reset-filters link and load-more text. Never throws to the customer (REQ-EXT-062).

class ControllerExtensionOcsListingEvents extends Controller {
	/** GET keys that belong to the page itself; every other key is treated as a filter parameter. */
	private $known = array('route', 'path', 'sort', 'order', 'limit', 'page', 'search', 'tag', 'description', 'category_id', 'sub_category', 'manufacturer_id', '_route_');

	public function beforeCategory(&$route, &$data) {
		$this->apply($route, $data, true);
	}

	public function beforeSearch(&$route, &$data) {
		$this->apply($route, $data, true);
	}

	public function beforeManufacturer(&$route, &$data) {
		$this->apply($route, $data, true);
	}

	public function beforeSpecial(&$route, &$data) {
		// ModelCatalogProduct::getProductSpecials() has its own sort whitelist: keep the core sort list here.
		$this->apply($route, $data, false);
	}

	private function apply($route, &$data, $replace_sorts) {
		if (!is_array($data) || !$this->config->get('library_ocs_listing_status') || $this->config->get('config_theme') != 'ocs') {
			return;
		}

		$strings = $this->load->language('extension/ocs_listing/listing');
		$strings = is_array($strings) ? $strings : array();

		if (!empty($data['products']) && is_array($data['products'])) {
			$ids = array();

			foreach ($data['products'] as $product) {
				if (isset($product['product_id'])) {
					$ids[] = $product['product_id'];
				}
			}

			$this->load->model('extension/ocs_listing/listing');

			$extras = $this->model_extension_ocs_listing_listing->getExtras($ids);

			foreach ($data['products'] as $i => $product) {
				if (isset($product['product_id'], $extras[(int)$product['product_id']])) {
					$data['products'][$i] = array_merge($product, $extras[(int)$product['product_id']]);
				}
			}
		}

		$page = array();
		$filters = array();

		foreach ($this->request->get as $key => $value) {
			if (in_array($key, array('sort', 'order', 'page'), true)) {
				continue;
			}

			$value = is_string($value) ? html_entity_decode($value, ENT_QUOTES, 'UTF-8') : $value;
			$page[$key] = $value;

			if (!in_array($key, $this->known, true)) {
				$filters[$key] = $value;
			}
		}

		unset($page['route']);

		if ($replace_sorts && isset($data['sorts'])) {
			$data['sorts'] = $this->sorts($route, $page, $strings);
		}

		$reset = '';

		if ($filters) {
			$keep = array();

			foreach ($page as $key => $value) {
				if (!isset($filters[$key])) {
					$keep[$key] = $value;
				}
			}

			$reset = $this->url->link($route, http_build_query($keep));
		}

		$limit = isset($data['limit']) ? (int)$data['limit'] : 0;

		$data['ocs_listing'] = array(
			't'         => $strings,
			'reset'     => $reset,
			'load_more' => sprintf(isset($strings['ocs_load_more']) ? $strings['ocs_load_more'] : '%s', $limit)
		);
	}

	private function sorts($route, $page, $strings) {
		$options = array(
			array('p.sort_order', 'ASC', 'ocs_sort_recommended'),
			array('p.price', 'ASC', 'ocs_sort_cheap'),
			array('p.price', 'DESC', 'ocs_sort_expensive'),
			array('p.date_added', 'DESC', 'ocs_sort_new'),
			array('p.viewed', 'DESC', 'ocs_sort_popular')
		);

		if ($this->config->get('config_review_status')) {
			$options[] = array('rating', 'DESC', 'ocs_sort_reviews');
		}

		$options[] = array('pd.name', 'ASC', 'ocs_sort_name');

		$sorts = array();

		foreach ($options as $option) {
			$sorts[] = array(
				'text'  => isset($strings[$option[2]]) ? $strings[$option[2]] : $option[2],
				'value' => $option[0] . '-' . $option[1],
				'href'  => $this->url->link($route, http_build_query(array_merge($page, array('sort' => $option[0], 'order' => $option[1]))))
			);
		}

		return $sorts;
	}
}
