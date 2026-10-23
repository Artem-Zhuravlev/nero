<?php
// Extension: ocs_back_in_stock. Admin event: after a product is saved with stock > 0, subscribers get one e-mail. Owner: SPEC-025 (REQ-QOR-021).
// Stock changes made by orders or imports do not fire this event; the CLI/cron path belongs to the jobs spec (SPEC-014).
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
		$product = $this->db->query("SELECT p.product_id, p.quantity FROM " . DB_PREFIX . "product p WHERE p.product_id = '" . $product_id . "' AND p.status = '1'")->row;

		if (!$product || (int)$product['quantity'] <= 0) {
			return;
		}

		$rows = $this->db->query("SELECT * FROM " . DB_PREFIX . "ocs_back_in_stock WHERE product_id = '" . $product_id . "' AND status = '0' ORDER BY subscription_id LIMIT 200")->rows;

		if (!$rows || !$this->config->get('config_mail_engine')) {
			return;
		}

		$url = HTTP_CATALOG . 'index.php?route=product/product&product_id=' . $product_id;

		foreach ($rows as $row) {
			// mark first: a failing mail must not be retried on every later save (at-most-once)
			$this->db->query("UPDATE " . DB_PREFIX . "ocs_back_in_stock SET status = '1', date_notified = NOW() WHERE subscription_id = '" . (int)$row['subscription_id'] . "'");

			$strings = $this->strings($row['language_code']);

			$mail = new Mail($this->config->get('config_mail_engine'));
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $this->config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $this->config->get('config_mail_smtp_port');
			$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

			$mail->setTo($row['email']);
			$mail->setFrom($this->config->get('config_email'));
			$mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
			$mail->setSubject(html_entity_decode(sprintf($strings['mail_subject'], $row['product_name']), ENT_QUOTES, 'UTF-8'));
			$mail->setText(sprintf($strings['mail_text'], $row['product_name'], $url));
			$mail->send();
		}
	}

	/**
	 * Mail strings in the subscriber's language, read from the extension's catalog language file.
	 */
	private function strings($code) {
		$code = preg_match('/^[a-z]{2}-[a-z]{2}$/', (string)$code) ? $code : $this->config->get('config_language');

		foreach (array($code, 'uk-ua', 'en-gb') as $candidate) {
			$file = DIR_CATALOG . 'language/' . $candidate . '/extension/module/ocs_back_in_stock.php';

			if (is_file($file)) {
				$_ = array();

				include $file;

				if (isset($_['mail_subject'], $_['mail_text'])) {
					return $_;
				}
			}
		}

		return array('mail_subject' => '%s', 'mail_text' => "%s\n%s");
	}
}
