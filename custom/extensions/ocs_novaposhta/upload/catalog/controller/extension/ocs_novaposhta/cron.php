<?php
// Extension: ocs_novaposhta. HTTP entry for cron on hosts without CLI: one portion of one directory per call. Owner: SPEC-085.
//   index.php?route=extension/ocs_novaposhta/cron&entity=areas|cities|warehouses&key=<cron token>
// Calls are repeatable: an interrupted directory continues from its cursor, so a cron line may simply fire every few minutes until it reports "done".

class ControllerExtensionOcsNovaposhtaCron extends Controller {
	public function index() {
		$entity = isset($this->request->get['entity']) ? (string)$this->request->get['entity'] : '';
		$key = isset($this->request->get['key']) ? (string)$this->request->get['key'] : '';
		$token = (string)$this->config->get('shipping_ocs_novaposhta_cron_token');

		$this->response->addHeader('Content-Type: application/json');
		$this->response->addHeader('Cache-Control: no-store');

		if (!$this->config->get('shipping_ocs_novaposhta_status') || $token === '' || !hash_equals($token, $key)) {
			$this->response->addHeader('HTTP/1.1 403 Forbidden');
			$this->response->setOutput(json_encode(array('status' => 'forbidden')));

			return;
		}

		if (!isset(\Ocs\Novaposhta\Sync::$tables[$entity])) {
			$this->response->addHeader('HTTP/1.1 400 Bad Request');
			$this->response->setOutput(json_encode(array('status' => 'bad_entity')));

			return;
		}

		@set_time_limit(70);

		$sync = new \Ocs\Novaposhta\Sync(
			new \Ocs\Novaposhta\OcDb($this->db),
			DB_PREFIX,
			new \Ocs\Novaposhta\Client($this->config->get('shipping_ocs_novaposhta_api_key')),
			array('limit' => (int)$this->config->get('shipping_ocs_novaposhta_batch_size'), 'pause_ms' => (int)$this->config->get('shipping_ocs_novaposhta_pause_ms'))
		);

		$result = $sync->run($entity, 50);

		$this->response->setOutput(json_encode(array('entity' => $entity, 'status' => $result['status'], 'rows' => $result['rows'], 'page' => $result['page'], 'error' => $result['error'])));
	}
}
