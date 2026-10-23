<?php
// Extension: ocs_request. Creates a real order for one product without touching the visitor's cart. Owner: SPEC-025 (REQ-QOR-002..004).
//
// Prices, options, discounts, tax and stock are calculated by a private Cart\Cart instance (own throw-away session, guest
// customer), i.e. by the same server code as the normal checkout; posted prices are never used.

class ModelExtensionOcsRequestOrder extends Model {
	/**
	 * @param array  $contact       clean contact (name, phone, email, comment)
	 * @param int    $product_id
	 * @param int    $quantity
	 * @param array  $options       product_option_id => value (as posted by the product page "option" fields)
	 * @param int    $status_id     initial order status
	 * @param string $method_title  shown as payment method in admin
	 * @param bool   $check_stock    false for pre-orders (the product is out of stock by definition)
	 * @return int order_id
	 * @throws \Exception message is a machine code: product, options, stock, minimum
	 */
	public function create($contact, $product_id, $quantity, $options, $status_id, $method_title, $check_stock = true) {
		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct((int)$product_id);

		if (!$product_info) {
			throw new \Exception('product');
		}

		$quantity = max((int)$quantity, $product_info['minimum'] > 0 ? (int)$product_info['minimum'] : 1);

		$clean_options = array();

		foreach ($this->model_catalog_product->getProductOptions((int)$product_id) as $product_option) {
			$id = $product_option['product_option_id'];

			if (isset($options[$id]) && $options[$id] !== '' && $options[$id] !== array()) {
				$clean_options[$id] = $options[$id];
			} elseif ($product_option['required']) {
				throw new \Exception('options');
			}
		}

		$registry = new \Registry();
		$registry->set('config', $this->config);
		$registry->set('db', $this->db);
		$registry->set('tax', $this->tax);
		$registry->set('weight', $this->weight);
		$registry->set('customer', new \Ocs\IsolatedCustomer());
		$registry->set('session', new \Ocs\IsolatedSession());

		$cart = new \Cart\Cart($registry);

		try {
			$cart->add((int)$product_id, $quantity, $clean_options);

			if (!$cart->hasProducts()) {
				throw new \Exception('product');
			}

			if ($check_stock && !$cart->hasStock() && !$this->config->get('config_stock_checkout')) {
				throw new \Exception('stock');
			}

			$order_id = $this->save($cart, $contact, (int)$status_id, $method_title);
		} catch (\Exception $e) {
			$cart->clear();

			throw $e;
		}

		$cart->clear();

		return $order_id;
	}

