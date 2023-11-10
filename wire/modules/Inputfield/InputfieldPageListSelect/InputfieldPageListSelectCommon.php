<?php namespace ProcessWire;

/**
 * Common methods for InputfieldPageListSelect and InputfieldPageListSelectMultiple
 * 
 * @since 3.0.231
 * 
 */
trait InputfieldPageListSelectCommon {

	/**
	 * @var ProcessPageList|null 
	 * 
	 */
	protected $pageList = null;

	/**
	 * Render ready
	 * 
	 * @param string $name
	 * @param string $labelFieldName
	 *
	 */
	public function pageListReady($name, $labelFieldName) {
		if($this->pageList) return;
		$this->pageList = $this->wire()->modules->get('ProcessPageList'); // prerequisite module
		$this->pageList->setPageLabelField($name, $labelFieldName);
		$this->pageList->renderReady();
	}

	/**
	 * Render markup value for PageListSelect/PageListSelectMultiple
	 * 
	 * @param int|int[] $value
	 * @return string
	 * 
	 */
	public function renderMarkupValue($value) {
		if(empty($value)) return '';
		$pages = $this->wire()->pages;
		if(is_array($value)) {
			$labels = array();
			foreach($value as $id) {
				$page = $pages->get((int) "$id");
				$labels[] = $this->getPageLabel($page);
			}
			return '<ul><li>' . implode('</li><li>', $labels) . '</li></ul>';
		} else {
			$page = $pages->get((int) "$value");
			$label = $this->getPageLabel($page);
			return "<p>$label</p>";
		}
	}

	/**
	 * Get label to represent given $page
	 * 
	 * @param Page $page
	 * @return string
	 * 
	 */
	public function getPageLabel(Page $page) {
		if(!$page->id) return '';
		if(!$page->viewable(false)) {
			$label = sprintf($this->_('Page %d not viewable'), $page->id);
		} else if($this->hasInputfield instanceof InputfieldPage) {
			$label = $this->hasInputfield->getPageLabel($page);
		} else {
			$label = $page->getUnformatted('title|name');
		}
		return $this->wire()->sanitizer->entities($label);
	}

	/**
	 * @return string
	 * 
	 */
	public function renderParentError() {
		return
			"<p class='error'>" .
			$this->_('Unable to render this field due to missing parent page in field settings.') .
			"</p>";
	}
}
