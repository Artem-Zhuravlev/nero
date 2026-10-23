<?php
// Extension: ocs_product_extras. Questions, accessories, similar products and card data. Owner: SPEC-035.

class ModelExtensionOcsProductExtrasExtras extends Model {
	public function questions($product_id, $limit = 20) {
		return $this->db->query("SELECT name, text, answer, date_added FROM `" . DB_PREFIX . "ocs_question` WHERE product_id = '" . (int)$product_id . "' AND status = '1' ORDER BY question_id DESC LIMIT " . (int)$limit)->rows;
	}

	public function totalQuestions($product_id) {
		return (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "ocs_question` WHERE product_id = '" . (int)$product_id . "' AND status = '1'")->row['total'];
	}

	/**
	 * New questions wait for moderation (status 0).
	 */
	public function addQuestion($product_id, $customer_id, $clean, $ip) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "ocs_question` SET product_id = '" . (int)$product_id . "', customer_id = '" . (int)$customer_id . "', name = '" . $this->db->escape($clean['name']) . "', email = '" . $this->db->escape($clean['email']) . "', text = '" . $this->db->escape($clean['text']) . "', answer = '', status = '0', ip = '" . $this->db->escape(substr($ip, 0, 40)) . "', date_added = NOW()");
	}

	public function accessoryIds($product_id) {
		$ids = array();

		foreach ($this->db->query("SELECT accessory_id FROM `" . DB_PREFIX . "ocs_product_accessory` WHERE product_id = '" . (int)$product_id . "' ORDER BY sort_order ASC, accessory_id ASC")->rows as $row) {
			$ids[] = (int)$row['accessory_id'];
		}

		return $ids;
	}

	/**
	 * Same category and (when the product has one) same brand, price within the band, current product excluded. One cached query per product and day.
	 */
	public function similarIds($product_id, $manufacturer_id, $price, $limit) {
		$key = 'ocs_similar.' . (int)$this->config->get('config_store_id') . '.' . (int)$product_id . '.' . (int)$this->config->get('config_customer_group_id') . '.' . (int)$limit . '.' . date('Ymd');

		$ids = $this->cache->get($key);

		if (is_array($ids)) {
			return $ids;
		}

		list($min, $max) = \Ocs\ProductExtras::priceBand($price);

		$sql = "SELECT DISTINCT p.product_id FROM `" . DB_PREFIX . "product` p"
			. " INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "')"
			. " INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p.product_id = p2c.product_id)"
			. " WHERE p2c.category_id IN (SELECT category_id FROM `" . DB_PREFIX . "product_to_category` WHERE product_id = '" . (int)$product_id . "')"
			. " AND p.product_id <> '" . (int)$product_id . "' AND p.status = '1' AND p.date_available <= NOW()";

		if ((int)$manufacturer_id > 0) {
			$sql .= " AND p.manufacturer_id = '" . (int)$manufacturer_id . "'";
		}

		if ((float)$price > 0) {
			$sql .= " AND p.price BETWEEN '" . (float)$min . "' AND '" . (float)$max . "'";
		}

		$sql .= " ORDER BY p.sort_order ASC, p.date_added DESC LIMIT " . (int)$limit;

		$ids = array();

		foreach ($this->db->query($sql)->rows as $row) {
			$ids[] = (int)$row['product_id'];
		}

		$this->cache->set($key, $ids);

		return $ids;
	}

	/**
	 * Card data (the shared ocs/card.twig partial) for the visible products among $ids.
	 */
	public function cards($ids) {
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$theme = $this->config->get('config_theme');
		$width = (int)$this->config->get('theme_' . $theme . '_image_product_width') ?: 400;
		$height = (int)$this->config->get('theme_' . $theme . '_image_product_height') ?: 400;
		$show_price = $this->customer->isLogged() || !$this->config->get('config_customer_price');
		$out = array();

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

			$out[] = array(
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

		return $out;
	}
}
