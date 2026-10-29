<?php
// Extension: ocs_order_editor. Admin view event that swaps the stock order form for the single-page editor. Owner: SPEC-122.
// Handlers never throw to the admin user (REQ-ORD-021): on any failure the stock form stays.

class ControllerExtensionOcsOrderEditorEvents extends Controller {
	/**
	 * admin/view/sale/order_form/before: hands the stock controller's data to the editor as one JSON config and swaps the template.
	 * The stock controller keeps preparing every list, the API token and the order data (REQ-ORD-021).
	 */
	public function beforeOrderForm(&$route, &$data) {
		if (!is_array($data) || !$this->config->get('ocs_order_editor_status') || !isset($data['catalog'])) {
			return;
		}

		try {
			$strings = $this->load->language('extension/ocs_order_editor/editor');
			$texts = array();

			if (is_array($strings)) {
				foreach ($strings as $key => $value) {
					if (strpos($key, 'oe_') === 0) {
						$texts[$key] = $value;
					}
				}
			}

			$config = $this->buildConfig($data, $texts);

			$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR;
			$json = json_encode($config, $flags);

			if (!is_string($json) || $json === '') {
				return;
			}

			$data['ocs_oe_config'] = $json;
			$data['ocs_oe_version'] = '1.0.0';
			$data['ocs_oe_cancel'] = isset($texts['oe_cancel']) ? $texts['oe_cancel'] : 'Cancel';
			$data['ocs_oe_title'] = isset($data['text_form']) ? $data['text_form'] : '';
			$route = 'extension/ocs_order_editor/order_form';
		} catch (\Exception $e) {
			$this->log->write('[ocs_order_editor] ' . $e->getMessage());
		}
	}

