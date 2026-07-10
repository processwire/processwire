<?php namespace ProcessWire;

/**
 * Tests for ProcessWire MarkupRSS
 *
 */
class WireTest_MarkupRSS extends WireTest {

	/**
	 * @var string
	 *
	 */
	protected $originalTitle = '';

	/**
	 *  Page|null
	 *
	 */
	protected $rssPage = null;

	public function init() {
		$this->rssPage = $this->wire()->pages->get(1);
		$this->originalTitle = (string) $this->rssPage->title;
	}

	public function execute() {
		$this->testRenderFeedBasics();
		$this->testDescriptionModesAndContent();
		$this->testAuthorAndRenderOutput();
	}

	public function finish() {
		if($this->rssPage && $this->originalTitle !== '') $this->rssPage->title = $this->originalTitle;
	}

	/**
	 * Test feed header and item basics.
	 *
	 */
	protected function testRenderFeedBasics() {
		$page = $this->rssPage;
		$page->of(false);
		$page->title = 'RSS <Title> & More';

		$rss = $this->newRss(array(
			'title' => 'Feed <Title> & More',
			'url' => 'https://example.com/feed.xml?x=1&y=2',
			'description' => 'Feed description & details',
			'copyright' => 'Copyright <2026>',
			'ttl' => 30,
			'itemTitleField' => 'title',
			'itemDescriptionField' => 'title',
			'itemDateField' => 'created',
		));

		$xml = $rss->renderFeed($this->pageArray($page));

		$this->check('renderFeed() starts XML document', '<?xml version="1.0" encoding="utf-8" ?>', substr($xml, 0, 39));
		$this->check('renderFeed() includes RSS root', true, strpos($xml, '<rss version="2.0"') !== false);
		$this->check('renderFeed() escapes channel title', true, strpos($xml, '<title>Feed &#x0003C;Title&#x0003E; &#x00026; More</title>') !== false);
		$this->check('renderFeed() escapes feed URL in atom link', true, strpos($xml, 'href="https://example.com/feed.xml?x=1&#x00026;y=2"') !== false);
		$this->check('renderFeed() includes ttl', true, strpos($xml, '<ttl>30</ttl>') !== false);
		$this->check('renderFeed() escapes item title', true, strpos($xml, '<title>RSS  &#x00026; More</title>') !== false);
		$this->check('renderFeed() includes item link', true, strpos($xml, '<link>' . $page->httpUrl . '</link>') !== false);
		$this->check('renderFeed() includes item pubDate', true, strpos($xml, '<pubDate>' . date(DATE_RSS, $page->created) . '</pubDate>') !== false);
	}

	/**
	 * Test truncated text descriptions and full content CDATA.
	 *
	 */
	protected function testDescriptionModesAndContent() {
		$page = $this->rssPage;
		$page->of(false);
		$page->title = 'RSS body test';
		$page->set('wire_test_rss_summary', '<p>First sentence. Second sentence should not fully appear.</p>');
		$page->set('wire_test_rss_body', '<p><a href="/about/">About</a><img src="/site/templates/test.png"><a href="#part">Part</a><![CDATA[test]]></p>');

		$rss = $this->newRss(array(
			'itemDescriptionField' => 'wire_test_rss_summary',
			'itemDescriptionLength' => 18,
			'itemContentField' => 'wire_test_rss_body',
		));

		$xml = $rss->renderFeed($this->pageArray($page));

		$this->check('renderFeed() strips tags in truncated description', true, strpos($xml, '<![CDATA[First sentence.]]>') !== false);
		$this->check('renderFeed() declares content namespace when content field set', true, strpos($xml, 'xmlns:content="http://rss.namespace.org/content/"') === false);
		$this->check('renderFeed() declares RSS content namespace', true, strpos($xml, 'xmlns:content="http://purl.org/rss/1.0/modules/content/"') !== false);
		$this->check('renderFeed() renders content encoded element', true, strpos($xml, '<content:encoded><![CDATA[') !== false);
		$this->check('renderFeed() converts root-relative href', true, strpos($xml, 'href="' . $this->wire()->config->urls->httpRoot . 'about/"') !== false);
		$this->check('renderFeed() converts root-relative src', true, strpos($xml, 'src="' . $this->wire()->config->urls->httpRoot . 'site/templates/test.png"') !== false);
		$this->check('renderFeed() converts anchor href to page URL', true, strpos($xml, 'href="' . $page->httpUrl . '#part"') !== false);
		$this->check('renderFeed() escapes nested CDATA start marker', true, strpos($xml, '&lt;![CDATA[test]]&gt;') !== false);

		$rss = $this->newRss(array(
			'itemDescriptionField' => 'wire_test_rss_body',
			'itemDescriptionLength' => 0,
		));
		$xml = $rss->renderFeed($this->pageArray($page));
		$this->check('renderFeed() preserves HTML description when length is zero', true, strpos($xml, '<description><![CDATA[<p><a href="') !== false);
	}

	/**
	 * Test author handling and render() echo path.
	 *
	 */
	protected function testAuthorAndRenderOutput() {
		$page = $this->rssPage;
		$page->of(false);
		$page->title = 'RSS author test';

		$rss = $this->newRss(array(
			'itemAuthorField' => 'createdUser',
			'itemAuthorElement' => 'author',
		));
		$xml = $rss->renderFeed($this->pageArray($page));
		$author = $page->createdUser->get('title|name');

		$this->check('renderFeed() renders author from Page field', true, strpos($xml, '<author>' . $author . '</author>') !== false);

		ob_start();
		$result = $rss->render($this->pageArray($page));
		$output = ob_get_clean();
		$this->check('render() returns true', true, $result);
		$this->check('render() echoes feed XML', true, strpos($output, '<rss version="2.0"') !== false);
	}

	/**
	 * Make configured MarkupRSS instance.
	 *
	 * @param array $settings
	 * @return MarkupRSS
	 *
	 */
	protected function newRss(array $settings = array()) {
		$rss = $this->wire(new MarkupRSS());
		$rss->setArray(array_merge(array(
			'title' => 'WireTest RSS',
			'url' => 'https://example.com/rss.xml',
			'description' => 'WireTest feed',
			'itemTitleField' => 'title',
			'itemDescriptionField' => 'title',
			'itemDescriptionLength' => 1024,
			'itemContentField' => '',
			'itemDateField' => 'created',
		), $settings));
		return $rss;
	}

	/**
	 * Make PageArray with given page.
	 *
	 * @param Page $page
	 * @return PageArray
	 *
	 */
	protected function pageArray(Page $page) {
		$items = $this->wire(new PageArray());
		$items->add($page);
		return $items;
	}
}
