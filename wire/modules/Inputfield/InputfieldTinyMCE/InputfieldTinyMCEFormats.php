<?php namespace ProcessWire;

/**
 * InputfieldTinyMCEFormats
 *
 * Helper for managing TinyMCE style_formats and related settings
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */
class InputfieldTinyMCEFormats extends InputfieldTinyMCEClass {
	
	/**
	 * HTML5 inline elements that should be "inline" designation in style_formats
	 *
	 * Text can be highlighted and then applied to any of these selements from the Styles dropdown.
	 * See also: notes in $inlineBlocks variable below.
	 *
	 * @var string
	 *
	 */
	static protected $inlines =
		'/abbr/acronym/b/bdi/bdo/big/br/button/cite/code/' .
		'/del/dfn/em/i/ins/kbd/label/mark/meter/' .
		'/q/s/samp/small/span/strong/' .
		'/sub/sup/time/u/tt/var/wbr/';

	/**
	 * HTML5 block elements that should use "block" designation in style_formats
	 *
	 * These elements can be inserted from Styles dropdown, if defined in style_formats.
	 * Block elements not defined here must exist before the style can be applied. 
	 *
	 * @var string
	 *
	 */
	static protected $blocks =
		'/h1/h2/h3/h4/h5/h6/p/hr/';
		// '/address/article/aside/blockquote/dd/details/div/dl/dt/' .
		// '/footer/h1/h2/h3/h4/h5/h6/header/hgroup/hr/li/main/nav/ol/p/pre/' .
		// '/section/table/'; 

	/**
	 * HTML5 block or inline elements that should use "selector" designation in style_formats
	 *
	 * These elements (and any others not defined above) cannot be inserted by selection but
	 * existing elements can be applied. For reference only, nothing uses this variable.
	 *
	 * @var string
	 *
	 */
	static protected $inlineBlocks =
		'/a/fieldset/figcaption/figure/form/dialog/form/' .
		'/audio/canvas/data/datalist/img/iframe/embed/input/map/noscript/object/output/' .
		'/picture/progress/ruby/select/slot/svg/template/textarea/video/';

	/**
	 * Get block_formats
	 *
	 * @return string
	 *
	 */
	public function getBlockFormats() {
		// 'block_formats' => 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5; Heading 6=h6;',
		$values = array('Paragraph=p;');
		$headlines = $this->inputfield->get('headlines');
		foreach($headlines as $h) {
			$n = ltrim($h, 'h');
			$values[$h] = "Heading $n=$h;";
		}
		return implode(' ', $values);
	}
	
	/**
	 * Get style_formats
	 *
	 * @param array $defaults
	 * @return array|mixed
	 *
	 */
	public function getStyleFormats(array $defaults) {

		/*
		'style_formats' => array(
			array(
				'title' => 'Headings',
				'items' => array(
					array('title' => 'Heading 1', 'format' => 'h1'),
					array('title' => 'Heading 2', 'format' => 'h2'),
					array('title' => 'Heading 3', 'format' => 'h3'),
					array('title' => 'Heading 4', 'format' => 'h4'),
					array('title' => 'Heading 5', 'format' => 'h5'),
					array('title' => 'Heading 6', 'format' => 'h6')
				)
			),
		*/

		$headlines = $this->inputfield->headlines;
		$headlines = array_flip($headlines);

		$formats = $defaults['style_formats'];

		foreach($formats as $key => $format) {
			if(!is_array($format)) continue;
			if($format['title'] === 'Headings') {
				foreach($format['items'] as $k => $item) {
					if(empty($item['format'])) continue;
					$tag = $item['format'];
					if(!isset($headlines[$tag])) unset($formats[$key]['items'][$k]);
				}
				$formats[$key]['items'] = array_values($formats[$key]['items']);
				break;
			}
		}

		return $formats;
	}


