<?php namespace ProcessWire;

/**
 * ProcessWire Markup File Regions
 * 
 * #pw-headline Markup File Regions
 * #pw-summary Enables you to use the Markup Regions system to populate CSS and JS files.
 * #pw-body = 
 * File regions are an experimental part of ProcessWire’s [Markup Regions](https://processwire.com/docs/front-end/output/markup-regions/) 
 * output strategy. File regions enable you to define CSS, JS, SCSS or LESS in your markup alongside the markup that is styled
 * or manipulated by it, keeping everything together as a single component, in cases where it's worthwhile. Unlike inline styles 
 * or scripts, ProcessWire takes care of moving these assets to one or more external asset files. These external asset files are 
 * automatically updated whenever you make a change to your file regions. 
 * 
 * File regions require ProcessWire 3.0.254 or newer. They are not enabled by default. 
 * Markup Regions with File Regions are enabled with `$config->useMarkupRegions = 2;` in your /site/config.php file.
 * More about: [Markup Regions](https://processwire.com/docs/front-end/output/markup-regions/).
 * 
 * #### How to use file regions for CSS: 
 * 
 * Add a `<link>` tag like the following in the HTML `<head>` section:
 * ~~~~~
 * <link rel="stylesheet" href="main.css">
 * ~~~~~
 * Now you can populate the file linked above from one or more file regions, anywhere 
 * in your output using a `<style>` tag. In the `<style>` tag, you must specify a "pw-file" 
 * attribute, and either an "id" or "pw-id" attribute containing a unique ID for your file region.
 * This unique ID is how ProcessWire will keep track of it and know when to update it. 
 * ~~~~~ 
 * <style id="hello-world" pw-file="main.css">
 *    .hello-world {
 *      color: red;
 *    }
 * </style>
 * ~~~~~
 * That's all that is necessary. Any time you make a change to the region above, it will be 
 * automatically updated in the main.css file. Note that the `<style>` tags do not appear
 * in the final document output, as their contents is moved to the main.css file. 
 * 
 * Now let's add another region to the main.css file, from somewhere else in our output: 
 * ~~~~~
 * <style id="foo-bar" pw-file="main.css">
 *   .foo { color: green }
 *   .bar { color: blue }
 * </style>
 * ~~~~~
 * Following the above, ProcessWire will now be maintaining regions named `hello-world` and `foo-bar`
 * in a main.css file. The main.css file (or whatever filename you choose)
 * is located in /site/assets/markup-regions/. You can add as many file regions as you want,
 * whether they all point to the same file or point to multiple files.
 * 
 * #### How to use file regions for JS
 * 
 * Exactly the same as using it for CSS, but use `<script>` tags instead. Place the 
 * script tag in your head, before the closing body tag, or wherever you want it:
 * ~~~~~
 * <script src="main.js"></script> 
 * ~~~~~
 * Then you can populate that main.js file anywhere in your output, from any number of
 * script tags that point to main.js: 
 * ~~~~~
 * <script id="test-js" pw-file="main.js">
 *   alert('This is a test');
 * </script>
 * ~~~~~
 * 
 * #### How to use file regions for SCSS/LESS
 * 
 * This would be the same as for CSS except that you'll be responsible for compiling 
 * the SCSS/LESS file, and pointing to the resulting CSS file, just as you would without
 * file regions. Your <link> tag will be left as-is.
 * ~~~~~
 * <link rel="stylesheet" href="<?=$config->urls->markupRegions?>test.css">
 * ~~~~~
 * Now you can define your SCSS/LESS anywhere in the output, and it will populate
 * a /site/assets/markup-regions/test.scss (or .less) file. But you'll have to use
 * whatever tool you want to compile it to a CSS file. 
 * ~~~~~
 * <style id="my-test-scss" pw-file="test.scss">
 *   $alert-background: red; 
 *   $alert-text: white;
 *   .alert { 
 *      color: $alert-text;
 *      background-color: $alert-background; 
 *   }
 * </style>
 * ~~~~~
 * Let's say that we were using ProCache to compile our SCSS or LESS, we could
 * compile and link to it like this: 
 * ~~~~~
 * $css = $procache->css([ $config->urls->markupRegions . 'test.scss' ]);
 * echo "<link rel='stylesheet' href='$css'>";
 * ~~~~~
 * 
 * #### Deleting regions
 * 
 * ProcessWire manages adding and updating regions, but if you need to delete a
 * region, just delete the entire file from /site/assets/markup-regions/
 * and ProcessWire will re-create it on the next page load, without your deleted
 * region. Perhaps a future version will be able to detect deleted regions somehow
 * or another. But since the same CSS file (for example) might be populated with
 * multiple different regions over multiple requests for different pages, ProcessWire
 * can't assume that it knows the full scope of regions in one file from any single request.
 * For that reason, it can't safely assume that a particular region has been deleted.
 * But it can easily re-create any files that you delete, and doing so will ensure
 * they do not contain CSS or JS for old/deleted regions. 
 *
 * #### Other tips
 * 
 * For CSS or JS you can specify `pw-file` as a boolean attribute and it will
 * assume the file "main.css" or "main.js", i.e. `<style id="test" pw-file>…</style>`
 * 
 * File regions are not meant to replace the traditional way of managing CSS and JS. 
 * Instead, see it as another tool to use when it fits the need.
 *
 * Defining file regions in this way means that you can use PHP code and variables 
 * as part of your CSS/JS/SCSS/LESS, should that be useful. 
 * 
 * When building components for a site or application, and depending on the case, 
 * it can sometimes be helpful to maintain the PHP, markup, CSS and JS together, 
 * like in the example below. Just one file to launch something new, and ProcessWire
 * takes care of moving them to externally linked assets. 
 * ~~~~~
 * <ul id="items">
 *   <?php 
 *   foreach($pages->get('/items/')->children as $item) {
 *     echo "<li class='item'>$item->title</li>";
 *   }
 *   ?>
 * </ul>
 * 
 * <style id="items-css" pw-file>
 *   #items .item {
 *     border-bottom: 1px solid black;
 *     font-size: 1rem;
 *   }
 * </style>
 * 
 * <script id="items-js" pw-file>
 *   $(function() {
 *      $('.item').on('click', function() {
 *        alert("You clicked on: " + $(this).text()); 
 *      }); 
 *   }); 
 * </script>
 * ~~~~~
 * #pw-body
 * 
 *
 * ProcessWire 3.x, Copyright 2025 by Ryan Cramer
 * https://processwire.com
 * 
 * @since 3.0.254
 *
 */
