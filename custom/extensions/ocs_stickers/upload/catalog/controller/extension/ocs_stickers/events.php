<?php
// Extension: ocs_stickers. Adds the stickers of every card / the product page to the view data. Owner: SPEC-029 (REQ-STK-006).
// Handlers never throw to the customer (REQ-EXT-062).

class ControllerExtensionOcsStickersEvents extends Controller {
	public function beforeCategory(&$route, &$data) {
		$this->listing($data);
	}

	public function beforeSearch(&$route, &$data) {
		$this->listing($data);
	}

	public function beforeManufacturer(&$route, &$data) {
		$this->listing($data);
	}

	public function beforeSpecial(&$route, &$data) {
		$this->listing($data);
	}

	/**
	 * catalog/view/product/product/before: stickers of the product itself.
	 */
	public function beforeProduct(&$route, &$data) {
		if (!is_array($data) || !$this->enabled() || empty($data['product_id'])) {
			return;
		}

		$map = $this->resolve(array((int)$data['product_id']));

		if (isset($map[(int)$data['product_id']])) {
			$data['ocs_stickers'] = $map[(int)$data['product_id']];
		}
	}

	private function listing(&$data) {
		if (!is_array($data) || !$this->enabled() || empty($data['products']) || !is_array($data['products'])) {
			return;
		}

		$ids = array();

		foreach ($data['products'] as $product) {
			if (isset($product['product_id'])) {
				$ids[] = (int)$product['product_id'];
			}
		}

		$map = $this->resolve($ids);

		foreach ($data['products'] as $i => $product) {
			if (isset($product['product_id'], $map[(int)$product['product_id']])) {
				$data['products'][$i]['stickers'] = $map[(int)$product['product_id']];
			}
		}
	}

	private function resolve($ids) {
		$this->load->model('extension/ocs_stickers/stickers');

		$defs = $this->model_extension_ocs_stickers_stickers->getDefinitions();

		if (!$defs) {
			return array();
		}

		$map = \Ocs\Stickers::resolve($defs, $this->model_extension_ocs_stickers_stickers->getFacts($ids), time(), (int)$this->config->get('module_ocs_stickers_limit') ?: 2);

		// templates print the text unescaped (autoescape is off): escape here, once
		foreach ($map as $product_id => $positions) {
			foreach ($positions as $position => $list) {
				foreach ($list as $i => $sticker) {
					$map[$product_id][$position][$i]['text'] = htmlspecialchars($sticker['text'], ENT_QUOTES, 'UTF-8');
				}
			}
		}

		return $map;
	}

	private function enabled() {
		return (bool)$this->config->get('module_ocs_stickers_status') && $this->config->get('config_theme') == 'ocs';
	}
}
