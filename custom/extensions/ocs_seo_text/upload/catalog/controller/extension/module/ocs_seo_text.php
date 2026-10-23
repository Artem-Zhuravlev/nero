<?php
// Extension: ocs_seo_text. Collapsible SEO text block (text is in the DOM, so crawlers see it). Owner: SPEC-033 (REQ-HOM-040).

class ControllerExtensionModuleOcsSeoText extends Controller {
	public function index() {
		if (!$this->config->get('module_ocs_seo_text_status')) {
			return '';
		}

		$l = (array)$this->config->get('module_ocs_seo_text_l');
		$l = isset($l[$this->config->get('config_language')]) && is_array($l[$this->config->get('config_language')]) ? $l[$this->config->get('config_language')] : array();

		// administrator content, stored entity-encoded by core (same convention as the core HTML module)
		$text = isset($l['text']) ? html_entity_decode((string)$l['text'], ENT_QUOTES, 'UTF-8') : '';

		if (trim(strip_tags($text)) === '') {
			return '';
		}

		$this->load->language('extension/module/ocs_seo_text');

		$data['text'] = $text;
		$data['title'] = !empty($l['title']) ? \Ocs\OrderRequest::e($l['title']) : $this->language->get('text_read_more');

		return $this->load->view('extension/module/ocs_seo_text', $data);
	}
}
