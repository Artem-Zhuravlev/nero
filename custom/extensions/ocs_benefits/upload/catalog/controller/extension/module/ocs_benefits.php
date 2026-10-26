<?php
// Extension: ocs_benefits. Strip with up to four benefits (delivery, warranty, returns, support). Owner: SPEC-038 (REQ-LAY-006).
// Without settings the four default benefits of the language file are shown.

class ControllerExtensionModuleOcsBenefits extends Controller {
	public function index() {
		if (!$this->config->get('module_ocs_benefits_status')) {
			return '';
		}

		$strings = $this->load->language('extension/module/ocs_benefits');
		$strings = is_array($strings) ? $strings : array();
		$code = $this->config->get('config_language');
		$data['items'] = array();

		foreach (array_slice(array_values((array)$this->config->get('module_ocs_benefits_items')), 0, 4) as $item) {
			$l = isset($item['l'][$code]) && is_array($item['l'][$code]) ? $item['l'][$code] : array();
			$title = isset($l['title']) ? trim(html_entity_decode((string)$l['title'], ENT_QUOTES, 'UTF-8')) : '';

			if ($title === '') {
				continue;
			}

			$data['items'][] = array(
				'icon'  => \Ocs\Icons::symbol(preg_match('/^[a-z0-9-]{1,32}$/', isset($item['icon']) ? $item['icon'] : '') ? $item['icon'] : 'check'),
				'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
				'text'  => htmlspecialchars(isset($l['text']) ? trim(html_entity_decode((string)$l['text'], ENT_QUOTES, 'UTF-8')) : '', ENT_QUOTES, 'UTF-8')
			);
		}

		if (!$data['items']) {
			foreach (array('delivery' => 'truck', 'warranty' => 'shield', 'returns' => 'undo', 'support' => 'headphones') as $key => $icon) {
				$data['items'][] = array('icon' => \Ocs\Icons::symbol($icon), 'title' => $this->language->get('default_' . $key . '_title'), 'text' => $this->language->get('default_' . $key . '_text'));
			}
		}

		return $this->load->view('extension/module/ocs_benefits', $data);
	}
}
