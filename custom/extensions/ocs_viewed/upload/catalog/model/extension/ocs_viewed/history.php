<?php
// Extension: ocs_viewed. The visitor's history: first-party cookie for everybody, table rows for logged-in customers. Owner: SPEC-030.

class ModelExtensionOcsViewedHistory extends Model {
	const COOKIE = 'ocs_viewed';

	/**
	 * @return int[] ids from the browser cookie, newest first
	 */
	public function cookieIds() {
		return \Ocs\Viewed::parse(isset($this->request->cookie[self::COOKIE]) ? $this->request->cookie[self::COOKIE] : '');
	}

	/**
	 * Records a view: cookie always, account table when logged in (the browser list is merged into the account once per session).
	 */
	public function record($product_id) {
		$list = \Ocs\Viewed::push($this->cookieIds(), $product_id);

		$this->writeCookie($list);

		if ($this->customer->isLogged()) {
			$customer_id = (int)$this->customer->getId();

			$this->mergeCookie($customer_id);

			$this->db->query("REPLACE INTO " . DB_PREFIX . "ocs_viewed SET customer_id = '" . $customer_id . "', product_id = '" . (int)$product_id . "', viewed_at = NOW()");

			$this->trim($customer_id);
		}
	}

	/**
	 * Ids to show: account history for logged-in customers (merged with the browser list), otherwise the cookie list.
	 *
	 * @return int[]
	 */
	public function ids($exclude, $limit) {
		$list = $this->cookieIds();

		if ($this->customer->isLogged()) {
			$customer_id = (int)$this->customer->getId();

			$this->mergeCookie($customer_id);

			$account = array();

			foreach ($this->db->query("SELECT product_id FROM " . DB_PREFIX . "ocs_viewed WHERE customer_id = '" . $customer_id . "' ORDER BY viewed_at DESC, product_id DESC LIMIT " . \Ocs\Viewed::MAX)->rows as $row) {
				$account[] = (int)$row['product_id'];
			}

			$list = \Ocs\Viewed::merge($account, $list);
		}

		return \Ocs\Viewed::visible($list, $exclude, $limit);
	}

	/**
	 * Browser list -> account rows, once per session and customer; browser-only ids get timestamps just below the newest row so
	 * that their order is kept and they never outrank something viewed after logging in.
	 */
	private function mergeCookie($customer_id) {
		if (!empty($this->session->data['ocs_viewed_merged']) && $this->session->data['ocs_viewed_merged'] === $customer_id) {
			return;
		}

		$this->session->data['ocs_viewed_merged'] = $customer_id;

		foreach ($this->cookieIds() as $position => $id) {
			$this->db->query("INSERT IGNORE INTO " . DB_PREFIX . "ocs_viewed SET customer_id = '" . $customer_id . "', product_id = '" . (int)$id . "', viewed_at = DATE_SUB(NOW(), INTERVAL " . (int)($position + 1) . " MINUTE)");
		}

		$this->trim($customer_id);
	}

	private function trim($customer_id) {
		$keep = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "ocs_viewed WHERE customer_id = '" . (int)$customer_id . "' ORDER BY viewed_at DESC, product_id DESC LIMIT " . \Ocs\Viewed::MAX);

		$ids = array();

		foreach ($keep->rows as $row) {
			$ids[] = (int)$row['product_id'];
		}

		if (count($ids) >= \Ocs\Viewed::MAX) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "ocs_viewed WHERE customer_id = '" . (int)$customer_id . "' AND product_id NOT IN (" . implode(',', $ids) . ")");
		}
	}

	private function writeCookie($list) {
		if (headers_sent()) {
			return;
		}

		setcookie(self::COOKIE, implode(',', $list), array(
			'expires'  => time() + 60 * 60 * 24 * 30,
			'path'     => '/',
			'secure'   => !empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off',
			'httponly' => true,
			'samesite' => 'Lax'
		));

		// the block of this very request reads the updated list
		$this->request->cookie[self::COOKIE] = implode(',', $list);
	}
}
