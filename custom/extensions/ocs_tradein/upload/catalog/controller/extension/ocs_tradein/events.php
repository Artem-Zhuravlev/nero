<?php
// Extension: ocs_tradein. Trade-in banner on the product and category pages of the configured categories. Owner: SPEC-036 (REQ-CRD-010).
// The handlers never throw to the customer (REQ-EXT-062).

class ControllerExtensionOcsTradeinEvents extends Controller {
	public function beforeProduct(&$route, &$data) {
		if (!is_array($data) || empty($data['product_id']) || !$this->enabled()) {
			return;
		}

		try {
			$this->load->model('extension/ocs_tradein/lead');

			if ($this->model_extension_ocs_tradein_lead->productInCategories((int)$data['product_id'], $this->categories())) {
				$data['ocs_tradein_banner'] = $this->banner((int)$data['product_id']);
			}
		} catch (\Exception $e) {
			$this->log->write('[ocs_tradein] ' . $e->getMessage());
		}
	}

	public function beforeCategory(&$route, &$data) {
		if (!is_array($data) || !$this->enabled() || empty($this->request->get['path'])) {
			return;
		}

		try {
			$parts = explode('_', (string)$this->request->get['path']);
			$category_id = (int)array_pop($parts);

			$this->load->model('extension/ocs_tradein/lead');

			if ($this->model_extension_ocs_tradein_lead->categoryInCategories($category_id, $this->categories())) {
				$data['ocs_tradein_banner'] = $this->banner(0);
			}
		} catch (\Exception $e) {
			$this->log->write('[ocs_tradein] ' . $e->getMessage());
		}
	}

	private function banner($product_id) {
		$this->load->language('extension/module/ocs_tradein');

		$texts = (array)$this->config->get('module_ocs_tradein_l');
		$texts = isset($texts[$this->config->get('config_language')]) && is_array($texts[$this->config->get('config_language')]) ? $texts[$this->config->get('config_language')] : array();

		$pick = function ($field, $default) use ($texts) {
			$value = isset($texts[$field]) ? trim(html_entity_decode((string)$texts[$field], ENT_QUOTES, 'UTF-8')) : '';

			return htmlspecialchars($value !== '' ? $value : $default, ENT_QUOTES, 'UTF-8');
		};

		return $this->load->view('extension/module/ocs_tradein_banner', array(
			'title'      => $pick('title', $this->language->get('banner_title')),
			'text'       => $pick('text', $this->language->get('banner_text')),
			'button'     => $pick('button', $this->language->get('banner_button')),
			'form'       => $this->url->link('extension/module/ocs_tradein/form', '', true),
			'product_id' => (int)$product_id
		));
	}

	private function categories() {
		return array_slice(array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$this->config->get('module_ocs_tradein_categories'))), function ($v) {
			return $v > 0;
		}))), 0, 100);
	}

	private function enabled() {
		return (bool)$this->config->get('module_ocs_tradein_status') && $this->config->get('config_theme') == 'ocs';
	}
}
