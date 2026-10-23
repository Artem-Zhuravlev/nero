<?php
// Extension: ocs_ajax_search. JSON suggestions for the header search box. Owner: SPEC-028 (REQ-SRC-001..005, 010, 011).
//
// Products come from core ModelCatalogProduct::getProducts() (same visibility, store, price rules as the search page) plus a model/SKU
// prefix match; categories and brands are matched by name. Every string in the answer is plain text (entities decoded): theme.js renders
// it with textContent.

class ControllerExtensionModuleOcsAjaxSearch extends Controller {
	/**
	 * GET q=<query>: suggestions; an empty q returns the configured popular queries.
	 */
	public function suggest() {
		$json = array();

		if (!$this->config->get('module_ocs_ajax_search_status')) {
			$json['disabled'] = true;

			$this->output($json);

			return;
		}

		$ip = isset($this->request->server['REMOTE_ADDR']) ? $this->request->server['REMOTE_ADDR'] : '';
		$key = 'ocs_search.' . md5($ip);

		$stamps = $this->cache->get($key);

		list($allowed, $stamps) = \Ocs\OrderRequest::rateLimit(is_array($stamps) ? $stamps : array(), time(), 10, 10);

		$this->cache->set($key, $stamps);

		if (!$allowed) {
			$this->response->addHeader('HTTP/1.1 429 Too Many Requests');
			$this->output(array('error' => 'rate'));

			return;
		}

		$q = isset($this->request->get['q']) ? html_entity_decode((string)$this->request->get['q'], ENT_QUOTES, 'UTF-8') : '';
		$q = trim(preg_replace('/\s+/u', ' ', $q));

		$min = max(1, min(5, (int)$this->config->get('module_ocs_ajax_search_min_length') ?: 2));

		$json['all'] = $this->plainLink('product/search', 'search=' . rawurlencode($q));

		if ($q === '') {
			$json['popular'] = $this->popular();

			$this->output($json);

			return;
		}

		if (mb_strlen($q, 'UTF-8') < $min || mb_strlen($q, 'UTF-8') > 100) {
			$json['products'] = array();
			$json['categories'] = array();
			$json['brands'] = array();
			$json['popular'] = array();

			$this->output($json);

			return;
		}

		$limit = max(1, min(12, (int)$this->config->get('module_ocs_ajax_search_limit') ?: 6));

		$json['products'] = $this->products($q, $limit);
		$json['categories'] = $this->config->get('module_ocs_ajax_search_categories') ? $this->categories($q) : array();
		$json['brands'] = $this->config->get('module_ocs_ajax_search_brands') ? $this->brands($q) : array();
		$json['popular'] = ($json['products'] || $json['categories'] || $json['brands']) ? array() : $this->popular();

		$this->output($json);
	}

	private function products($q, $limit) {
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$found = $this->model_catalog_product->getProducts(array('filter_name' => $q, 'sort' => 'p.sort_order', 'order' => 'ASC', 'start' => 0, 'limit' => $limit));

		// model / SKU prefix ("MBA13" finds "MBA13-M5"): the core filter only matches them exactly
		if (count($found) < $limit) {
			$like = $this->db->escape(utf8_strtolower($q));

			$query = $this->db->query("SELECT p.product_id FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND (LCASE(p.model) LIKE '" . $like . "%' OR LCASE(p.sku) LIKE '" . $like . "%') LIMIT " . (int)$limit);

			foreach ($query->rows as $row) {
				if (count($found) >= $limit) {
					break;
				}

				if (!isset($found[$row['product_id']])) {
					$product = $this->model_catalog_product->getProduct($row['product_id']);

					if ($product) {
						$found[$row['product_id']] = $product;
					}
				}
			}
		}

		$out = array();
		$show_price = $this->customer->isLogged() || !$this->config->get('config_customer_price');

		foreach ($found as $product) {
			$price = $show_price ? $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']) : '';
			$special = '';
			$discount = 0;

			if ($show_price && isset($product['special']) && (float)$product['special'] > 0 && (float)$product['special'] < (float)$product['price']) {
				$special = $this->currency->format($this->tax->calculate($product['special'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				$discount = (int)round((1 - (float)$product['special'] / (float)$product['price']) * 100);
			}

			$out[] = array(
				'name'     => html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8'),
				'model'    => html_entity_decode((string)$product['model'], ENT_QUOTES, 'UTF-8'),
				'href'     => $this->plainLink('product/product', 'product_id=' . (int)$product['product_id']),
				'thumb'    => $this->model_tool_image->resize($product['image'] ? $product['image'] : 'placeholder.png', 60, 60),
				'price'    => $price,
				'special'  => $special,
				'discount' => $discount
			);
		}

		return $out;
	}

	private function categories($q) {
		$query = $this->db->query("SELECT c.category_id, cd.name FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.status = '1' AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND LCASE(cd.name) LIKE '%" . $this->db->escape(utf8_strtolower($q)) . "%' ORDER BY c.parent_id, cd.name LIMIT 4");

		$out = array();

		foreach ($query->rows as $row) {
			$out[] = array('name' => html_entity_decode($row['name'], ENT_QUOTES, 'UTF-8'), 'href' => $this->plainLink('product/category', 'path=' . (int)$row['category_id']));
		}

		return $out;
	}

	private function brands($q) {
		$query = $this->db->query("SELECT m.manufacturer_id, m.name FROM " . DB_PREFIX . "manufacturer m LEFT JOIN " . DB_PREFIX . "manufacturer_to_store m2s ON (m.manufacturer_id = m2s.manufacturer_id) WHERE m2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND LCASE(m.name) LIKE '%" . $this->db->escape(utf8_strtolower($q)) . "%' ORDER BY m.name LIMIT 4");

		$out = array();

		foreach ($query->rows as $row) {
			$out[] = array('name' => html_entity_decode($row['name'], ENT_QUOTES, 'UTF-8'), 'href' => $this->plainLink('product/manufacturer/info', 'manufacturer_id=' . (int)$row['manufacturer_id']));
		}

		return $out;
	}

	/**
	 * Popular queries of the current language: lines "Label|URL" (URL optional, then it is a search for the label).
	 */
	private function popular() {
		$l = (array)$this->config->get('module_ocs_ajax_search_l');
		$l = isset($l[$this->config->get('config_language')]) && is_array($l[$this->config->get('config_language')]) ? $l[$this->config->get('config_language')] : array();

		$out = array();

		foreach (\Ocs\OrderRequest::parseLines(isset($l['popular']) ? $l['popular'] : '') as $item) {
			$out[] = array(
				'name' => $item['text'],
				'href' => $item['url'] !== '' ? $item['url'] : $this->plainLink('product/search', 'search=' . rawurlencode($item['text']))
			);
		}

		return array_slice($out, 0, 12);
	}

	/**
	 * Core url->link() returns HTML-escaped URLs (&amp;); JSON consumers need plain ones.
	 */
	private function plainLink($route, $args = '') {
		return str_replace('&amp;', '&', $this->url->link($route, $args));
	}

	private function output($json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
