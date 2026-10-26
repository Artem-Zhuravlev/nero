<?php
// Extension: ocs_bookmarks. POST type=wish|cmp, action=add|remove, product_id: keeps the account wishlist of a logged-in customer in step with the
// cookie that theme.js already wrote, and answers the counters. Compare and guests need nothing here: the cookie is the source (SPEC-044 REQ-BKM-001).

class ControllerExtensionOcsBookmarksSync extends Controller {
	public function index() {
		$json = array();

		$type = isset($this->request->post['type']) ? $this->request->post['type'] : '';
		$action = isset($this->request->post['action']) ? $this->request->post['action'] : '';
		$id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $id > 0 && in_array($type, array('wish', 'cmp'), true) && in_array($action, array('add', 'remove'), true)) {
			if ($type == 'wish' && $this->customer->isLogged()) {
				$this->load->model('account/wishlist');

				if ($action == 'add') {
					$this->load->model('catalog/product');

					// only an existing, enabled product enters the account list
					if ($this->model_catalog_product->getProduct($id)) {
						$this->model_account_wishlist->addWishlist($id);
					}
				} else {
					$this->model_account_wishlist->deleteWishlist($id);
				}
			}

			$json['success'] = true;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->addHeader('Cache-Control: no-store');
		$this->response->setOutput(json_encode($json));
	}
}
