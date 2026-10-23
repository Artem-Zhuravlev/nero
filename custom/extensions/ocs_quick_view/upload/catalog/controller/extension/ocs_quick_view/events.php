<?php
// Extension: ocs_quick_view. Tells the theme where the quick view is (body attributes). Owner: SPEC-037 (REQ-QVW-003).
// The handler never throws to the customer (REQ-EXT-062).

class ControllerExtensionOcsQuickViewEvents extends Controller {
	public function beforeHeader(&$route, &$data) {
		if (!is_array($data) || !$this->config->get('module_ocs_quick_view_status') || $this->config->get('config_theme') != 'ocs') {
			return;
		}

		try {
			$this->load->language('extension/module/ocs_quick_view');

			$data['ocs_qv'] = array(
				'url'   => $this->url->link('extension/module/ocs_quick_view/view', '', true),
				'label' => $this->language->get('text_quick_view'),
				'close' => $this->language->get('text_close')
			);
		} catch (\Exception $e) {
			$this->log->write('[ocs_quick_view] ' . $e->getMessage());
		}
	}
}
