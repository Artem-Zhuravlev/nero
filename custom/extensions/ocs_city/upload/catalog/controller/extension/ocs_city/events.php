<?php
// Extension: ocs_city. Header city button / prompt and the product page delivery block. Owner: SPEC-032.
// Handlers never throw to the customer (REQ-EXT-062).

class ControllerExtensionOcsCityEvents extends Controller {
	/**
	 * catalog/view/common/header/before
	 */
	public function beforeHeader(&$route, &$data) {
		if (!is_array($data) || !$this->enabled()) {
			return;
		}

		try {
			$this->load->language('extension/module/ocs_city');
			$this->load->model('extension/ocs_city/city');
			$this->load->model('extension/ocs_city/block');

			$current = $this->model_extension_ocs_city_block->currentCity();
			$name = '';

			foreach ($this->model_extension_ocs_city_city->cities() as $row) {
				if ((int)$row['city_id'] === $current) {
					$name = $row['name'];
				}
			}

			if ($name === '') {
				return;
			}

			$data['ocs_city'] = array(
				'name'       => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
				'id'         => $current,
				'select_url' => $this->url->link('extension/module/ocs_city/select', '', true),
				'prompt'     => sprintf($this->language->get('prompt_text'), htmlspecialchars($name, ENT_QUOTES, 'UTF-8')),
				'yes'        => $this->language->get('prompt_yes'),
				'other'      => $this->language->get('prompt_other'),
				'stores'     => $this->url->link('extension/module/ocs_city/stores', '', true),
				'stores_text' => $this->language->get('stores_title'),
				'track'      => $this->config->get('module_ocs_city_tracking') ? $this->url->link('extension/module/ocs_city/track', '', true) : '',
				'track_text' => $this->language->get('track_title')
			);
		} catch (\Exception $e) {
			$this->log->write('[ocs_city] ' . $e->getMessage());
		}
	}

	/**
	 * catalog/view/product/product/before
	 */
	public function beforeProduct(&$route, &$data) {
		if (!is_array($data) || empty($data['product_id']) || !$this->enabled()) {
			return;
		}

		try {
			$this->load->model('extension/ocs_city/block');

			$block = $this->model_extension_ocs_city_block->data((int)$data['product_id'], $this->model_extension_ocs_city_block->currentCity());

			$data['ocs_city_block'] = $block ? $this->load->view('extension/module/ocs_city_block', $block) : '';
		} catch (\Exception $e) {
			$this->log->write('[ocs_city] ' . $e->getMessage());
		}
	}

	private function enabled() {
		return (bool)$this->config->get('module_ocs_city_status') && $this->config->get('config_theme') == 'ocs';
	}
}
