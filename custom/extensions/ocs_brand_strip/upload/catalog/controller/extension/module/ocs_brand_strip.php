<?php
// Extension: ocs_brand_strip. Manufacturer logos with links, horizontal scroll. Owner: SPEC-033 (REQ-HOM-010).

class ControllerExtensionModuleOcsBrandStrip extends Controller {
	public function index() {
		if (!$this->config->get('module_ocs_brand_strip_status')) {
			return '';
		}

		$width = max(40, (int)$this->config->get('module_ocs_brand_strip_width') ?: 140);
		$height = max(30, (int)$this->config->get('module_ocs_brand_strip_height') ?: 80);

		$key = 'ocs_brands.' . (int)$this->config->get('config_store_id') . '.' . $width . 'x' . $height;

		$brands = $this->cache->get($key);

		if (!is_array($brands)) {
			$this->load->model('catalog/manufacturer');
			$this->load->model('tool/image');

			$limit = max(1, min(60, (int)$this->config->get('module_ocs_brand_strip_limit') ?: 20));
			$rows = array();

			if ($this->config->get('module_ocs_brand_strip_source') == 'selected') {
				foreach (array_filter(array_map('intval', explode(',', (string)$this->config->get('module_ocs_brand_strip_selected')))) as $id) {
					$row = $this->model_catalog_manufacturer->getManufacturer($id);

					if ($row) {
						$rows[] = $row;
					}
				}
			} else {
				$rows = $this->model_catalog_manufacturer->getManufacturers(array(
					'sort'  => $this->config->get('module_ocs_brand_strip_sort') == 'sort' ? 'sort_order' : 'name',
					'order' => 'ASC',
					'start' => 0,
					'limit' => $limit
				));
			}

			$brands = array();

			foreach ($rows as $row) {
				if (empty($row['image']) || !is_file(DIR_IMAGE . $row['image'])) {
					continue;
				}

				$brands[] = array(
					'name'  => html_entity_decode($row['name'], ENT_QUOTES, 'UTF-8'),
					'thumb' => $this->model_tool_image->resize($row['image'], $width, $height),
					'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . (int)$row['manufacturer_id'])
				);
			}

			$this->cache->set($key, array_slice($brands, 0, $limit));

			$brands = array_slice($brands, 0, $limit);
		}

		if (!$brands) {
			return '';
		}

		$this->load->language('extension/module/ocs_brand_strip');

		$data['brands'] = $brands;
		$data['width'] = $width;
		$data['height'] = $height;
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_prev'] = $this->language->get('text_prev');
		$data['text_next'] = $this->language->get('text_next');

		return $this->load->view('extension/module/ocs_brand_strip', $data);
	}
}