	private function save($cart, $contact, $status_id, $method_title) {
		$this->load->model('checkout/order');
		$this->load->language('extension/total/sub_total');
		$this->load->language('extension/total/total');

		$products = array();

		foreach ($cart->getProducts() as $product) {
			$option_data = array();

			foreach ($product['option'] as $option) {
				$option_data[] = array(
					'product_option_id'       => $option['product_option_id'],
					'product_option_value_id' => $option['product_option_value_id'],
					'option_id'               => $option['option_id'],
					'option_value_id'         => $option['option_value_id'],
					'name'                    => $option['name'],
					'value'                   => $option['value'],
					'type'                    => $option['type']
				);
			}

			$products[] = array(
				'product_id' => $product['product_id'],
				'name'       => $product['name'],
				'model'      => $product['model'],
				'option'     => $option_data,
				'download'   => $product['download'],
				'quantity'   => $product['quantity'],
				'subtract'   => $product['subtract'],
				'price'      => $product['price'],
				'total'      => $product['total'],
				'tax'        => $this->tax->getTax($product['price'], $product['tax_class_id']),
				'reward'     => $product['reward']
			);
		}

		$sub_total = $cart->getSubTotal();
		$total = $sub_total;

		$totals = array(
			array('code' => 'sub_total', 'title' => $this->language->get('text_sub_total'), 'value' => $sub_total, 'sort_order' => (int)$this->config->get('total_sub_total_sort_order'))
		);

		foreach ($cart->getTaxes() as $tax_rate_id => $value) {
			if ($value > 0) {
				$totals[] = array('code' => 'tax', 'title' => $this->tax->getRateName($tax_rate_id), 'value' => $value, 'sort_order' => (int)$this->config->get('total_tax_sort_order'));
				$total += $value;
			}
		}

		$totals[] = array('code' => 'total', 'title' => $this->language->get('text_total'), 'value' => $total, 'sort_order' => (int)$this->config->get('total_total_sort_order'));

		$store_url = $this->config->get('config_store_id') ? $this->config->get('config_url') : (isset($this->request->server['HTTPS']) && $this->request->server['HTTPS'] ? HTTPS_SERVER : HTTP_SERVER);

		$email = !empty($contact['email']) ? $contact['email'] : $this->config->get('config_email');

		$ip = isset($this->request->server['REMOTE_ADDR']) ? $this->request->server['REMOTE_ADDR'] : '';

		$order_data = array(
			'invoice_prefix'          => $this->config->get('config_invoice_prefix'),
			'store_id'                => $this->config->get('config_store_id'),
			'store_name'              => $this->config->get('config_name'),
			'store_url'               => $store_url,
			'customer_id'             => 0,
			'customer_group_id'       => $this->config->get('config_customer_group_id'),
			'firstname'               => $contact['name'],
			'lastname'                => '',
			'email'                   => $email,
			'telephone'               => $contact['phone'],
			'custom_field'            => array(),
			'payment_firstname'       => $contact['name'],
			'payment_lastname'        => '',
			'payment_company'         => '',
			'payment_address_1'       => '',
			'payment_address_2'       => '',
			'payment_city'            => '',
			'payment_postcode'        => '',
			'payment_zone'            => '',
			'payment_zone_id'         => 0,
			'payment_country'         => '',
			'payment_country_id'      => 0,
			'payment_address_format'  => '',
			'payment_custom_field'    => array(),
			'payment_method'          => $method_title,
			'payment_code'            => 'ocs_request',
			'shipping_firstname'      => '',
			'shipping_lastname'       => '',
			'shipping_company'        => '',
			'shipping_address_1'      => '',
			'shipping_address_2'      => '',
			'shipping_city'           => '',
			'shipping_postcode'       => '',
			'shipping_zone'           => '',
			'shipping_zone_id'        => 0,
			'shipping_country'        => '',
			'shipping_country_id'     => 0,
			'shipping_address_format' => '',
			'shipping_custom_field'   => array(),
			'shipping_method'         => '',
			'shipping_code'           => '',
			'products'                => $products,
			'vouchers'                => array(),
			'totals'                  => $totals,
			'comment'                 => $contact['comment'],
			'total'                   => $total,
			'affiliate_id'            => 0,
			'commission'              => 0,
			'marketing_id'            => 0,
			'tracking'                => '',
			'language_id'             => $this->config->get('config_language_id'),
			'currency_id'             => $this->currency->getId($this->session->data['currency']),
			'currency_code'           => $this->session->data['currency'],
			'currency_value'          => $this->currency->getValue($this->session->data['currency']),
			'ip'                      => $ip,
			'forwarded_ip'            => '',
			'user_agent'              => isset($this->request->server['HTTP_USER_AGENT']) ? $this->request->server['HTTP_USER_AGENT'] : '',
			'accept_language'         => isset($this->request->server['HTTP_ACCEPT_LANGUAGE']) ? $this->request->server['HTTP_ACCEPT_LANGUAGE'] : ''
		);

		$order_id = $this->model_checkout_order->addOrder($order_data);

		return (int)$order_id;
	}

	/**
	 * Moves the order out of "missing" (status 0) into the initial status. Core sends the order mails here, which can be slow
	 * or fail: call this after the response has been sent; a failure is logged and never reaches the customer.
	 */
	public function confirm($order_id, $status_id) {
		$this->load->model('checkout/order');

		ob_start();

		try {
			$this->model_checkout_order->addOrderHistory((int)$order_id, (int)$status_id, '', false);
		} catch (\Exception $e) {
			$this->log->write('[ocs_request] ' . $e->getMessage());
		}

		ob_end_clean();
	}
}
