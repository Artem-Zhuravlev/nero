<?php
// Extension: ocs_credit. Renders the instalment block into the product view data. Owner: SPEC-036 (REQ-CRD-001, 002).
// Display only: no payment or order logic. The handler never throws to the customer (REQ-EXT-062).

class ControllerExtensionOcsCreditEvents extends Controller {
	public function beforeProduct(&$route, &$data) {
		if (!is_array($data) || empty($data['product_id']) || !$this->config->get('module_ocs_credit_status') || $this->config->get('config_theme') != 'ocs') {
			return;
		}

		try {
			$plans = array_values((array)$this->config->get('module_ocs_credit_plans'));

			if (!$plans) {
				return;
			}

			$this->load->model('catalog/product');
			$this->load->language('extension/module/ocs_credit');

			$product_id = (int)$data['product_id'];
			$product = $this->model_catalog_product->getProduct($product_id);

			if (!$product || !($this->customer->isLogged() || !$this->config->get('config_customer_price'))) {
				return;
			}

			$base = (float)$this->tax->calculate(!is_null($product['special']) ? $product['special'] : $product['price'], $product['tax_class_id'], $this->config->get('config_tax'));
			$applicable = \Ocs\Credit::plansFor($plans, $base);

			// the block is rendered even when no plan fits now: a chosen option can bring the price into a plan's range
			$code = $this->session->data['currency'];
			$value = (float)$this->currency->getValue($code);
			$dec = (int)$this->currency->getDecimalPlace($code);
			$left = $this->currency->getSymbolLeft($code);
			$right = $this->currency->getSymbolRight($code);
			$dp = $this->language->get('decimal_point');
			$tp = $this->language->get('thousand_point');

			$texts = (array)$this->config->get('module_ocs_credit_l');
			$texts = isset($texts[$this->config->get('config_language')]) && is_array($texts[$this->config->get('config_language')]) ? $texts[$this->config->get('config_language')] : array();
			$terms = isset($texts['terms']) ? trim(html_entity_decode((string)$texts['terms'], ENT_QUOTES, 'UTF-8')) : '';
			$disclaimer = isset($texts['disclaimer']) ? trim(html_entity_decode((string)$texts['disclaimer'], ENT_QUOTES, 'UTF-8')) : '';

			$rows = array();

			foreach ($plans as $i => $plan) {
				$rows[] = array(
					'index'  => $i,
					'bank'   => htmlspecialchars($plan['bank'], ENT_QUOTES, 'UTF-8'),
					'months' => (int)$plan['months'],
					'pay'    => '',
					'show'   => false
				);
			}

			foreach ($applicable as $plan) {
				$rows[$plan['index']]['pay'] = $this->currency->format($plan['payment'], $code);
				$rows[$plan['index']]['show'] = true;
			}

			$js = array(
				'price' => $base,
				'plans' => array(),
				'cur'   => array('value' => $value, 'dec' => $dec, 'left' => $left, 'right' => $right, 'dp' => $dp, 'tp' => $tp),
				'deltas' => $this->deltas($product_id),
				'tpl'   => $this->language->get('note_template')
			);

			foreach ($plans as $plan) {
				$js['plans'][] = array('months' => (int)$plan['months'], 'rate' => (float)$plan['rate'], 'fee' => (float)$plan['fee'], 'min' => (float)$plan['min'], 'max' => (float)$plan['max'], 'bank' => $plan['bank']);
			}

			$data['ocs_credit_block'] = $this->load->view('extension/module/ocs_credit', array(
				'plans'           => $rows,
				'any'             => (bool)$applicable,
				// an HTML attribute: the JSON is entity-encoded as a whole, the browser decodes it before theme.js parses it
				'config_json'     => htmlspecialchars(json_encode($js, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'),
				'terms'           => $terms !== '' ? $terms : '',
				'disclaimer'      => htmlspecialchars($disclaimer !== '' ? $disclaimer : $this->language->get('text_disclaimer'), ENT_QUOTES, 'UTF-8'),
				'text_title'      => $this->language->get('text_title'),
				'text_per_month'  => $this->language->get('text_per_month'),
				'text_months'     => $this->language->get('text_months'),
				'text_details'    => $this->language->get('text_details'),
				'text_close'      => $this->language->get('text_close')
			));
		} catch (\Exception $e) {
			$this->log->write('[ocs_credit] ' . $e->getMessage());
		}
	}

	/**
	 * product_option_value_id => signed price delta in the default currency (tax applied like on the page).
	 */
	private function deltas($product_id) {
		$out = array();
		$product = $this->model_catalog_product->getProduct($product_id);

		foreach ($this->model_catalog_product->getProductOptions($product_id) as $option) {
			if (!isset($option['product_option_value']) || !is_array($option['product_option_value'])) {
				continue;
			}

			foreach ($option['product_option_value'] as $value) {
				if ((float)$value['price'] != 0.0) {
					$delta = (float)$this->tax->calculate($value['price'], $product['tax_class_id'], $this->config->get('config_tax'));

					$out[(int)$value['product_option_value_id']] = $value['price_prefix'] == '-' ? -$delta : $delta;
				}
			}
		}

		return $out;
	}
}
