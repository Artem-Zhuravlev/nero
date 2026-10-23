<?php
// Extension: ocs_ajax_search. View event that switches the header search box to the suggestion dropdown. Owner: SPEC-028.
// Handlers never throw to the customer (REQ-EXT-062).

class ControllerExtensionOcsAjaxSearchEvents extends Controller {
	/**
	 * catalog/view/common/search/before
	 */
	public function beforeSearch(&$route, &$data) {
		if (!is_array($data) || !$this->config->get('module_ocs_ajax_search_status') || $this->config->get('config_theme') != 'ocs') {
			return;
		}

		$strings = $this->load->language('extension/module/ocs_ajax_search');
		$strings = is_array($strings) ? $strings : array();

		$keys = array('recent', 'popular', 'clear', 'all', 'empty', 'error', 'products', 'categories', 'brands');
		$texts = array();

		foreach ($keys as $key) {
			$texts[$key] = isset($strings['text_' . $key]) ? $strings['text_' . $key] : '';
		}

		$data['ocs_search'] = array(
			'url'     => $this->url->link('extension/module/ocs_ajax_search/suggest', '', true),
			'page'    => $this->url->link('product/search', '', true),
			'min'     => max(1, min(5, (int)$this->config->get('module_ocs_ajax_search_min_length') ?: 2)),
			'strings' => htmlspecialchars(json_encode($texts), ENT_QUOTES, 'UTF-8')
		);
	}
}
