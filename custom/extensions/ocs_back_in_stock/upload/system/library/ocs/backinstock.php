<?php
// Extension: ocs_back_in_stock. Who is ready to be notified, and the mailing itself. Shared by the admin save event and the cron
// endpoint (any stock change route). Owner: SPEC-037 (REQ-OPT-002, REQ-OPT-003).

namespace Ocs;

class BackInStock {
	/**
	 * Is a subscription ready? The product must be in stock and every option value of the subscription must be in stock.
	 *
	 * @param int    $quantity product quantity
	 * @param string $optionKey comma separated product_option_value ids ('' = the product itself)
	 * @param array  $out       product_option_value_id => true for values that are out of stock
	 */
	public static function ready($quantity, $optionKey, $out) {
		if ((int)$quantity <= 0) {
			return false;
		}

		foreach (array_filter(array_map('intval', explode(',', (string)$optionKey))) as $id) {
			if (!empty($out[$id])) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Sends the notifications that are due. Each subscription is marked before its mail goes out (at most once, REQ-OPT-003).
	 *
	 * @param object $db         core DB
	 * @param string $prefix     table prefix
	 * @param object $config     core config
	 * @param string $langDir    catalog language directory (DIR_CATALOG . 'language/')
	 * @param string $siteUrl    catalog base URL
	 * @param int    $productId  0 = every product with waiting subscriptions
	 * @param int    $limit      mails per run
	 * @return int number of mails sent
	 */
	public static function run($db, $prefix, $config, $langDir, $siteUrl, $productId = 0, $limit = 200) {
		if (!$config->get('config_mail_engine')) {
			return 0;
		}

		$sql = "SELECT s.*, p.quantity FROM `" . $prefix . "ocs_back_in_stock` s INNER JOIN `" . $prefix . "product` p ON (p.product_id = s.product_id AND p.status = '1') WHERE s.status = '0'";

		if ($productId) {
			$sql .= " AND s.product_id = '" . (int)$productId . "'";
		}

		$rows = $db->query($sql . " ORDER BY s.subscription_id ASC LIMIT " . (int)$limit)->rows;
		$outCache = array();
		$sent = 0;

		foreach ($rows as $row) {
			$pid = (int)$row['product_id'];

			if (!isset($outCache[$pid])) {
				$outCache[$pid] = array();

				foreach ($db->query("SELECT product_option_value_id, subtract, quantity FROM `" . $prefix . "product_option_value` WHERE product_id = '" . $pid . "'")->rows as $v) {
					if (Stock::optionOut($v['subtract'], $v['quantity'])) {
						$outCache[$pid][(int)$v['product_option_value_id']] = true;
					}
				}
			}

			if (!self::ready($row['quantity'], $row['option_key'], $outCache[$pid])) {
				continue;
			}

			$db->query("UPDATE `" . $prefix . "ocs_back_in_stock` SET status = '1', date_notified = NOW() WHERE subscription_id = '" . (int)$row['subscription_id'] . "' AND status = '0'");

			// another run took it between the SELECT and the UPDATE
			if (!$db->countAffected()) {
				continue;
			}

			$strings = self::strings($row['language_code'], $config, $langDir);

			try {
				$mail = new \Mail($config->get('config_mail_engine'));
				$mail->parameter = $config->get('config_mail_parameter');
				$mail->smtp_hostname = $config->get('config_mail_smtp_hostname');
				$mail->smtp_username = $config->get('config_mail_smtp_username');
				$mail->smtp_password = html_entity_decode($config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
				$mail->smtp_port = $config->get('config_mail_smtp_port');
				$mail->smtp_timeout = $config->get('config_mail_smtp_timeout');

				$mail->setTo($row['email']);
				$mail->setFrom($config->get('config_email'));
				$mail->setSender(html_entity_decode($config->get('config_name'), ENT_QUOTES, 'UTF-8'));
				$mail->setSubject(html_entity_decode(sprintf($strings['mail_subject'], $row['product_name']), ENT_QUOTES, 'UTF-8'));
				$mail->setText(sprintf($strings['mail_text'], $row['product_name'], $siteUrl . 'index.php?route=product/product&product_id=' . $pid));
				$mail->send();

				$sent++;
			} catch (\Exception $e) {
				// marked as notified already: a failing mail is not retried on every run
			}
		}

		return $sent;
	}

	/**
	 * Mail strings in the subscriber's language, from the extension's catalog language file.
	 */
	public static function strings($code, $config, $langDir) {
		$code = preg_match('/^[a-z]{2}-[a-z]{2}$/', (string)$code) ? $code : $config->get('config_language');

		foreach (array($code, 'uk-ua', 'en-gb') as $candidate) {
			$file = $langDir . $candidate . '/extension/module/ocs_back_in_stock.php';

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

	/**
	 * Cron access: a long random key from the settings, compared in constant time.
	 */
	public static function keyValid($configured, $given) {
		return is_string($configured) && strlen($configured) >= 16 && is_string($given) && hash_equals($configured, $given);
	}
}
