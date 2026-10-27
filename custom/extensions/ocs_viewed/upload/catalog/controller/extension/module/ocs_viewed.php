<?php
// Extension: ocs_viewed. "You recently viewed" block for layout positions (home, product page, ...). Owner: SPEC-030 (REQ-VWD-002..004).

class ControllerExtensionModuleOcsViewed extends Controller {
	public function index() {
		if (!$this->config->get('module_ocs_viewed_status')) {
			return '';
		}

		$limit = max(1, min(12, (int)$this->config->get('module_ocs_viewed_limit') ?: 4));
		$current = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;

		$this->load->model('extension/ocs_viewed/history');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');
		$this->load->language('extension/module/ocs_viewed');

		$width = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width') ?: 400;
		$height = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height') ?: 400;

		$data['products'] = array();

		// a few more ids than needed: disabled or deleted products are skipped (REQ-VWD-004)
		foreach ($this->model_extension_ocs_viewed_history->ids($current, \Ocs\Viewed::MAX) as $product_id) {
			if (count($data['products']) >= $limit) {
				break;
			}

			$product = $this->model_catalog_product->getProduct($product_id);

			if (!$product) {
				continue;
			}

			$show_price = $this->customer->isLogged() || !$this->config->get('config_customer_price');

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
				'quantity'     => (int)$product['quantity'],
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

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_tax'] = $this->language->get('text_tax');
		$data['button_cart'] = $this->language->get('button_cart');
		$data['button_wishlist'] = $this->language->get('button_wishlist');
		$data['button_compare'] = $this->language->get('button_compare');

		return $this->load->view('extension/module/ocs_viewed', $data);
	}
}