class WireMarkupFileRegions extends Wire {
	
	/**
	 * Get default settings
	 * 
	 * @return array
	 * 
	 */
	public function getDefaults() {
		return [
			'path' => $this->wire()->config->paths->markupRegions,
			'action' => 'file',
			'tags' => [ 
				'style' => 'main.css', 
				'script' => 'main.js' 
			],
			'exts' => [ 
				'css', 
				'scss', 
				'sass', 
				'less', 
				'js' 
			],
			'allowPaths' => false,
			'autoInsert' => false,
			'useVersion' => true, 
		];
	}
	
	/**
	 * Namespaces that have specific meanings in file regions
	 * 
	 * @var string[] 
	 * 
	 */
	protected $reservedNamespaces = [
		'ready' => 'ready',
		'loaded' => 'loaded',
		'onload' => 'loaded',
	];
	
	/**
	 * Boolean namespaces that map to reserved namespaces
	 * 
	 * @var string[] 
	 * 
	 */
	protected $booleanNamespaces = [
		'pw-ready' => 'ready', 
		'pw-loaded' => 'loaded',
		'pw-onload' => 'loaded',
	];
	
	/**
	 * Errors found by findRegions() method 
	 * 
	 * @var array 
	 * 
	 */
	protected $errors = [];
	
	/**
	 * Debugging notes
	 * 
	 * @var array 
	 * 
	 */
	protected $notes = [];
	
	/**
	 * Apply file regions in given HTML document and regions markup
	 * 
	 * @param string $htmlDocument
	 * @param string $htmlRegions
	 * @param array $options
	 * @return int Number of file regions
	 * 
	 */
	public function apply(&$htmlDocument, &$htmlRegions, array $options = []) {
		
		$options = array_merge($this->getDefaults(), $options);
		$fileRegions = $this->findRegions($htmlDocument, $options);
		
		foreach($this->findRegions($htmlRegions, $options) as $key => $a) {
			if(isset($fileRegions[$key])) {
				$fileRegions[$key] = array_merge($fileRegions[$key], $a);
			} else {
				$fileRegions[$key] = $a;
			}
		}
		
		if(count($fileRegions)) {
			$htmlDocument .= $this->populateRegions($fileRegions, $htmlDocument, $options);
		}
		
		return count($fileRegions);
	}
	
