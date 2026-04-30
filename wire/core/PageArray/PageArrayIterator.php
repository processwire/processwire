<?php namespace ProcessWire;

/**
 * PageArrayIterator for iteration of Page objects in a lazy-loaded fashion
 * 
 * The custom Iterator that finds real Pages in chunks (in advance, and on demand), enabling memory 
 * safety while maintaining reasonable speeds when iterating over a large set of Pages.
 * 
 * Thanks to Avoine and @sforsman for this. 
 *
 */
class PageArrayIterator extends Wire implements \Iterator {
	
	/**
	 * Placeholder objects for pages
	 * 
	 * @var array
	 * 
	 */
	protected $lazypages;

	/**
	 * Current buffer of real pages
	 * 
	 * @var array
	 * 
	 */
	protected $pages;

	/**
	 * Holds the options originally given by the user for Pages::find()
	 * 
	 * @var array
	 * 
	 */ 
	protected $options = array();

	/**
	 * Current position
	 * 
	 * @var int
	 * 
	 */
	protected $position = 0;
	
	/**
	 * Current chunk
	 * 
	 * @var
	 * 
	 */
	protected $currentChunk;
	
	/**
	 * @var int
	 * 
	 */
	protected $pagesPosition = 0;
	
	/**
	 * Number of pages in current chunk
	 * 
	 * @var int
	 * 
	 */
	protected $pagesCount = 0;

	/**
	 * Determines how many pages to load in advance. 
	 * 
	 * Could be adjusted or suggested automatically based on memory_limit, for an example.
	 * 
	 * @var int
	 * 
	 */ 
	protected $chunkSize = 250;

	/**
	 * @var string
	 * 
	 */
	protected $cacheGroup = '';
	
	/**
	 * Construct
	 * 
	 * @param array $lazypages
	 * @param array $options Options provided to $pages->find()
	 * 
	 */
	public function __construct(array $lazypages, array $options = []) {
		$this->lazypages = $lazypages;
		$this->options = $options;
	}

	/**
	 * Retrieves the next chunk of real pages
	 * 
	 */
	protected function loadChunk() {
		$this->chunkSize = (int) $this->wire()->config->lazyPageChunkSize;
		$this->pagesPosition = 0;
		$start = $this->currentChunk++ * $this->chunkSize;
		$pages = $this->wire()->pages;
		
		if($this->cacheGroup) {
			$this->wire()->pages->cacher()->uncacheGroup($this->cacheGroup);
		}

		// If the starting position exceeds the amount of placeholder objects, we just issue an empty
		// PageArray, which causes the loop to stop (because valid() will return false)
		if(!isset($this->lazypages[$start])) {
			
			$this->pages = $pages->newPageArray();
			
		} else {
			
			// Check if the user gave options for the loading
			$options = isset($this->options['loadOptions']) ? $this->options['loadOptions'] : array();

			// Here we retrieve a chunk of Page objects and loop over them to retrieve the IDs of the Pages.
			$lazypages = array_slice($this->lazypages, $start, $this->chunkSize);
			$ids = array();
			
			foreach($lazypages as $page) {
				// Grab the ID from the placeholder object. We are using the internal method here which does
				// not cause the real Page to be loaded. We only need to collect the IDs to request a chunk 
				// of real Page-objects from Pages::getById()
				$ids[] = $page->id;
			}
			
			$this->cacheGroup = 'lazy' . md5(implode(',', $ids));
			$options['cache'] = $this->cacheGroup;

			$debug = $pages->debug();
			if($debug) $pages->debug(false);
			$this->pages = $pages->getById($ids, $options);
			if($debug) $pages->debug(true);
		}

		$this->pagesCount = count($this->pages);
	}
	
	/**
	 * Rewind to beginning
	 * 
	 */
	#[\ReturnTypeWillChange]
	public function rewind() {
		$this->pagesPosition = 0;
		$this->position = 0;
		$this->currentChunk = 0;
		$this->pagesCount = 0;
		$this->pages = null;
	}
	
	/**
	 * Get current Page
	 * 
	 * @return Page
	 * 
	 */
	#[\ReturnTypeWillChange]
	public function current() {
		return $this->pages[$this->pagesPosition];
	}
	
	/**
	 * Get current key/position
	 * 
	 * @return int
	 * 
	 */
	#[\ReturnTypeWillChange]
	public function key() {
		return $this->position;
	}
	
	/**
	 * Update current position to next
	 * 
	 */
	#[\ReturnTypeWillChange]
	public function next() {
		$this->pagesPosition++;
		$this->position++;
	}
	
	/**
	 * Return whether or not there are more items after current position
	 * 
	 * @return bool
	 * 
	 */
	#[\ReturnTypeWillChange]
	public function valid() {
		if($this->position === 0 || $this->pagesPosition >= $this->pagesCount) {
			// If we have just been rewound or if we have reached the end of the buffer,
			// we will load the next chunk.
			$this->loadChunk();
		}
		// We have reached the end when no Pages were loaded
		return ($this->pagesCount > 0);
	}
}
