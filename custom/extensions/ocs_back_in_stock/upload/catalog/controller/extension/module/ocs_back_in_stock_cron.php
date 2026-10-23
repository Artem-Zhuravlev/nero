<?php
// Extension: ocs_back_in_stock. Cron endpoint: notifies the subscribers whose product / option value is back in stock, however the stock
// changed (order cancel, import, option edit). Owner: SPEC-037 (REQ-OPT-002).
// Call it from the server cron, e.g. every 10 minutes: curl -s "https://shop/index.php?route=extension/module/ocs_back_in_stock_cron&key=KEY"

class ControllerExtensionModuleOcsBackInStockCron extends Controller {
	public function index() {
		// a wrong or missing key gets an empty answer: nothing reveals that the endpoint exists
		$given = isset($this->request->get['key']) ? (string)$this->request->get['key'] : '';

		if (!$this->config->get('module_ocs_back_in_stock_status') || !\Ocs\BackInStock::keyValid((string)$this->config->get('module_ocs_back_in_stock_cron_key'), $given)) {
			$this->response->addHeader('HTTP/1.1 404 Not Found');
			$this->response->setOutput('');

			return;
		}

		$sent = \Ocs\BackInStock::run($this->db, DB_PREFIX, $this->config, DIR_LANGUAGE, HTTP_SERVER, 0, 200);

		$this->response->addHeader('Content-Type: text/plain; charset=utf-8');
		$this->response->setOutput('ok ' . (int)$sent);
	}
}
