<?php
// Extension: ocs_stickers. Admin events: "Stickers" tab in the product form and saving of its checkboxes. Owner: SPEC-029 (REQ-STK-003).
// Handlers never throw to the admin user (REQ-EXT-062).

class ControllerExtensionOcsStickersEvents extends Controller {
	/**
	 * admin/view/catalog/product_form/before: tab link and pane (the OCMOD puts the two variables into the core template).
	 */
	public function beforeProductForm(&$route, &$data) {
		if (!is_array($data) || !$this->config->get('module_ocs_stickers_status')) {
			return;
		}

		try {
			$strings = $this->load->language('extension/ocs_stickers/tab');
			$strings = is_array($strings) ? $strings : array();

			$this->load->model('extension/ocs_stickers/sticker');

			$product_id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;
			$assigned = $product_id ? $this->model_extension_ocs_stickers_sticker->getProductStickers($product_id) : array();

			if (isset($this->request->post['ocs_sticker_ids'])) {
				$assigned = array_map('intval', (array)$this->request->post['ocs_sticker_ids']);
			}

			$stickers = array();

			foreach ($this->model_extension_ocs_stickers_sticker->getStickers() as $row) {
				$stickers[] = array(
					'sticker_id' => $row['sticker_id'],
					'name'       => $row['name'],
					'fg'         => \Ocs\Stickers::safeColor($row['text_color'], '#ffffff'),
					'bg'         => \Ocs\Stickers::safeColor($row['bg_color'], '#1d1d1f'),
					'checked'    => in_array((int)$row['sticker_id'], $assigned, true),
					'auto'       => $row['rule_type'] !== 'manual'
				);
			}

			$title = isset($strings['ocs_tab_title']) ? $strings['ocs_tab_title'] : 'Stickers';

			$data['ocs_stickers_tab_link'] = '<li><a href="#tab-ocs-stickers" data-toggle="tab">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</a></li>';
			$data['ocs_stickers_tab_pane'] = $this->load->view('extension/ocs_stickers/tab', array(
				'stickers'      => $stickers,
				'ocs_text_help' => isset($strings['ocs_text_help']) ? $strings['ocs_text_help'] : '',
				'ocs_text_auto' => isset($strings['ocs_text_auto']) ? $strings['ocs_text_auto'] : '',
				'ocs_text_none' => isset($strings['ocs_text_none']) ? $strings['ocs_text_none'] : ''
			));
		} catch (\Exception $e) {
			$this->log->write('[ocs_stickers] ' . $e->getMessage());
		}
	}

	/**
	 * admin/model/catalog/product/addProduct/after: $output is the new product id.
	 */
	public function afterAddProduct(&$route, &$args, &$output) {
		$this->save((int)$output);
	}

	/**
	 * admin/model/catalog/product/editProduct/after: $args = array(product_id, data).
	 */
	public function afterEditProduct(&$route, &$args, &$output) {
		$this->save(is_array($args) && isset($args[0]) ? (int)$args[0] : 0);
	}

	private function save($product_id) {
		// only the product form sends the marker; other callers of the model (imports) leave the assignments alone
		if ($product_id < 1 || !$this->config->get('module_ocs_stickers_status') || empty($this->request->post['ocs_stickers_present']) || !$this->user->hasPermission('modify', 'extension/module/ocs_stickers')) {
			return;
		}

		try {
			$this->load->model('extension/ocs_stickers/sticker');

			$this->model_extension_ocs_stickers_sticker->setProductStickers($product_id, isset($this->request->post['ocs_sticker_ids']) ? (array)$this->request->post['ocs_sticker_ids'] : array());
		} catch (\Exception $e) {
			$this->log->write('[ocs_stickers] ' . $e->getMessage());
		}
	}
}