	/**
	 * Find and return file regions in given HTML
	 *
	 * @param string $html
	 * @param array $options
	 *  - `action` (string): The "pw-[action]" or "data-pw-[action]" attribute to look for (default='file')
	 *  - `tags` (array): Array of tag names to allow for action, and default action value (when non specified)
	 *  - `exts` (array): File extensions to allow in action value (default=['css','less', 'scss','sass','js'])
	 *  - `allowPaths` (bool): Allow paths in action value? (default=false)
	 * @return array
	 * @since 3.0.254
	 *
	 */
	public function findRegions(&$html, array $options) {
		
		$action = $options['action'];
		
		$this->errors = [];
		$this->notes = [];
		
		if(strpos($html, "pw-$action") === false) return [];
		
		$tagNames = implode('|', array_keys($options['tags']));
		
		$rx =
			'!<(' . $tagNames . ')\s([^>]*?' .  // 1:tag, 2:attrs
			'((?:data-)?pw-' . $action . '=[^\s>]+|(?:data-)?pw-' . $action . ')' . // 3:pw-file or pw-file=value
			'[^>]*)>(.*?)</\\1>!is'; // 4:content of region
		
		if(!preg_match_all($rx, $html, $matches)) return [];
		
		$regions = [];
		$valueRemovals = [
			"'", '"',
			"data-pw-$action=",
			"pw-$action=",
			"data-pw-$action",
			"pw-$action"
		];
		
		foreach($matches[0] as $key => $fullMatch) {
			$id = '';
			$tag = strtolower($matches[1][$key]);
			$attrs = $matches[2][$key];
			$value = str_replace($valueRemovals, '', $matches[3][$key]);
			$content = $matches[4][$key];
			$open = "<$tag $attrs>";
			$context = $open . str_replace(["\n", "\t"], " ", substr($content, 0, 30)) . '…';
			$namespace = false;
			$errors = [];
			
			if(empty($value)) {
				// use default value if boolean pw-action attribute used
				$value = $options['tags'][$tag];
			}
			
			if(!$options['allowPaths']) {
				$v = $value;
				$value = basename($value);
				if($v !== $value) {
					$errors[] = "Paths are not allowed in pw-$action value";
				}
			}
			
			if(!empty($exts)) {
				$ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
				if(!in_array($ext, $exts)) {
					$errors[] = "Unsupported extension for pw-$action region: $ext";
				}
			}
			
			if(strpos($attrs, 'id=') !== false) {
				$idrx = '!\b(pwid|pw-id|id|data-pwid|data-pw-id)=([^\s>]+)!';
				if(preg_match($idrx, $attrs, $attrMatch)) {
					$id = trim($attrMatch[2], "'\"");
				}
			} else {
				$errors[] = "An 'id' or 'pw-id' attribute is required for 'pw-$action' regions";
			}
			
			foreach($errors as $error) {
				$this->addError($context, $error);
			}
	
			if(strpos($open, 'pw-ns') || strpos($open, 'pw-namespace')) {
				if(preg_match('!\bpw-(?:ns|namespace)(?:=(["\'])(.+?)\\1)?!', $open, $match)) {
					$namespace = isset($match[2]) ? $match[2] : true;
				}
			} else {
				foreach($this->booleanNamespaces as $attrName => $ns) {
					if(strpos($open, $attrName) === false) continue;
					$namespace = $ns;
					break;
				}
			}
			
			if(!isset($regions[$value])) $regions[$value] = [];
			
			$regions[$value][$id] = [
				'name' => $tag,
				'pwid' => $id,
				'open' => $open,
				'close' => "</$tag>",
				'attrs' => $attrs,
				'classes' => [],
				'action' => $action,
				'actionType' => 'attr',
				'actionTarget' => $value,
				'error' => count($errors) > 0, 
				'details' => implode('. ', $errors), 
				'region' => $content,
				'html' => $fullMatch,
				'namespace' => $namespace,
			];
			
			$html = str_replace($fullMatch, '', $html);
		}
		
		return $regions;
	}
	
