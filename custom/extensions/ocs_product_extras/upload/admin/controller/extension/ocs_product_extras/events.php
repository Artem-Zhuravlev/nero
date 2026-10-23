<?php
// Extension: ocs_product_extras. Admin events: "Accessories" tab in the product form, saving and cleanup. Owner: SPEC-035 (REQ-PRD-020).
// Handlers never throw to the admin user (REQ-EXT-062).

class ControllerExtensionOcsProductExtrasEvents extends Controller {
	public function beforeProductForm(&$route, &$data) {
		if (!is_array($data) || !$this->config->get('module_ocs_product_extras_status')) {
			return;
		}

		try {
			$strings = $this->load->language('extension/ocs_product_extras/tab');
			$strings = is_array($strings) ? $strings : array();

			$this->load->model('extension/ocs_product_extras/extras');

			$model = $this->model_extension_ocs_product_extras_extras;
			$product_id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;
			$ids = $product_id ? $model->getAccessoryIds($product_id) : array();

			if (isset($this->request->post['ocs_accessory_ids'])) {
				$ids = \Ocs\ProductExtras::ids($this->request->post['ocs_accessory_ids']);
			}

			$title = isset($strings['ocs_tab_title']) ? $strings['ocs_tab_title'] : 'Accessories';

			$accessories = array();

			foreach ($model->getProductNames($ids) as $id => $name) {
				$accessories[] = htmlspecialchars('#' . $id . ' ' . $name, ENT_QUOTES, 'UTF-8');
			}

			$data['ocs_extras_tab_link'] = '<li><a href="#tab-ocs-extras" data-toggle="tab">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</a></li>';
			$data['ocs_extras_tab_pane'] = $this->load->view('extension/ocs_product_extras/tab', array(
				'ids'            => implode(', ', $ids),
				'accessories'    => $accessories,
				'ocs_text_help'  => isset($strings['ocs_text_help']) ? $strings['ocs_text_help'] : '',
				'ocs_entry_ids'  => isset($strings['ocs_entry_ids']) ? $strings['ocs_entry_ids'] : ''
			));
		} catch (\Exception $e) {
			$this->log->write('[ocs_product_extras] ' . $e->getMessage());
		}
	}

	public function afterAddProduct(&$route, &$args, &$output) {
		$this->save((int)$output);
	}

	public function afterEditProduct(&$route, &$args, &$output) {
		$this->save(is_array($args) && isset($args[0]) ? (int)$args[0] : 0);
	}

	/**
	 * admin/model/catalog/product/deleteProduct/before: $args[0] is the product id.
	 */
	public function beforeDeleteProduct(&$route, &$args) {
		if (!is_array($args) || empty($args[0])) {
			return;
		}

		try {
			$this->load->model('extension/ocs_product_extras/extras');

			$this->model_extension_ocs_product_extras_extras->deleteProduct((int)$args[0]);
		} catch (\Exception $e) {
			$this->log->write('[ocs_product_extras] ' . $e->getMessage());
		}
	}

	private function save($product_id) {
		// only the product form sends the marker; other callers of the model (imports) leave the links alone
		if ($product_id < 1 || !$this->config->get('module_ocs_product_extras_status') || empty($this->request->post['ocs_extras_present']) || !$this->user->hasPermission('modify', 'extension/module/ocs_product_extras')) {
			return;
		}

		try {
			$this->load->model('extension/ocs_product_extras/extras');

			$this->model_extension_ocs_product_extras_extras->setAccessories($product_id, \Ocs\ProductExtras::ids(isset($this->request->post['ocs_accessory_ids']) ? $this->request->post['ocs_accessory_ids'] : ''));
		} catch (\Exception $e) {
			$this->log->write('[ocs_product_extras] ' . $e->getMessage());
		}
	}
}
