<?php
// Extension: ocs_home_slider. Admin data access for slides. Owner: SPEC-033 (REQ-HOM-001).

class ModelExtensionOcsHomeSliderSlide extends Model {
	public function getSlides() {
		return $this->db->query("SELECT s.*, (SELECT sd.title FROM " . DB_PREFIX . "ocs_slide_description sd WHERE sd.slide_id = s.slide_id AND sd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS title FROM " . DB_PREFIX . "ocs_slide s ORDER BY s.sort_order, s.slide_id")->rows;
	}

	public function getSlide($slide_id) {
		$row = $this->db->query("SELECT * FROM " . DB_PREFIX . "ocs_slide WHERE slide_id = '" . (int)$slide_id . "'")->row;

		if ($row) {
			$row['descriptions'] = array();

			foreach ($this->db->query("SELECT * FROM " . DB_PREFIX . "ocs_slide_description WHERE slide_id = '" . (int)$slide_id . "'")->rows as $d) {
				$row['descriptions'][(int)$d['language_id']] = array('title' => $d['title'], 'text' => $d['text']);
			}
		}

		return $row;
	}

	/**
	 * @return int slide_id
	 */
	public function save($slide_id, $data) {
		$set = "image = '" . $this->db->escape($data['image']) . "', image_mobile = '" . $this->db->escape($data['image_mobile']) . "', link = '" . $this->db->escape($data['link']) . "', sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)!empty($data['status']) . "', date_from = '" . $this->db->escape($data['date_from']) . "', date_to = '" . $this->db->escape($data['date_to']) . "'";

		if ($slide_id) {
			$this->db->query("UPDATE " . DB_PREFIX . "ocs_slide SET " . $set . " WHERE slide_id = '" . (int)$slide_id . "'");
		} else {
			$this->db->query("INSERT INTO " . DB_PREFIX . "ocs_slide SET " . $set);

			$slide_id = $this->db->getLastId();
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "ocs_slide_description WHERE slide_id = '" . (int)$slide_id . "'");

		foreach ($data['descriptions'] as $language_id => $d) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "ocs_slide_description SET slide_id = '" . (int)$slide_id . "', language_id = '" . (int)$language_id . "', title = '" . $this->db->escape(utf8_substr($d['title'], 0, 128)) . "', text = '" . $this->db->escape(utf8_substr($d['text'], 0, 255)) . "'");
		}

		$this->cache->delete('ocs_slider');

		return (int)$slide_id;
	}

	public function delete($slide_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "ocs_slide WHERE slide_id = '" . (int)$slide_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "ocs_slide_description WHERE slide_id = '" . (int)$slide_id . "'");

		$this->cache->delete('ocs_slider');
	}
}
