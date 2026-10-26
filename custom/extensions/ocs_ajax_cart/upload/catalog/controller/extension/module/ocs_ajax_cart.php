<?php
// Extension: ocs_ajax_cart. JSON endpoints behind the cart side panel: panel, update, remove. Owner: SPEC-026 (REQ-CRT-001..006).
// Adding goes through core checkout/cart/add (theme.js opens the panel afterwards). Every price and total is computed on the
// server by the same cart code as the cart page; the browser only sends a cart line key and a quantity.

class ControllerExtensionModuleOcsAjaxCart extends Controller {
	/**
	 * GET [&added=product_id] [&counter=1]: panel HTML plus the numbers for the header.
	 */
	public function panel() {
		$this->json($this->state(isset($this->request->get['added']) ? (int)$this->request->get['added'] : 0, !empty($this->request->get['counter'])));
	}

	/**
	 * POST key, quantity: sets a line quantity (0 removes the line).
	 */
	public function update() {
		if ($this->request->server['REQUEST_METHOD'] == 'POST' && isset($this->request->post['key']) && isset($this->request->post['quantity'])) {
			$key = (string)$this->request->post['key'];
			$quantity = (int)$this->request->post['quantity'];

			if ($this->hasLine($key)) {
				if ($quantity < 1) {
					$this->cart->remove($key);
				} else {
					$this->cart->update($key, min($quantity, 9999));
				}

				$this->resetCheckoutState();
			}
		}

		$this->json($this->state(0, false));
	}

	/**
	 * POST key: removes a line.
	 */
	public function remove() {
		if ($this->request->server['REQUEST_METHOD'] == 'POST' && isset($this->request->post['key']) && $this->hasLine((string)$this->request->post['key'])) {
			$this->cart->remove((string)$this->request->post['key']);

			$this->resetCheckoutState();
		}

		$this->json($this->state(0, false));
	}

	private function hasLine($key) {
		foreach ($this->cart->getProducts() as $product) {
			if ((string)$product['cart_id'] === $key) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Same cleanup as core after a cart change: shipping/payment choices depend on the cart.
	 */
	private function resetCheckoutState() {
		unset($this->session->data['shipping_method']);
		unset($this->session->data['shipping_methods']);
		unset($this->session->data['payment_method']);
		unset($this->session->data['payment_methods']);
		unset($this->session->data['reward']);
	}

	private function state($added, $counter_only) {
		if (!$this->config->get('module_ocs_ajax_cart_status')) {
			return array('disabled' => true);
		}

		// the module file is loaded last so its own strings win over same-named keys of common/cart
		$this->load->language('common/cart');
		$this->load->language('extension/module/ocs_ajax_cart');

		list($totals, $total) = $this->totals();

		$count = $this->cart->countProducts() + (isset($this->session->data['vouchers']) ? count($this->session->data['vouchers']) : 0);

		$ids = array();

		foreach ($this->cart->getProducts() as $line) {
			$ids[(int)$line['product_id']] = (int)$line['product_id'];
		}

		$out = array(
			'count'      => $count,
			'ids'        => array_values($ids),
			'total_text' => sprintf($this->language->get('text_items'), $count, $this->currency->format($total, $this->session->data['currency']))
		);

		if ($counter_only) {
			return $out;
		}

		$this->load->model('tool/image');

		$lines = array();
		$blocked = false;

		foreach ($this->cart->getProducts() as $product) {
			$options = array();

			foreach ($product['option'] as $option) {
				$value = $option['value'];

				if ($option['type'] == 'file') {
					$value = utf8_substr($value, 0, utf8_strrpos($value, '.'));
				}

				$options[] = $option['name'] . ': ' . (utf8_strlen($value) > 30 ? utf8_substr($value, 0, 28) . '..' : $value);
			}

			$unit = ($this->customer->isLogged() || !$this->config->get('config_customer_price')) ? $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')) : false;

			$problem = '';

			if (!$product['stock']) {
				$problem = $this->language->get('error_stock');
			} elseif ($product['minimum'] > $product['quantity'] && $product['minimum'] > 1) {
				$problem = sprintf($this->language->get('error_minimum'), $product['minimum']);
			}

			$blocked = $blocked || $problem !== '';

			$lines[] = array(
				'key'      => $product['cart_id'],
				'name'     => $product['name'],
				'href'     => $this->url->link('product/product', 'product_id=' . $product['product_id']),
				'thumb'    => $this->model_tool_image->resize($product['image'] ? $product['image'] : 'placeholder.png', 80, 80),
				'options'  => $options,
				'quantity' => $product['quantity'],
				'price'    => $unit !== false ? $this->currency->format($unit, $this->session->data['currency']) : false,
				'total'    => $unit !== false ? $this->currency->format($unit * $product['quantity'], $this->session->data['currency']) : false,
				'problem'  => $problem,
				'added'    => $added && (int)$product['product_id'] === $added
			);
		}

		$data = array(
			'lines'         => $lines,
			'totals'        => $totals,
			'checkout'      => $this->url->link('checkout/checkout', '', true),
			'cart'          => $this->url->link('checkout/cart'),
			'blocked'       => $blocked,
			'heading_cart'  => $this->language->get('heading_cart'),
			'text_empty'    => $this->language->get('text_empty'),
			'text_continue' => $this->language->get('text_continue'),
			'text_checkout' => $this->language->get('text_checkout'),
			'text_cart'     => $this->language->get('text_cart'),
			'text_remove'   => $this->language->get('text_remove'),
			'text_minus'    => $this->language->get('text_minus'),
			'text_plus'     => $this->language->get('text_plus'),
			'text_qty'      => $this->language->get('text_qty'),
			'text_close'    => $this->language->get('text_close')
		);

		$out['html'] = $this->load->view('extension/module/ocs_ajax_cart', $data);
		$out['empty'] = !$lines;

		return $out;
	}

	/**
	 * Totals exactly as core common/cart computes them.
	 *
	 * @return array array(rows, total)
	 */
	private function totals() {
		$this->load->model('setting/extension');

		$totals = array();
		$taxes = $this->cart->getTaxes();
		$total = 0;

		$total_data = array(
			'totals' => &$totals,
			'taxes'  => &$taxes,
			'total'  => &$total
		);

		if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
			$sort_order = array();

			$results = $this->model_setting_extension->getExtensions('total');

			foreach ($results as $key => $value) {
				$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
			}

			array_multisort($sort_order, SORT_ASC, $results);

			foreach ($results as $result) {
				if ($this->config->get('total_' . $result['code'] . '_status')) {
					$this->load->model('extension/total/' . $result['code']);

					$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
				}
			}

			$sort_order = array();

			foreach ($totals as $key => $value) {
				$sort_order[$key] = $value['sort_order'];
			}

			array_multisort($sort_order, SORT_ASC, $totals);
		}

		$rows = array();

		foreach ($totals as $row) {
			$rows[] = array('title' => $row['title'], 'text' => $this->currency->format($row['value'], $this->session->data['currency']));
		}

		return array($rows, $total);
	}

	private function json($payload) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($payload));
	}
}