	/**
	 * Merge the given style formats
	 *
	 * @param array $styleFormats
	 * @param array $addFormats
	 * @return array
	 *
	 */
	public function mergeStyleFormats(array $styleFormats, array $addFormats) {
		$a = array();
		foreach($styleFormats as $value) {
			if(empty($value['title'])) continue;
			$title = $value['title'];
			$a[$title] = $value;
		}
		$styleFormats = $a;
		foreach($addFormats as $value) {
			if(empty($value['title'])) continue;
			$title = $value['title'];
			if(isset($styleFormats[$title])) {
				if(isset($styleFormats[$title]['items'])) {
					if(isset($value['items'])) {
						$styleFormats[$title]['items'] = array_merge($styleFormats[$title]['items'], $value['items']);
					}
				} else {
					$styleFormats[$title] = array(
						'title' => $title,
						'items' => $value,
					);
				}
			} else {
				$styleFormats[$title] = $value;
			}
		}
		return array_values($styleFormats);
	}


	/**
	 * Add CSS that converts to style_formats and content_style
	 *
	 * Easier-to-use alternative to the importcss plugin
	 *
	 * @param string $css From the styleFormatsCSS setting
	 * @param array $settings
	 * @param array $defaults
	 *
	 */
	public function applyStyleFormatsCSS($css, array &$settings, $defaults) {

		$contentStyle = ''; // output for content_style

		// ensures each CSS rule has its own line
		$css = trim(str_replace('}', "}\n", $css));

		// converts each CSS rule to be on single line with no newlines between "key:value;" rules
		//$css = preg_replace('!\s*([{;:]|/\*|\*/)\s*!s', '\1', $css);
		$css = preg_replace('!\s*([{;:]|/\*)\s*!s', '\1', $css);

		//$css = preg_replace('!\}\s+/\*!s', '}/*', $css);

		$lines = explode("\n", $css);
		$numRemove = 0; 
		$formats = array(
			// 'Headings' => array(), 
			// 'Blocks' => array(),
			// 'Inline' => array(),
			// 'Align' => array(),
			// 'Other' => array(), // converts to root level (no parent)
		);

		while(count($lines)) {

			$line = array_shift($lines);
			$line = trim($line);
			$title = '';

			if(empty($line)) continue;
			if(strpos($line, '{') && strpos($line, '}') === false) {
				// grab next line if a rule was started but not closed
				$line .= array_shift($lines);
			}

			if(strpos($line, '{') === false) continue; // line does not start a rule

			if(strpos($line, '/*') && preg_match('!/\*(.+)?\*/!', $line, $matches)) {
				// line has comment indicating text label
				$title = trim($matches[1]);
				$line = str_replace($matches[0], '', $line);
			}

			list($selector, $styles) = explode('{', $line, 2);
			list($styles,) = explode('}', $styles, 2);

			$selector = trim($selector);
			
			if(strpos($selector, '(') !== false) {
				// Alternate title assignment i.e. #Align(Center)
				list($selector, $title2) = explode('(', $selector, 2); 
				list($title2, $selector2) = explode(')', $title2, 2); 
				$selector = trim("$selector $selector2"); 
				if(!empty($title2)) $title = trim($title2);
			}

			if(strpos($selector, '#') === 0) {
				// indicates a submenu parent, i.e. #Blocks
				if(strpos($selector, ' ')) {
					list($parent, $selector) = explode(' ', $selector, 2);
				} else {
					list($parent, $selector) = array($selector, ''); 
				}
				$selector = trim($selector);
				$parent = ucfirst(strtolower(ltrim($parent, '#')));
			} else {
				$parent = 'Other';
			}

			if(strpos($selector, '.') !== false) {
				// element with class, i.e. span.red-text or just .red-text
				list($element, $class) = explode('.', $selector, 2);
				$class = str_replace('.', ' ', $class);
			} else {
				// element only (no class), i.e. ins or del
				$element = $selector;
				$class = '';
			}

			$stylesStr = ''; // minified styles string
			$inlineStyles = array(); // styles to also forced as inline styles on element

			foreach(explode(';', $styles) as $style) {
				// i.e. color: red
				if(strpos($style, ':') === false) continue;
				list($k, $v) = explode(':', $style);
				list($k, $v) = array(trim($k), trim($v));
				if(strtoupper($k) === $k) {
					// uppercase styles i.e. 'COLOR: red' become inline styles of element
					$k = strtolower($k);
					$inlineStyles[$k] = $v;
				}
				$stylesStr .= "$k:$v;";
			}
			
			$contentStyleSelector = ($class ? "$element." . str_replace(' ', '.', $class) : $element);
			
			if(stripos($stylesStr, 'display:none') !== false) {
				$numRemove++;
				if(empty($title)) $title = '*'; // indicates remove all in parent
				$remove = true;
			} else {
				$contentStyle .= "$contentStyleSelector { $stylesStr } ";
				$remove = false;
			}

			if(empty($element)) $element = '*';

			$format = array(
				'title' => ($title ? $title : $selector)
			);
			
			if($remove) {
				$format['remove'] = true;
			} else if(stripos(self::$inlines, "/$element/") !== false) {
				$format['inline'] = $element;
			} else if(strpos(self::$blocks, "/$element/") !== false) {
				$format['block'] = $element;
			} else {
				$format['selector'] = $element;
			}

			if($class) $format['classes'] = $class;
			if(count($inlineStyles)) $format['styles'] = $inlineStyles;
			if(!isset($formats[$parent])) $formats[$parent] = array();

			$formats[$parent][] = $format;
		}

		$styleFormats = array();

		foreach($formats as $parent => $format) {
			if($parent === 'Other') {
				$styleFormats[$parent] = $format;
			} else if(!isset($styleFormats[$parent])) {
				$styleFormats[$parent] = array(
					'title' => $parent,
					'items' => $format,
				);
			}
		}

		$other = isset($styleFormats['Other']) ? $styleFormats['Other'] : array();
		unset($styleFormats['Other']);

		$styleFormats = array_values($styleFormats);
		if(count($other)) $styleFormats = array_merge($styleFormats, $other);

		// add to settings
		if(isset($settings['style_formats'])) {
			$settings['style_formats'] = $this->mergeStyleFormats($settings['style_formats'], $styleFormats);
		} else if(isset($defaults['style_formats'])) {
			$settings['style_formats'] = $this->mergeStyleFormats($defaults['style_formats'], $styleFormats);
		} else {
			$settings['style_formats'] = $styleFormats;
		}

		if(isset($settings['content_style'])) {
			$settings['content_style'] .= $contentStyle;
		} else if(isset($defaults['content_style'])) {
			$settings['content_style'] = $defaults['content_style'] . $contentStyle;
		} else {
			$settings['content_style'] = $contentStyle;
		}
		
		if($numRemove) {
			$settings['style_formats'] = $this->applyRemoveStyleFormats($settings['style_formats']); 
		}
	
		// reindex to ensure keys remain numeric and in order so json_encode doesn’t use string keys
		$settings['style_formats'] = array_values($settings['style_formats']); 
	}

