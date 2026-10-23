<?php
// Extension: ocs_request. Option data of a product in the shape of the product page, shared by the quick view and the option-level
// pre-order rule. Owner: SPEC-037 (REQ-QVW-002, REQ-OPT-001).

class ModelExtensionOcsRequestOptions extends Model {
	/**
	 * Same structure as core's product/product page ($data['options']); values that are out of stock are kept and flagged 'out'
	 * only when $keepOut is true (otherwise they are dropped like core does).
	 *
	 * @param array $product row of catalog/product getProduct()
	 */
	public function build($product, $keepOut = false) {
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$theme = $this->config->get('config_theme');
		$w = (int)$this->config->get('theme_' . $theme . '_image_additional_width') ?: 74;
		$h = (int)$this->config->get('theme_' . $theme . '_image_additional_height') ?: 74;
		$show_price = (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price'));
		$options = array();

		foreach ($this->model_catalog_product->getProductOptions($product['product_id']) as $option) {
			$values = array();

			foreach ($option['product_option_value'] as $value) {
				$out = \Ocs\Stock::optionOut($value['subtract'], $value['quantity']);

				if ($out && !$keepOut) {
					continue;
				}

				$price = false;

				if ($show_price && (float)$value['price']) {
					$price = $this->currency->format($this->tax->calculate($value['price'], $product['tax_class_id'], $this->config->get('config_tax') ? 'P' : false), $this->session->data['currency']);
				}

				$values[] = array(
					'product_option_value_id' => $value['product_option_value_id'],
					'option_value_id'         => $value['option_value_id'],
					'name'                    => $value['name'],
					'image'                   => $value['image'] ? $this->model_tool_image->resize($value['image'], $w, $h) : '',
					'price'                   => $price,
					'price_prefix'            => $value['price_prefix'],
					'out'                     => $out
				);
			}

			$options[] = array(
				'product_option_id'    => $option['product_option_id'],
				'product_option_value' => $values,
				'option_id'            => $option['option_id'],
				'name'                 => $option['name'],
				'type'                 => $option['type'],
				'value'                => $option['value'],
				'required'             => $option['required']
			);
		}

		return $options;
	}

	/**
	 * product_option_value_id => true for every out-of-stock value of the product.
	 */
	public function outValues($product_id) {
		$this->load->model('catalog/product');

		$out = array();

		foreach ($this->model_catalog_product->getProductOptions($product_id) as $option) {
			foreach ($option['product_option_value'] as $value) {
				if (\Ocs\Stock::optionOut($value['subtract'], $value['quantity'])) {
					$out[(int)$value['product_option_value_id']] = true;
				}
			}
		}

		return $out;
	}
}
