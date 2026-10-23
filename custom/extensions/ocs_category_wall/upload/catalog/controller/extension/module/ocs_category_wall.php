<?php
// Extension: ocs_category_wall. Category tile grid as a module and as the "All categories" page. Owner: SPEC-027 (REQ-MNU-010, 011).

class ControllerExtensionModuleOcsCategoryWall extends Controller {
	/**
	 * Module output (home page block or any layout position).
	 */
	public function index() {
		if (!$this->config->get('module_ocs_category_wall_status')) {
			return '';
		}

		$data = $this->wall();

		return $data['categories'] ? $this->load->view('extension/module/ocs_category_wall', $data) : '';
	}

	/**
	 * Standalone page: index.php?route=extension/module/ocs_category_wall/page
	 */
	public function page() {
		if (!$this->config->get('module_ocs_category_wall_status')) {
			$this->response->redirect($this->url->link('common/home'));
		}

		$data = $this->wall();

		$this->document->setTitle($data['heading_title']);

		$data['grid'] = $data['categories'] ? $this->load->view('extension/module/ocs_category_wall', $data) : '';

		$data['breadcrumbs'] = array(
			array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/home')),
			array('text' => $data['heading_title'], 'href' => $this->url->link('extension/module/ocs_category_wall/page'))
		);

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/module/ocs_category_wall_page', $data));
	}

	private function wall() {
		$this->load->language('extension/module/ocs_category_wall');
		$this->load->model('extension/ocs_menu/tree');
		$this->load->model('tool/image');

		$show_children = (bool)$this->config->get('module_ocs_category_wall_show_children');
		$limit = max(1, (int)$this->config->get('module_ocs_category_wall_children_limit') ?: 5);
		$sort = $this->config->get('module_ocs_category_wall_sort') == 'name' ? 'name' : 'sort';
		$width = max(1, (int)$this->config->get('module_ocs_category_wall_image_width') ?: 200);
		$height = max(1, (int)$this->config->get('module_ocs_category_wall_image_height') ?: 200);

		$tree = $this->model_extension_ocs_menu_tree->getTree($show_children ? 2 : 1);

		if ($this->config->get('module_ocs_category_wall_source') == 'selected') {
			$ids = array_map('intval', array_filter(array_map('trim', explode(',', (string)$this->config->get('module_ocs_category_wall_selected'))), 'strlen'));
			$nodes = \Ocs\CategoryTree::pick($tree, $ids);
		} else {
			$nodes = \Ocs\CategoryTree::sort($tree, $sort);
		}

		$data['categories'] = array();

		foreach ($nodes as $node) {
			$children = array();

			if ($show_children) {
				foreach (array_slice(\Ocs\CategoryTree::sort($node['children'], $sort), 0, $limit) as $child) {
					$children[] = array('name' => $child['name'], 'href' => $child['href']);
				}
			}

			$data['categories'][] = array(
				'name'     => $node['name'],
				'href'     => $node['href'],
				'popular'  => (bool)$node['popular'],
				'thumb'    => $this->model_tool_image->resize($node['image'] ? $node['image'] : 'placeholder.png', $width, $height),
				'children' => $children
			);
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['cols_desktop'] = max(1, min(6, (int)$this->config->get('module_ocs_category_wall_cols_desktop') ?: 4));
		$data['cols_tablet'] = max(1, min(4, (int)$this->config->get('module_ocs_category_wall_cols_tablet') ?: 3));
		$data['cols_mobile'] = max(1, min(3, (int)$this->config->get('module_ocs_category_wall_cols_mobile') ?: 2));
		$data['width'] = $width;
		$data['height'] = $height;

		return $data;
	}
}