	/**
	 * Remove style formats that have a 'remove=true' property
	 * 
	 * @param array $styleFormats
	 * @return array
	 * 
	 */
	protected function applyRemoveStyleFormats(array $styleFormats) {
		
		foreach($styleFormats as $key => $styleFormat) {
			
			if(!empty($styleFormat['remove'])) {
				// remove all in format or remove root level format
				unset($styleFormats[$key]); 
				continue;
			} else if(empty($styleFormat['items'])) {
				// root level format with no items
				continue;
			}
			
			$removeTitles = array();
			
			foreach($styleFormat['items'] as $item) {
				if(empty($item['remove'])) continue;
				$title = strtolower($item['title']);
				if($title === '*') {
					unset($styleFormats[$key]); // remove all in parent
				} else {
					$removeTitles[$title] = $title; // remove by title
				}
			}
		
			if(empty($styleFormats[$key]) || empty($removeTitles)) continue;
			
			foreach($styleFormat['items'] as $itemKey => $item) {
				$title = strtolower($item['title']); 
				if(!isset($removeTitles[$title])) continue;
				unset($styleFormats[$key]['items'][$itemKey]); // remove item matching title
				if(empty($styleFormats[$key]['items'])) {
					unset($styleFormats[$key]); // remove parent when it has no items
					break;
				}
			}
		
			// reindex to prevent json_encode from converting keys to strings
			if(isset($styleFormats[$key]['items'])) {
				$styleFormats[$key]['items'] = array_values($styleFormats[$key]['items']);
			}
		}

		return $styleFormats;
	}