	/**
	 * Populate file regions
	 *
	 * @param array $fileRegions Regions found by findRegions()
	 * @param string $html HTML to populate them into
	 * @param array $options
	 * @return string Returned value only useful if autoInsert=true
	 * @since 3.0.254
	 *
	 */
	public function populateRegions(array $fileRegions, &$html, array $options = []) {
		
		$config = $this->wire()->config;
		$files = $this->wire()->files;
	
		$numUpdates = 0;
		$fileContentsItems = [];
		$fileUpdates = [];
		$filesExist = [];
		$out = '';
		$a = [];
		
		if(!is_dir($options['path'])) $files->mkdir($options['path']);
		$startTime = filemtime($options['path']);
		
		// re-organize file regions so that those sharing same pwid are together
		foreach($fileRegions as $value => $regions) {
			if(!isset($a[$value])) $a[$value] = [];
			foreach($regions as $region) {
				$pwid = $region['pwid'];
				if(!isset($a[$value])) $a[$value] = [];
				if(!isset($a[$value][$pwid])) $a[$value][$pwid] = [];
				$a[$value][$pwid][] = $region;
			}
		}
		
		$fileRegions = $a;
		
		foreach($fileRegions as $file => $pwidRegions) {
			
			$filename = $options['path'] . $file;
			$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
			
			if(isset($fileContentsItems[$file])) {
				$fileContents = $fileContentsItems[$file];
				$filesExist[$file] = true;
			} else {
				$fileExists = file_exists($filename);
				$filesExist[$file] = $fileExists;
				if($fileExists) {
					$fileContents = $files->fileGetContents($filename);
				} else {
					$fileContents = '';
				}
				$fileContentsItems[$file] = $fileContents;
			}
			
			foreach($pwidRegions as $pwid => $regions) {
				
				$landmarkOpen = "/*+$pwid*/";
				$landmarkClose = "/*-$pwid*/";
				$newContent = '';
				$skip = false;
				
				foreach($regions as $region) {
					if($region['error']) $skip = true;
					if($region['namespace'] !== false) $this->applyRegionNamespace($region);
					$newContent .= $region['region'];
				}
				
				if($skip) continue;
				
				$landmarkContent = $landmarkOpen . $newContent . $landmarkClose;
				
				if(empty($fileContents)) {
					$fileContents = strlen(trim($newContent)) ? $landmarkContent : '';
					$fileContentsItems[$file] = $fileContents . "\n";
					$fileUpdates[$file] = $filename;
					continue;
				}
				
				if(strpos($fileContents, $landmarkOpen) !== false) {
					// updating existing section
					if(strlen(trim($newContent)) && strpos($fileContents, $landmarkContent) !== false) {
						// already there and up-to-date, we do not need anything else
						continue;
					}
					list($before, $after) = explode($landmarkOpen, $fileContents, 2);
					list(/*$oldContent*/, $after) = explode($landmarkClose, $after, 2);
					$fileContents = (strlen($before) ? "$before\n" : '');
					$fileContents .= "$landmarkContent\n$after";
					$this->addNote("Updated #$pwid in $file");
				} else {
					// adding new section
					if(strlen($fileContents)) $fileContents .= "\n";
					$fileContents .= $landmarkContent . "\n";
					$this->addNote("Added #$pwid to $file");
				}
				
				$fileContentsItems[$file] = $fileContents;
				$fileUpdates[$file] = $filename;
				
				unset($newContent, $landmarkContent);
			}
			
			$url = str_replace($config->paths->root, $config->urls->root, $filename); 
			if($options['useVersion']) $url .= "?v=" . dechex(abs($startTime - filemtime($filename)));
			
			$basename = basename($filename);
			$out .= $this->applyRegionLinks($html, $basename, $url, $ext, $options);
		}
		
		foreach($fileUpdates as $basename => $filename) {
			$fileContents = $fileContentsItems[$basename];
			while(strpos($fileContents, "\n\n/*") !== false) {
				$fileContents = str_replace("\n\n/*", "\n/*", $fileContents);
			}
			$fileContents = trim($fileContents);
			if($files->filePutContents($filename, $fileContents, LOCK_EX)) {
				if(empty($filesExist[$basename])) $this->addNote("Created file: $basename");
			} else {
				$this->addError($basename, "Cannot write to $basename"); 
			}
			$numUpdates++;
		}
		
		return $out;
	}
	
