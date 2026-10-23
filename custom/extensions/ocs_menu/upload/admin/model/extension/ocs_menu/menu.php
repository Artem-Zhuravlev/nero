<?php
// Extension: ocs_menu. Admin access to per-category menu extras (icon, popular flag, banner). Owner: SPEC-027.

class ModelExtensionOcsMenuMenu extends Model {
	/**
	 * @return array category_id => row
	 */
	public function getExtras() {
		$out = array();

		foreach ($this->db->query("SELECT * FROM " . DB_PREFIX . "ocs_menu_category")->rows as $row) {
			$out[(int)$row['category_id']] = $row;
		}

		return $out;
	}

	/**
	 * Replaces the extras of the given categories; rows with no values are removed.
	 *
	 * @param array $rows category_id => array(icon, popular, banner_image, banner_url)
	 */
	public function saveExtras($rows) {
		foreach ((array)$rows as $category_id => $row) {
			$category_id = (int)$category_id;

			if ($category_id < 1 || !is_array($row)) {
				continue;
			}

			$icon = isset($row['icon']) ? trim((string)$row['icon']) : '';
			$popular = !empty($row['popular']) ? 1 : 0;
			$banner_image = isset($row['banner_image']) ? trim((string)$row['banner_image']) : '';
			$banner_url = isset($row['banner_url']) ? trim((string)$row['banner_url']) : '';

			$this->db->query("DELETE FROM " . DB_PREFIX . "ocs_menu_category WHERE category_id = '" . $category_id . "'");

			if ($icon !== '' || $popular || $banner_image !== '' || $banner_url !== '') {
				$this->db->query("INSERT INTO " . DB_PREFIX . "ocs_menu_category SET category_id = '" . $category_id . "', icon = '" . $this->db->escape(utf8_substr($icon, 0, 128)) . "', popular = '" . $popular . "', banner_image = '" . $this->db->escape(utf8_substr($banner_image, 0, 255)) . "', banner_url = '" . $this->db->escape(utf8_substr($banner_url, 0, 255)) . "'");
			}
		}

		$this->cache->delete('ocs_menu');
	}
}
