<?php
// Extension: ocs_quick_view. Server-rendered body of the quick-view popup. Owner: SPEC-037 (REQ-QVW-001..004).
// The body is never cached (prices, stock and buttons are per visitor).

class ControllerExtensionModuleOcsQuickView extends Controller {
	/** option types that need the page widgets (uploads, date pickers): the quick view sends the visitor to the full page */
	private $pageOnly = array('file', 'date', 'datetime', 'time');

	/**
	 * GET product_id=N: HTML body for OCS.modal, empty when the product is unknown or disabled.
	 */
	public function view() {
		$this->response->addHeader('Cache-Control: no-store');

		$product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : (isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0);

		if (!$this->config->get('module_ocs_quick_view_status') || $product_id < 1) {
			$this->response->setOutput('');

			return;
		}

		$this->load->model('catalog/product');
		$this->load->model('tool/image');
		$this->load->model('extension/ocs_request/options');
		$this->load->language('extension/module/ocs_quick_view');

		$product = $this->model_catalog_product->getProduct($product_id);

		if (!$product) {
			$this->response->setOutput('');

			return;
		}

		$theme = $this->config->get('config_theme');
		$w = (int)$this->config->get('theme_' . $theme . '_image_product_width') ?: 400;
		$h = (int)$this->config->get('theme_' . $theme . '_image_product_height') ?: 400;
		$currency = $this->session->data['currency'];
		$show_price = $this->customer->isLogged() || !$this->config->get('config_customer_price');

		$paths = array($product['image'] ? $product['image'] : 'placeholder.png');

		foreach (array_slice($this->model_catalog_product->getProductImages($product_id), 0, 5) as $row) {
			$paths[] = $row['image'];
		}

		$data['images'] = array();

		foreach ($paths as $path) {
			$data['images'][] = $this->model_tool_image->resize($path, $w, $h);
		}

		$data['image_w'] = $w;
		$data['image_h'] = $h;
		$data['product_id'] = $product_id;
		$data['name'] = $product['name'];
		$data['model'] = $product['model'];
		$data['href'] = $this->url->link('product/product', 'product_id=' . $product_id);
		$data['price'] = false;
		$data['special'] = false;
		$data['tax'] = false;

		if ($show_price) {
			$data['price'] = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $currency);

			if (!is_null($product['special']) && (float)$product['special'] >= 0) {
				$data['special'] = $this->currency->format($this->tax->calculate($product['special'], $product['tax_class_id'], $this->config->get('config_tax')), $currency);
			}

			if ($this->config->get('config_tax')) {
				$data['tax'] = $this->currency->format((float)(!is_null($product['special']) ? $product['special'] : $product['price']), $currency);
			}
		}

		$data['in_stock'] = (int)$product['quantity'] > 0;
		$data['stock'] = $data['in_stock'] ? $this->language->get('text_instock') : $product['stock_status'];
		$data['minimum'] = $product['minimum'] > 0 ? $product['minimum'] : 1;
		$data['rating'] = $this->config->get('config_review_status') ? (int)$product['rating'] : false;

		// REQ-STK-010: the same stickers as on the card
		$data['stickers'] = array('tl' => array(), 'tr' => array(), 'bl' => array());

		if ($this->config->get('module_ocs_stickers_status')) {
			$this->load->model('extension/ocs_stickers/stickers');

			$map = $this->model_extension_ocs_stickers_stickers->forProducts(array($product_id));

			if (isset($map[$product_id])) {
				$data['stickers'] = array_merge($data['stickers'], $map[$product_id]);
			}
		}

		// options, as on the product page; the types that need page widgets send the visitor to the full page
		// out-of-stock values stay selectable only when pre-order or "notify me" can serve them (REQ-OPT-001)
		$this->load->language('extension/ocs_request/common');

		$data['ocs_text_out'] = $this->language->get('text_out_of_stock');
		$data['options'] = $this->model_extension_ocs_request_options->build($product, (bool)($this->config->get('module_ocs_preorder_status') || $this->config->get('module_ocs_back_in_stock_status')));
		$data['page_only'] = false;

		foreach ($data['options'] as $option) {
			if (in_array($option['type'], $this->pageOnly, true)) {
				$data['page_only'] = true;
			}
		}

		// the request buttons come from the product-page handlers of the request extensions
		$buttons = array('ocs_quick' => array('module_ocs_quick_order_status', 'extension/ocs_quick_order/events/beforeProduct'), 'ocs_preorder' => array('module_ocs_preorder_status', 'extension/ocs_preorder/events/beforeProduct'), 'ocs_subscribe' => array('module_ocs_back_in_stock_status', 'extension/ocs_back_in_stock/events/beforeProduct'));
		$extra = array('ocs_preorder_opt', 'ocs_subscribe_opt');
		$page = array('product_id' => $product_id);

		foreach ($buttons as $key => $def) {
			if ($this->config->get($def[0])) {
				$route = '';
				$action = new Action($def[1]);
				$action->execute($this->registry, array(&$route, &$page));
			}

			$data[$key] = isset($page[$key]) ? $page[$key] : false;
		}

		foreach ($extra as $key) {
			$data[$key] = isset($page[$key]) ? $page[$key] : false;
		}

		foreach (array('text_option', 'text_select', 'text_loading', 'button_upload', 'button_cart', 'entry_qty', 'text_details', 'text_close', 'text_choose_on_page', 'text_model', 'text_tax', 'button_wishlist', 'button_compare') as $key) {
			$data[$key] = $this->language->get($key);
		}

		$data['add_url'] = 'index.php?route=checkout/cart/add';

		$this->response->setOutput($this->load->view('extension/module/ocs_quick_view', $data));
	}
}
