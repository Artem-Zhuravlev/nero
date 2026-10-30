<?php
// Extension: ocs_home_slider. Hero slider block (layout position). Owner: SPEC-033 (REQ-HOM-001).
// Slides come from one cached query per day and language; the first slide image is the LCP image (explicit size, no lazy loading).

class ControllerExtensionModuleOcsHomeSlider extends Controller {
	public function index() {
		if (!$this->config->get('module_ocs_home_slider_status')) {
			return '';
		}

		$language_id = (int)$this->config->get('config_language_id');
		$today = date('Y-m-d');

		$key = 'ocs_slider.' . $language_id . '.' . $today;

		$rows = $this->cache->get($key);

		if (!is_array($rows)) {
			$rows = $this->db->query("SELECT s.*, sd.title, sd.text FROM " . DB_PREFIX . "ocs_slide s LEFT JOIN " . DB_PREFIX . "ocs_slide_description sd ON (sd.slide_id = s.slide_id AND sd.language_id = '" . $language_id . "') WHERE s.status = '1' AND (s.date_from = '' OR s.date_from <= '" . $this->db->escape($today) . "') AND (s.date_to = '' OR s.date_to >= '" . $this->db->escape($today) . "') ORDER BY s.sort_order, s.slide_id")->rows;

			$this->cache->set($key, $rows);
		}

		$this->load->model('tool/image');
		$this->load->language('extension/module/ocs_home_slider');

		$w = max(100, (int)$this->config->get('module_ocs_home_slider_width') ?: 1280);
		$h = max(50, (int)$this->config->get('module_ocs_home_slider_height') ?: 480);
		$mw = max(100, (int)$this->config->get('module_ocs_home_slider_mobile_width') ?: 640);
		$mh = max(50, (int)$this->config->get('module_ocs_home_slider_mobile_height') ?: 480);

		// REQ-LAY-006: with the "side tiles" switch the last two slides become two promo tiles beside the slider
		$tiles = (bool)$this->config->get('module_ocs_home_slider_tiles');

		$data['slides'] = array();

		foreach ($rows as $row) {
			if (!$row['image'] || !is_file(DIR_IMAGE . $row['image'])) {
				continue;
			}

			$link = htmlspecialchars_decode((string)$row['link'], ENT_QUOTES);

			$data['slides'][] = array(
				'desktop' => $this->model_tool_image->resize($row['image'], $w, $h),
				'tile'    => $tiles ? $this->model_tool_image->resize($row['image'], 640, 320) : '',
				'mobile'  => $row['image_mobile'] && is_file(DIR_IMAGE . $row['image_mobile']) ? $this->model_tool_image->resize($row['image_mobile'], $mw, $mh) : '',
				'link'    => preg_match('#^(https?://|/(?!/))#i', $link) ? \Ocs\OrderRequest::e($link) : '',
				'title'   => \Ocs\OrderRequest::e($row['title']),
				'text'    => \Ocs\OrderRequest::e($row['text'])
			);
		}

		if (!$data['slides']) {
			return '';
		}

		// REQ-VIS-003: the category menu stays open next to the banners (same tree as the mega menu, flyouts are pure CSS)
		$data['menu'] = array();

		if ($this->config->get('module_ocs_home_slider_menu') && $this->config->get('module_ocs_mega_menu_status')) {
			$this->load->model('extension/ocs_menu/tree');

			foreach ($this->model_extension_ocs_menu_tree->getTree(2) as $node) {
				if (!$node['top']) {
					continue;
				}

				$icon = \Ocs\CategoryTree::icon($node['icon']);

				if ($icon['type'] == 'image') {
					$icon['src'] = $this->model_tool_image->resize($icon['value'], 24, 24);
				}

				$banner = false;

				if ($node['banner_image']) {
					$url = OcsShell::safeUrl($node['banner_url']);

					$banner = array(
						'src' => $this->model_tool_image->resize($node['banner_image'], 480, 720),
						'url' => $url !== '' ? $url : $node['href']
					);
				}

				// same node data as the header mega menu: popular dot, banner, every group and link
				$data['menu'][] = array('name' => $node['name'], 'href' => $node['href'], 'icon' => $icon, 'popular' => (bool)$node['popular'], 'banner' => $banner, 'children' => $node['children']);
			}
		}

		$data['multi'] = (bool)$this->config->get('module_ocs_home_slider_multi');
		$data['tiles'] = array();

		if ($tiles && count($data['slides']) >= 3) {
			$data['tiles'] = array_splice($data['slides'], -2);
		}

		$data['width'] = $w;
		$data['height'] = $h;
		$data['mobile_width'] = $mw;
		$data['mobile_height'] = $mh;
		$data['autoplay'] = max(0, min(30000, (int)$this->config->get('module_ocs_home_slider_autoplay')));

		foreach (array('text_label', 'text_prev', 'text_next', 'text_slide', 'text_catalog') as $k) {
			$data[$k] = $this->language->get($k);
		}

		return $this->load->view('extension/module/ocs_home_slider', $data);
	}
}
