<?php
namespace App\Inc;

class Pagination{
	
	public $controller;
	
	public function __construct($controller, $parameters = null){
		$this->controller = $controller;
	}
	
	/**
	 * Generates pagination for the given pages.
	 * @param int $pages The total number of pages.
	 * @return string The HTML of the pagination.
	 **/
	public function pager(int $pages): string
	{
		$parameters = '';
		
		if (!empty($_GET)) {
			$parameters .= '&amp;';
			$queryParts = [];

			foreach ($_GET as $key => $value) {
				if ($key !== 'page') {
					$queryParts[] = $key . '=' . urlencode($value);
				}
			}

			$parameters .= implode('&amp;', $queryParts);
		}

		$pagination = '';
		if ($pages > 1) {
			$pagination .= '<div class="pagination pager">';
			$pagination .= '<div class="label">Pages</div>';
			$pagination .= '<ul>';

			for ($i = 1; $i <= $pages; $i++) {
				$attr1 = $i > 1 ? 'clickable' : '';
				$attr2 = $i == $this->controller->request->page ? ' active' : '';

				$pagination .= '<a href="?page=' . $i . $parameters . '">';
				$pagination .= '<li class="' . $attr1 . $attr2 . '">' . $i . '</li>';
				$pagination .= '</a>';
			}

			$pagination .= '</ul>';
			$pagination .= '</div>';
		}

		return $pagination;
	}

	/**
	 * Generates pagination based on the given years.
	 * @param array $years Table of years for pagination.
	 * @return string The HTML of the pagination of the years.
	 **/
	public function years(array $years): string
	{
		$parameters = '';

		if (!empty($_GET)) {
			$parameters .= '&amp;';
			$queryParts = [];

			foreach ($_GET as $key => $value) {
				if ($key !== 'year') {
					$queryParts[] = $key . '=' . urlencode($value);
				}
			}

			$parameters .= implode('-----', $queryParts);
		}

		$pagination = '';
		if (count($years) > 1) {
			$pagination .= '<div class="pagination years">';
			$pagination .= '<div class="label">Years</div>';
			$pagination .= '<ul>';

			for ($i = $years[0]; $i <= end($years); $i++) {
				$attr1 = $i > 1 ? 'clickable' : '';
				$attr2 = (isset($this->controller->request->parameters->year) && $i == $this->controller->request->parameters->year) ? ' active' : '';

				$pagination .= '<a href="?year=' . $i . $parameters . '">';
				$pagination .= '<li class="' . $attr1 . $attr2 . '">' . $i . '</li>';
				$pagination .= '</a>';
			}

			$pagination .= '</ul>';
			$pagination .= '</div>';
		}

		return $pagination;
	}

}
?>
