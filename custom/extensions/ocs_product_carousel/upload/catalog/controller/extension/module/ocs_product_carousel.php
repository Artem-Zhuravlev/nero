<?php
// Extension: ocs_product_carousel. Product carousel by category and mode; several instances (core module table). Owner: SPEC-033 (REQ-HOM-020).

class ControllerExtensionModuleOcsProductCarousel extends Controller {
	public function index($setting) {
		if (empty($setting['status'])) {
			return '';
		}

		$limit = max(1, min(30, (int)(isset($setting['limit']) ? $setting['limit'] : 10)));
		$mode = isset($setting['mode']) ? $setting['mode'] : 'newest';
		$category_id = (int)(isset($setting['category_id']) ? $setting['category_id'] : 0);
		$manual = isset($setting['ids']) ? (string)$setting['ids'] : '';

		$ids = $this->ids($mode, $category_id, $manual, $limit);

		if (!$ids) {
			return '';
		}

		$this->load->model('catalog/product');
		$this->load->model('tool/image');
		$this->load->language('extension/module/ocs_product_carousel');

		$width = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width') ?: 400;
		$height = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height') ?: 400;
		$show_price = $this->customer->isLogged() || !$this->config->get('config_customer_price');

		$data['products'] = array();

		foreach ($ids as $product_id) {
			$product = $this->model_catalog_product->getProduct($product_id);

			if (!$product) {
				continue;
			}

			$price = $show_price ? $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']) : false;
			$special = false;
			$discount = 0;
			$tax_price = (float)$product['price'];

			if ($show_price && !is_null($product['special']) && (float)$product['special'] >= 0) {
				$special = $this->currency->format($this->tax->calculate($product['special'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				$tax_price = (float)$product['special'];

				if ((float)$product['price'] > 0 && (float)$product['special'] < (float)$product['price']) {
					$discount = (int)round((1 - (float)$product['special'] / (float)$product['price']) * 100);
				}
			}

			$data['products'][] = array(
				'product_id'   => $product['product_id'],
				'thumb'        => $this->model_tool_image->resize($product['image'] ? $product['image'] : 'placeholder.png', $width, $height),
				'name'         => $product['name'],
				'description'  => utf8_substr(trim(strip_tags(html_entity_decode($product['description'], ENT_QUOTES, 'UTF-8'))), 0, 100) . '..',
				'price'        => $price,
				'special'      => $special,
				'tax'          => $this->config->get('config_tax') ? $this->currency->format($tax_price, $this->session->data['currency']) : false,
				'minimum'      => $product['minimum'] > 0 ? $product['minimum'] : 1,
				'rating'       => $this->config->get('config_review_status') ? (int)$product['rating'] : false,
				'discount_pct' => $discount,
				'href'         => $this->url->link('product/product', 'product_id=' . $product['product_id'])
			);
		}

		if (!$data['products']) {
			return '';
		}

		// REQ-STK-010: stickers in module blocks
		if ($this->config->get('module_ocs_stickers_status')) {
			$this->load->model('extension/ocs_stickers/stickers');

			$map = $this->model_extension_ocs_stickers_stickers->forProducts(array_column($data['products'], 'product_id'));

			foreach ($data['products'] as $i => $card) {
				if (isset($map[(int)$card['product_id']])) {
					$data['products'][$i]['stickers'] = $map[(int)$card['product_id']];
				}
			}
		}

		$titles = isset($setting['title']) && is_array($setting['title']) ? $setting['title'] : array();
		$code = $this->config->get('config_language_id');
		$title = '';

		foreach ($titles as $lang_id => $text) {
			if ((int)$lang_id === (int)$code && trim((string)$text) !== '') {
				$title = $text;
			}
		}

		$data['heading_title'] = $title !== '' ? \Ocs\OrderRequest::e($title) : $this->language->get('heading_title');
		$data['show_all'] = '';

		if ($category_id && !empty($setting['show_all'])) {
			$data['show_all'] = $this->url->link('product/category', 'path=' . $category_id);
		} elseif (!$category_id && !empty($setting['show_all']) && $mode == 'discounted') {
			$data['show_all'] = $this->url->link('product/special');
		}

		$data['text_show_all'] = $this->language->get('text_show_all');
		$data['text_prev'] = $this->language->get('text_prev');
		$data['text_next'] = $this->language->get('text_next');
		$data['text_tax'] = $this->language->get('text_tax');
		$data['button_cart'] = $this->language->get('button_cart');
		$data['button_wishlist'] = $this->language->get('button_wishlist');
		$data['button_compare'] = $this->language->get('button_compare');

		return $this->load->view('extension/module/ocs_product_carousel', $data);
	}

	/**
	 * Product ids for the block; one cached query per instance and day (REQ-HOM-050). Visibility is re-checked by getProduct().
	 */
	private function ids($mode, $category_id, $manual, $limit) {
		if ($mode == 'manual') {
			return array_slice(array_values(array_unique(array_filter(array_map('intval', explode(',', $manual))))), 0, $limit);
		}

		$key = 'ocs_pcar.' . (int)$this->config->get('config_store_id') . '.' . md5($mode . '|' . $category_id . '|' . $limit . '|' . (int)$this->config->get('config_customer_group_id')) . '.' . date('Ymd');

		$ids = $this->cache->get($key);

		if (is_array($ids)) {
			return $ids;
		}

		$store = (int)$this->config->get('config_store_id');
		$group = (int)$this->config->get('config_customer_group_id');

		$sql = "SELECT p.product_id FROM " . DB_PREFIX . "product p"
			. " LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)";

		if ($category_id) {
			$sql .= " INNER JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id)"
				. " INNER JOIN " . DB_PREFIX . "category_path cp ON (cp.category_id = p2c.category_id AND cp.path_id = '" . $category_id . "')";
		}

		if ($mode == 'bestsellers') {
			$sql .= " LEFT JOIN " . DB_PREFIX . "order_product op ON (op.product_id = p.product_id)"
				. " LEFT JOIN `" . DB_PREFIX . "order` o ON (o.order_id = op.order_id AND o.order_status_id > '0')";
		}

		$sql .= " WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . $store . "'";

		if ($mode == 'discounted') {
			$sql .= " AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id"
				. " AND ps.customer_group_id = '" . $group . "'"
				. " AND (ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))";
		}

		$sql .= " GROUP BY p.product_id";

		if ($mode == 'bestsellers') {
			$sql .= " ORDER BY SUM(CASE WHEN o.order_id IS NULL THEN 0 ELSE op.quantity END) DESC, p.sort_order ASC";
		} elseif ($mode == 'discounted') {
			$sql .= " ORDER BY p.sort_order ASC, p.date_added DESC";
		} else {
			$sql .= " ORDER BY p.date_added DESC";
		}

		$sql .= " LIMIT " . (int)$limit;

		$ids = array();

		foreach ($this->db->query($sql)->rows as $row) {
			$ids[] = (int)$row['product_id'];
		}

		$this->cache->set($key, $ids);

		return $ids;
	}
}
