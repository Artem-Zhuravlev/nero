<?php
// Extension: ocs_mega_menu. Renders the mega menu (header via event, or any layout position). Owner: SPEC-027 (REQ-MNU-001..004).

class ControllerExtensionModuleOcsMegaMenu extends Controller {
	public function index() {
		if (!$this->config->get('module_ocs_mega_menu_status')) {
			return '';
		}

		$strings = $this->load->language('extension/module/ocs_mega_menu');

		$this->load->model('extension/ocs_menu/tree');
		$this->load->model('tool/image');

		$levels = max(1, min(3, (int)$this->config->get('module_ocs_mega_menu_levels') ?: 3));
		$columns = max(1, min(5, (int)$this->config->get('module_ocs_mega_menu_columns') ?: 3));
		$behaviour = $this->config->get('module_ocs_mega_menu_behaviour') == 'click' ? 'click' : 'hover';

		$tree = $this->model_extension_ocs_menu_tree->getTree($levels);

		$active = isset($this->request->get['path']) ? \Ocs\CategoryTree::pathIds($this->request->get['path']) : array();
		$tree = \Ocs\CategoryTree::markActive($tree, $active);

		$brands = array();

		if ($this->config->get('module_ocs_mega_menu_brands')) {
			$top_ids = array();

			foreach ($tree as $node) {
				if ($node['top'] && $node['children']) {
					$top_ids[] = $node['category_id'];
				}
			}

			$brands = $this->model_extension_ocs_menu_tree->getBrands($top_ids, max(1, (int)$this->config->get('module_ocs_mega_menu_brands_limit') ?: 8));
		}

		$data['categories'] = array();

		foreach ($tree as $node) {
			if (!$node['top']) {
				continue;
			}

			$node_brands = array();

			if (isset($brands[(int)$node['category_id']])) {
				foreach ($brands[(int)$node['category_id']] as $brand) {
					$node_brands[] = array(
						'name' => $brand['name'],
						'href' => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $brand['manufacturer_id'])
					);
				}
			}

			$icon = \Ocs\CategoryTree::icon($node['icon']);

			if ($icon['type'] == 'image') {
				$icon['src'] = $this->model_tool_image->resize($icon['value'], 24, 24);
			}

			$banner = false;

			if ($node['banner_image']) {
				$url = \Ocs\Shell::safeUrl($node['banner_url']);

				$banner = array(
					'src' => $this->model_tool_image->resize($node['banner_image'], 480, 720),
					'url' => $url !== '' ? $url : $node['href']
				);
			}

			$data['categories'][] = array(
				'name'     => $node['name'],
				'href'     => $node['href'],
				'active'   => $node['active'],
				'popular'  => (bool)$node['popular'],
				'icon'     => $icon,
				'banner'   => $banner,
				'brands'   => $node_brands,
				'children' => $node['children']
			);
		}

		$data['columns'] = $columns;
		$data['behaviour'] = $behaviour;
		$data['text_catalog'] = $strings['text_catalog'];
		$data['text_all'] = $strings['text_all'];
		$data['text_brands'] = $strings['text_brands'];

		return $data['categories'] ? $this->load->view('extension/module/ocs_mega_menu', $data) : '';
	}
}
