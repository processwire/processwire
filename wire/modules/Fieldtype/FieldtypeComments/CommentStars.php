<?php namespace ProcessWire;

/**
 * Class CommentStars
 *
 * Handles rendering of star ratings for comments.
 * Additional code in comments.js and comments.css accompanies this.
 *
 * Copyright 2016 by Ryan Cramer for ProcessWire
 * 
 * @property int $numStars Max number of stars
 * @property string $star Default star output (can be overridden with HTML)
 * @property string $starOn Optionally use this star/HTML for ON state
 * @property string $starOff Optionally use this star/HTML for OFF state
 * @property string $starClass Class for stars whether on or off
 * @property string $starOnClass Class for active/on stars
 * @property string $starOffClass Class for inactive/off stars
 * @property string $starPartialClass Class for partial (half lit star)
 * @property string $wrapClass Wrapping element class, required for JS and CSS
 * @property string $wrapClassInput Wrapping input element class, required for JS and CSS
 * @property string $countClass Class used for the renderCount() method
 * @property string $detailsLabel Star details label
 * @property string $countLabelSingular Single count label
 * @property string $countLabelPlural Plural count label
 * @property string $unratedLabel Unrated label
 *
 */

class CommentStars extends WireData {

	protected static $defaults = array(
		'numStars' => 5, // max number of stars
		'star' => '★', // this may be overridden with HTML (icons for instance)
		'starOn' => '', // optionally use separate star for ON...
		'starOff' => '', // ...and OFF
		'starClass' => 'CommentStar', // applies to both on and off
		'starOnClass' => 'CommentStarOn', // class assigned to active/on stars
		'starOffClass' => 'CommentStarOff', // class assigned to inactive/off stars
		'starPartialClass' => 'CommentStarPartial',
		'wrapClass' => 'CommentStars', // required by JS and CSS
		'wrapClassInput' => 'CommentStarsInput', // required by JS and CSS
		'countClass' => 'CommentStarsCount', // class used for renderCount() method
		'detailsLabel' => '%s/%s', // i.e. 4.5/5
		'countLabelSingular' => '', // i.e. 4/5 via 1 rating
		'countLabelPlural' => '', // i.e. 4.5/5 via 10 ratings
		'unratedLabel' => '',
	);

	/**
	 * Construct comment stars
	 *
	 */
	public function __construct() {
		foreach(self::$defaults as $key => $value) {
			$this->set($key, $value);
		}
		if(!strlen($this->countLabelSingular)) $this->set('countLabelSingular', $this->_('%1$s (%2$s rating)'));
		if(!strlen($this->countLabelPlural)) $this->set('countLabelPlural', $this->_('%1$s (%2$s ratings)'));
		if(!strlen($this->unratedLabel)) $this->set('unratedLabel', $this->_('not yet rated'));
	}

	/**
	 * Change one of the defaults (see $defaults)
	 *
	 * Example: CommentStars::setDefault('star', '<i class="fa fa-star"></i>');
	 *
	 * @param $key
	 * @param $value
	 *
	 */
	public static function setDefault($key, $value) {
		self::$defaults[$key] = $value;
	}

	/**
	 * Render stars
	 *
	 * If given a float for $stars, it will render partial stars.
	 * If given an int for $stars, it will only render whole stars.
	 *
	 * @param int|float|null $stars Number of stars that are in active state
	 * @param bool $allowInput Whether to allow input of stars
	 * @return string
	 *
	 */
	public function render($stars = 0, $allowInput = false) {

		$class = $allowInput ? "$this->wrapClass $this->wrapClassInput" : $this->wrapClass;
		if(!$this->starOn) $this->starOn = $this->star;
		if(!$this->starOff) $this->starOff = $this->star;

		if($allowInput) {
			$attr = "data-onclass='$this->starOnClass' data-offclass='$this->starOffClass'";
			if($this->starOn !== $this->starOff) $attr .= " " .
				"data-on='" . htmlspecialchars($this->starOn, ENT_QUOTES, 'UTF-8') . "' " .
				"data-off='" . htmlspecialchars($this->starOff, ENT_QUOTES, 'UTF-8') . "'";
		} else {
			$attr = '';
		}

		$out = "<span class='$class' $attr>";

		for($n = 1; $n <= $this->numStars; $n++) {
			if($n <= $stars) {
				// full star on
				$star = $this->starOn;
				$attr = "class='$this->starClass $this->starOnClass'";
			} else if(is_float($stars) && $n > $stars && $n < $stars + 1) {
				// partial star on
				$star = "<span class='$this->starClass $this->starOffClass'>$this->starOff</span>";
				if(preg_match('/^\d+[^\d]+(\d+)$/', round($stars, 2), $matches)) {
					if(strlen($matches[1]) == 1) $matches[1] .= '0';
					$star .= "<span class='$this->starClass $this->starOnClass' style='width:$matches[1]%;'>$this->starOn</span>";
				}
				$attr = "class='$this->starClass $this->starPartialClass'";
			} else {
				// star off
				$attr = "class='$this->starClass $this->starOffClass'";
				$star = $this->starOff;
			}
			if($allowInput) {
				// add tooltip indicating number of stars
				$starChars = "$n: " . str_repeat('★ ', $n);
				$attr .= " title='$starChars'";
			}
			$out .= "<span $attr data-value='$n'>$star</span>";
		}

		$out .= "</span>";

		return $out;
	}

	/**
	 * Render a count of ratings
	 *
	 * @param int $count
	 * @param float|int $stars
	 * @param string $schema May be "rdfa" or "microdata" or blank (default) to omit.
	 * @return string
	 *
	 */
	public function renderCount($count, $stars = 0.0, $schema = '') {
		$count = (int) $count;
		if($stars > 0) {
			if(is_int($stars)) {
				$stars = round($stars);
			} else {
				if($stars > $this->numStars) $stars = 5.0;
				$stars = round($stars, 1);
			}
			if($schema == 'rdfa') {
				$stars = "<span property='ratingValue'>$stars</span>";
				$numStars = "<span property='bestRating'>$this->numStars</span>";
				$countStr = "<span property='ratingCount'>$count</span>";

			} else if($schema == 'microdata') {
				$stars = "<span itemprop='ratingValue'>$stars</span>";
				$numStars = "<span itemprop='bestRating'>$this->numStars</span>";
				$countStr = "<span itemprop='ratingCount'>$count</span>";
			} else {
				$numStars = $this->numStars;
				$countStr = (string) $count;
			}
			$details = sprintf($this->detailsLabel, "$stars", "$numStars");
			$label = $count === 1 ? $this->countLabelSingular : $this->countLabelPlural;
			$out = sprintf($label, $details, $countStr);
		} else {
			$out = $this->unratedLabel;
		}
		if($schema == 'rdfa') {
			return "<span class='$this->countClass' property='aggregateRating' typeof='AggregateRating'>$out</span>";
		} else if($schema == 'microdata') {
			return "<span class='$this->countClass' itemprop='aggregateRating' itemscope itemtype='http://schema.org/AggregateRating'>$out</span>";
		} else {
			return "<span class='$this->countClass'>$out</span>";
		}
	}
}