	/**
	 * Apply namespace to region content
	 * 
	 * @param array $region
	 * 
	 */
	protected function applyRegionNamespace(array &$region) {
		$sanitizer = $this->wire()->sanitizer;
		$namespace = $region['namespace'];
		$ext = strtolower(pathinfo($region['actionTarget'], PATHINFO_EXTENSION)); 
		if(in_array($ext, [ 'css', 'scss', 'sass', 'less' ])) {
			if(is_string($namespace) && strlen($namespace)) {
				if(isset($this->reservedNamespaces[$namespace])) {
					// reserved namespace not used by css
				} else {
					// use namespace as the parent rule in nested css
					$region['region'] = "\n$namespace {" . $region['region'] . "}";
				}
			} else {
				// boolean namespace not used for css 
			}
		} else if($ext === 'js') {
			if(isset($this->reservedNamespaces[$namespace])) {
				$namespace = $this->reservedNamespaces[$namespace];
			}
			if($namespace === 'ready') {
				// document.ready 
				$region['region'] = "\ndocument.addEventListener('DOMContentLoaded', (event) => {" . $region['region'] . "});";
			} else if($namespace === 'loaded') {
				// window.onload
				$region['region'] = "\nwindow.onload = () => {" . $region['region'] . "};";
			} else {
				// boolean or named namespace puts JS within JS arrow IIFE
				$namespace = is_string($namespace) && strlen($namespace) ? "/*" . $sanitizer->name($namespace) . "*/" : "";
				$region['region'] = "\n(($namespace) => {" . $region['region'] . "})();";
			}
		}	
	}
	
	/**
	 * Apply region links (optional)
	 * 
	 * For example: Converts the `main.css` in `<link rel="stylesheet" href="main.css">` 
	 * to `/site/assets/markup-regions/main.css`.
	 * 
	 * If the autoInsert option is enabled and there is no existing `main.css` to update then it will return
	 * a string with the `<link>` tag to main.css in it. 
	 * 
	 * @param string $html 
	 * @param string $basename
	 * @param string $url
	 * @param string $ext
	 * @param array $options
	 * @return string
	 * @since 3.0.254
	 * 
	 */
	protected function applyRegionLinks(&$html, $basename, $url, $ext, array $options) {
		$out = '';
		if($ext === 'css') {
			list($href1, $href2) = [ " href=\"$basename\"", " href='$basename'" ];
			if(stripos($html, $href1) || stripos($html, $href2)) {
				$rx = '!(<link [^>]*?href=["\'])' . preg_quote($basename) . '(["\'])!i';
				$html = preg_replace($rx, '$1' . $url . '$2', $html);
			} else if($options['autoInsert']) {
				$out .= "<link rel='stylesheet' href='$url' pw-append='head'>";
			}
		} else if($ext === 'js') {
			list($src1, $src2) = [ " src=\"$basename\"", " src='$basename'" ];
			if(stripos($html, $src1) || stripos($html, $src2)) {
				$rx = '!(<script [^>]*?src=["\'])' . preg_quote($basename) . '(["\'])!i';
				$html = preg_replace($rx, '$1' . $url . '$2', $html);
			} else if($options['autoInsert']) {
				$out .= "<script src='$url' pw-append='body'></script>";
			}
		}
		return $out;
	}
	
	/**
	 * Add a debug note 
	 * 
	 * @param string $note
	 * 
	 */
	protected function addNote($note) {
		$this->notes[] = $note;
	}
	
	/**
	 * Get debug note
	 * 
	 * @return array
	 * 
	 */
	public function getNotes() {
		return $this->notes;
	}
	
	/**
	 * Add an error 
	 * 
	 * @param string $key Error context
	 * @param string $error Error message
	 * 
	 */
	protected function addError($key, $error) {
		$key = str_replace([ "\n", "\t" ], ' ', $key);
		while(strpos($key, '  ') !== false) $key = str_replace('  ', ' ', $key);
		if(!isset($this->errors[$key])) $this->errors[$key] = [];
		$this->errors[$key][] = $error;
	}
	
	/**
	 * Get errors
	 * 
	 * @param string $key Optional context 
	 * @return array
	 * 
	 */
	public function getErrors($key = '') {
		if($key) return isset($this->errors[$key]) ? $this->errors[$key] : [];
		return $this->errors;
	}
}