	private function buildConfig(array $data, array $texts) {
		$get = function ($key, $default = '') use ($data) {
			return isset($data[$key]) && $data[$key] !== null ? $data[$key] : $default;
		};

		$this->load->model('customer/custom_field');

		$custom_fields = array();

		foreach ($this->model_customer_custom_field->getCustomFields(array('sort' => 'cf.sort_order', 'order' => 'ASC')) as $row) {
			if (empty($row['status']) || !in_array($row['location'], array('account', 'address'))) {
				continue;
			}

			$values = array();

			foreach ($this->model_customer_custom_field->getCustomFieldValues($row['custom_field_id']) as $value) {
				$values[] = array('id' => (int)$value['custom_field_value_id'], 'name' => $value['name']);
			}

			$custom_fields[] = array(
				'id'         => (int)$row['custom_field_id'],
				'name'       => $row['name'],
				'type'       => $row['type'],
				'location'   => $row['location'],
				'required'   => !empty($row['required']),
				'value'      => (string)$row['value'],
				'sort_order' => (int)$row['sort_order'],
				'values'     => $values
			);
		}

		$stores = array();

		foreach ($get('stores', array()) as $store) {
			$stores[] = array('id' => (int)$store['store_id'], 'name' => $store['name']);
		}

		$currencies = array();

		foreach ($get('currencies', array()) as $code => $currency) {
			$currencies[] = array('code' => $currency['code'], 'title' => $currency['title']);
		}

		$groups = array();

		foreach ($get('customer_groups', array()) as $group) {
			$groups[] = array('id' => (int)$group['customer_group_id'], 'name' => $group['name']);
		}

		$countries = array();

		foreach ($get('countries', array()) as $country) {
			$countries[] = array('id' => (int)$country['country_id'], 'name' => $country['name']);
		}

		$statuses = array();

		foreach ($get('order_statuses', array()) as $status) {
			$statuses[] = array('id' => (int)$status['order_status_id'], 'name' => $status['name']);
		}

		$themes = array();

		foreach ($get('voucher_themes', array()) as $theme) {
			$themes[] = array('id' => (int)$theme['voucher_theme_id'], 'name' => $theme['name']);
		}

		$addresses = array();

		foreach ($get('addresses', array()) as $address) {
			$addresses[] = $this->addressRow($address);
		}

		$products = array();

		foreach ($get('order_products', array()) as $product) {
			$options = array();

			foreach ((array)$product['option'] as $option) {
				$options[] = array(
					'product_option_id'       => $option['product_option_id'],
					'product_option_value_id' => $option['product_option_value_id'],
					'value'                   => $option['value'],
					'type'                    => $option['type']
				);
			}

			$products[] = array(
				'product_id' => (int)$product['product_id'],
				'quantity'   => (int)$product['quantity'],
				'option'     => $options
			);
		}

		$vouchers = array();

		foreach ($get('order_vouchers', array()) as $voucher) {
			$vouchers[] = array(
				'code'             => $voucher['code'],
				'description'      => $voucher['description'],
				'from_name'        => $voucher['from_name'],
				'from_email'       => $voucher['from_email'],
				'to_name'          => $voucher['to_name'],
				'to_email'         => $voucher['to_email'],
				'voucher_theme_id' => $voucher['voucher_theme_id'],
				'message'          => $voucher['message'],
				'amount'           => $voucher['amount']
			);
		}

		$customer_cf = $get('account_custom_field', $get('customer_custom_field', array()));

		$order = array(
			'order_id'       => (int)$get('order_id', 0),
			'store_id'       => (int)$get('store_id', 0),
			'currency'       => (string)$get('currency_code'),
			'customer'       => (string)$get('customer'),
			'customer_id'    => (string)$get('customer_id'),
			'group_id'       => (int)$get('customer_group_id'),
			'firstname'      => (string)$get('firstname'),
			'lastname'       => (string)$get('lastname'),
			'email'          => (string)$get('email'),
			'telephone'      => (string)$get('telephone'),
			'custom_field'   => is_array($customer_cf) ? $customer_cf : array(),
			'payment'        => $this->orderAddress($data, 'payment'),
			'shipping'       => $this->orderAddress($data, 'shipping'),
			'payment_code'   => (string)$get('payment_code'),
			'shipping_code'  => (string)$get('shipping_code'),
			'products'       => $products,
			'vouchers'       => $vouchers,
			'coupon'         => (string)$get('coupon'),
			'voucher'        => (string)$get('voucher'),
			'reward'         => (string)$get('reward'),
			'status_id'      => (int)$get('order_status_id'),
			'comment'        => (string)$get('comment'),
			'affiliate'      => trim((string)$get('affiliate')),
			'affiliate_id'   => (string)$get('affiliate_id')
		);

		return array(
			'strings'            => $texts,
			'catalog'            => (string)$get('catalog'),
			'api_token'          => (string)$get('api_token'),
			'user_token'         => (string)$get('user_token'),
			'info_url'           => $this->url->link('sale/order/info', 'user_token=' . $this->session->data['user_token'] . '&order_id=', true),
			'list_url'           => (string)$get('cancel'),
			'stores'             => $stores,
			'currencies'         => $currencies,
			'customer_groups'    => $groups,
			'countries'          => $countries,
			'order_statuses'     => $statuses,
			'voucher_themes'     => $themes,
			'voucher_min'        => (string)$get('voucher_min'),
			'default_country_id' => (int)$this->config->get('config_country_id'),
			'custom_fields'      => $custom_fields,
			'addresses'          => $addresses,
			'order'              => $order
		);
	}

	private function orderAddress(array $data, $prefix) {
		$keys = array('firstname', 'lastname', 'company', 'address_1', 'address_2', 'city', 'postcode', 'country_id', 'zone_id');
		$result = array();

		foreach ($keys as $key) {
			$result[$key] = isset($data[$prefix . '_' . $key]) ? (string)$data[$prefix . '_' . $key] : '';
		}

		$custom = isset($data[$prefix . '_custom_field']) && is_array($data[$prefix . '_custom_field']) ? $data[$prefix . '_custom_field'] : array();
		$result['custom_field'] = $custom;

		return $result;
	}

	private function addressRow(array $address) {
		$row = array('address_id' => isset($address['address_id']) ? (int)$address['address_id'] : 0);

		foreach (array('firstname', 'lastname', 'company', 'address_1', 'address_2', 'city', 'postcode', 'country_id', 'zone_id') as $key) {
			$row[$key] = isset($address[$key]) ? (string)$address[$key] : '';
		}

		$row['country'] = isset($address['country']) ? (string)$address['country'] : '';
		$row['custom_field'] = isset($address['custom_field']) && is_array($address['custom_field']) ? $address['custom_field'] : array();

		return $row;
	}
}
