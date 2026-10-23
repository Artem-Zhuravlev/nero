<?php
// Extension: ocs_listing. One batched query for the extra card data of a listing page. Owner: SPEC-034 (REQ-LST-010, REQ-LST-021).

class ModelExtensionOcsListingListing extends Model {
	/**
	 * @param int[] $product_ids
	 * @return array product_id => array(model, reviews, discount_pct)
	 */
	public function getExtras($product_ids) {
		$ids = array();

		foreach ((array)$product_ids as $id) {
			$ids[] = (int)$id;
		}

		if (!$ids) {
			return array();
		}

		$group = (int)$this->config->get('config_customer_group_id');

		$query = $this->db->query("SELECT p.product_id, p.model, p.price, (SELECT ps.price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . $group . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special, (SELECT COUNT(*) FROM " . DB_PREFIX . "review r WHERE r.product_id = p.product_id AND r.status = '1') AS reviews FROM " . DB_PREFIX . "product p WHERE p.product_id IN (" . implode(',', $ids) . ")");

		$out = array();

		foreach ($query->rows as $row) {
			$pct = 0;

			if ($row['special'] !== null && (float)$row['price'] > 0 && (float)$row['special'] < (float)$row['price']) {
				$pct = (int)round((1 - (float)$row['special'] / (float)$row['price']) * 100);
			}

			$out[(int)$row['product_id']] = array(
				'model'        => $row['model'],
				'reviews'      => (int)$row['reviews'],
				'discount_pct' => $pct
			);
		}

		return $out;
	}
}
