<?php
// Extension: ocs_back_in_stock. Admin event: after a product is saved with stock > 0, subscribers get one e-mail. Owner: SPEC-025 (REQ-QOR-021).
// Stock changes made by orders or imports do not fire this event: the cron endpoint of the module covers them (SPEC-037).
// Handlers never throw to the admin user (REQ-EXT-062).

class ControllerExtensionOcsBackInStockEvents extends Controller {
	/**
	 * admin/model/catalog/product/editProduct/after: $args = array(product_id, data)
	 */
	public function afterEditProduct(&$route, &$args, &$output) {
		if (!$this->config->get('module_ocs_back_in_stock_status') || !is_array($args) || empty($args[0])) {
			return;
		}

		try {
			$this->notify((int)$args[0]);
		} catch (\Exception $e) {
			$this->log->write('[ocs_back_in_stock] ' . $e->getMessage());
		}
	}

	private function notify($product_id) {
		// the shared routine also serves the cron endpoint (SPEC-037 REQ-OPT-002): quantity and chosen option values are both checked
		\Ocs\BackInStock::run($this->db, DB_PREFIX, $this->config, DIR_CATALOG . 'language/', HTTP_CATALOG, $product_id);
	}
}
