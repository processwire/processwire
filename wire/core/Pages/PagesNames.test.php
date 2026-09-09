<?php namespace ProcessWire;

/**
 * Tests for PagesNames
 *
 */
class WireTest_PagesNames extends WireTest {

	public function execute() {
		$this->testPageNameFromTitlePeriods();
	}

	/**
	 * Test that generating a name from a title handles periods sensibly
	 *
	 * Abbreviation and sentence periods are stripped, periods between digits and in
	 * whitespace-free (filename-style) titles are kept, and explicitly assigned page
	 * names are unaffected. See processwire/processwire-issues#1305
	 *
	 */
	protected function testPageNameFromTitlePeriods() {
		$pages = $this->wire()->pages;
		$names = $pages->names();
		$page = $this->getTestPage();
		$title = $page->title;
		$page->of(false);

		try {
			// abbreviation and sentence periods are stripped before the name is generated
			$tests = array(
				'A status report for the U.S. Virgin Islands' => 'a-status-report-for-the-us-virgin-islands',
				'Here.After by John Smith' => 'hereafter-by-john-smith',
				'Mr. Smith Goes to Washington' => 'mr-smith-goes-to-washington',
				'No. 1 hit single' => 'no-1-hit-single',
				'A sentence. And another' => 'a-sentence-and-another',
			);
			foreach($tests as $t => $expected) {
				$page->title = $t;
				$this->check("Name from title: $t", $expected, $names->pageNameFromFormat($page, 'title'));
			}

			// periods between digits and in whitespace-free (filename-style) titles pass
			// through unchanged, whatever the configured page name charset/whitelist allows
			foreach(array('PHP 8.4.1 is now available', 'sitemap.xml') as $t) {
				$page->title = $t;
				$this->check("Name from title keeps periods: $t",
					$this->sanitizedName($t), $names->pageNameFromFormat($page, 'title'));
			}

			// explicitly assigned page names keep their periods
			$this->check('Explicit page names keep periods', 'my-page.xml',
				$this->wire()->sanitizer->pageName('my-page.xml', Sanitizer::translate));

		} finally {
			$page->title = $title;
		}
	}

	/**
	 * Sanitize the given text the same way pageNameFromFormat() does after period handling
	 *
	 * @param string $name
	 * @return string
	 *
	 */
	protected function sanitizedName($name) {
		$sanitizer = $this->wire()->sanitizer;
		$utf8 = $this->wire()->config->pageNameCharset === 'UTF8';
		return $utf8 ? $sanitizer->pageNameUTF8($name) : $sanitizer->pageName($name, Sanitizer::translate);
	}
}