	/**
	 * Get TinyMCE "invalid_styles" setting and prepare as array value
	 * 
	 * Parses value in space-separated string format (commas optional):
	 * ~~~~~
	 * line-height, color, a=background|background-color, td=height
	 * ~~~~~
	 * In the above, line-height and color are disabled for all elements,
	 * background and background color are disabled for "a" elements, 
	 * and height is disabled for "td" elements. 
	 * 
	 * @param string|array $value
	 * @param array|string $defaultValue
	 * @param bool $merge Merge with given defaultValue?
	 * @return array|string
	 * 
	 */
	public function getInvalidStyles($value, $defaultValue, $merge = false) {
		
		if(!is_array($defaultValue)) $defaultValue = array('*' => $defaultValue);
		if($value === null) $value = $this->inputfield->invalid_styles;
		if($value === 'default') return $defaultValue;
		if(is_string($value) && strpos($value, ',') !== false) $value = str_replace(',', ' ', $value);
		
		if($merge) {
			if(is_string($defaultValue)) {
				$defaultValue = $this->invalidStylesStrToArray($defaultValue);
			}
			if(is_array($value)) {
				$invalidStyles = array_merge($defaultValue, $value);
			} else {
				$invalidStyles = $this->invalidStylesStrToArray("$value", $defaultValue);
			}
		} else if(is_array($value)) {
			$invalidStyles = $value;
		} else {
			$invalidStyles = $this->invalidStylesStrToArray("$value");
		}
		
		return $invalidStyles;
	}

	/**
	 * Convert invalid_styles string to array
	 * 
	 * @param string $value i.e. "line-height color a=background|background-color td=height"
	 * @param array $a Optionally merge with these styles
	 * @return array
	 * 
	 */
	public function invalidStylesStrToArray($value, array $a = array()) {
		// $value i.e. "line-height color a=background|background-color td=height"
		if(strpos($value, ',') !== false) $value = str_replace(',', ' ', $value);
		if(strpos($value, "\n") !== false) $value = str_replace("\n", ' ', $value);
		foreach(explode(' ', strtolower($value)) as $style) {
			if(empty($style)) continue;
			if(strpos($style, '=')) {
				list($element, $style) = explode('=', $style, 2);
				$styleNames = explode('|', $style);
			} else {
				$element = '*';
				$styleNames[] = $style;
			}
			if(strpos($element, '|')) {
				$elements = explode('|', $element);
			} else {
				$elements = array($element);
			}
			foreach($elements as $element) {
				if(isset($invalidStyles[$element])) {
					$a[$element] = array_unique(array_merge($a[$element], $styleNames));
				} else {
					$a[$element] = $styleNames;
				}
			}
		}
		foreach($a as $element => $styles) {
			$a[$element] = implode(' ', $styles); // convert to string
		}
		return $a;
	}

	/**
	 * Convert invalid_styles array to string
	 * 
	 * @param array $a
	 * @return string
	 * 
	 */
	public function invalidStylesArrayToStr(array $a) {
		$str = '';
		$elementsByStyle = array();
		foreach($a as $element => $styles) {
			if($element === '*') {
				$str .= " $styles";
			} else if(strpos($styles, ' ') === false) {
				if(!isset($elementsByStyle[$styles])) $elementsByStyle[$styles] = array();
				$elementsByStyle[$styles][] = $element;
			} else {
				$str .= " $element=" . str_replace(' ', '|', $styles);
			}
		}
		foreach($elementsByStyle as $style => $elements) {
			$str .= " " . implode('|', $elements) . "=$style";
		}
		return trim($str);
	}
	
}