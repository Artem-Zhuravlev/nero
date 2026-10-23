<?php
// Extension: ocs_stickers. Active sticker definitions (cached) and the facts of a page of products (one batched query). Owner: SPEC-029 (REQ-STK-010).

class ModelExtensionOcsStickersStickers extends Model {
	/**
	 * Active stickers with the name in the current language; cached, the admin clears the cache on every change.
	 */
	public function getDefinitions() {
		$key = 'ocs_stickers.defs.' . (int)$this->config->get('config_language_id');

		$defs = $this->cache->get($key);

		if (!is_array($defs)) {
			$query = $this->db->query("SELECT s.*, sd.name AS text FROM " . DB_PREFIX . "ocs_sticker s LEFT JOIN " . DB_PREFIX . "ocs_sticker_description sd ON (sd.sticker_id = s.sticker_id AND sd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE s.status = '1' ORDER BY s.priority DESC, s.sticker_id");

			$defs = array();

			foreach ($query->rows as $row) {
				$row['text'] = html_entity_decode((string)$row['text'], ENT_QUOTES, 'UTF-8');
				$defs[] = $row;
			}

			$this->cache->set($key, $defs);
		}

		return $defs;
	}

	/**
	 * One query for all the data the rules need.
	 *
	 * @param int[] $ids
	 * @return array product_id => fact (see \Ocs\Stickers::matches)
	 */
	public function getFacts($ids) {
		$clean = array();

		foreach ((array)$ids as $id) {
			$clean[] = (int)$id;
		}

		if (!$clean) {
			return array();
		}

		$group = (int)$this->config->get('config_customer_group_id');

		$query = $this->db->query("SELECT p.product_id, p.date_added, p.quantity, p.price, p.manufacturer_id, (SELECT ps.price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . $group . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special, (SELECT COALESCE(SUM(op.quantity), 0) FROM " . DB_PREFIX . "order_product op INNER JOIN `" . DB_PREFIX . "order` o ON (o.order_id = op.order_id) WHERE op.product_id = p.product_id AND o.order_status_id > '0') AS sold, (SELECT GROUP_CONCAT(p2c.category_id) FROM " . DB_PREFIX . "product_to_category p2c WHERE p2c.product_id = p.product_id) AS cats, (SELECT GROUP_CONCAT(sp.sticker_id) FROM " . DB_PREFIX . "ocs_sticker_product sp WHERE sp.product_id = p.product_id) AS manual FROM " . DB_PREFIX . "product p WHERE p.product_id IN (" . implode(',', $clean) . ")");

		$out = array();

		foreach ($query->rows as $row) {
			$out[(int)$row['product_id']] = array(
				'product_id'      => (int)$row['product_id'],
				'date_added'      => $row['date_added'],
				'quantity'        => (int)$row['quantity'],
				'price'           => (float)$row['price'],
				'special'         => $row['special'] !== null ? (float)$row['special'] : null,
				'manufacturer_id' => (int)$row['manufacturer_id'],
				'sold'            => (int)$row['sold'],
				'cats'            => $row['cats'] !== null && $row['cats'] !== '' ? array_map('intval', explode(',', $row['cats'])) : array(),
				'manual'          => $row['manual'] !== null && $row['manual'] !== '' ? array_map('intval', explode(',', $row['manual'])) : array()
			);
		}

		return $out;
	}

	/**
	 * Stickers of the given products by position, text already escaped (templates print it unescaped). Empty when the module is off.
	 * Used by the listing events, quick view and the module blocks (SPEC-029 REQ-STK-006, SPEC-037 REQ-STK-010).
	 *
	 * @param int[] $ids
	 * @return array product_id => array(position => list of array(text, bg, fg))
	 */
	public function forProducts($ids) {
		$ids = array_values(array_unique(array_filter(array_map('intval', (array)$ids))));

		if (!$ids || !$this->config->get('module_ocs_stickers_status') || $this->config->get('config_theme') != 'ocs') {
			return array();
		}

		$defs = $this->getDefinitions();

		if (!$defs) {
			return array();
		}

		$map = \Ocs\Stickers::resolve($defs, $this->getFacts($ids), time(), (int)$this->config->get('module_ocs_stickers_limit') ?: 2);

		foreach ($map as $product_id => $positions) {
			foreach ($positions as $position => $list) {
				foreach ($list as $i => $sticker) {
					$map[$product_id][$position][$i]['text'] = htmlspecialchars($sticker['text'], ENT_QUOTES, 'UTF-8');
				}
			}
		}

		return $map;
	}
}
