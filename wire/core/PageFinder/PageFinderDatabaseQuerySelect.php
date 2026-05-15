<?php namespace ProcessWire;

/**
 * Typehinting class for DatabaseQuerySelect object passed to Fieldtype::getMatchQuery()
 *
 * @property Field $field Original field
 * @property string $group Original group of the field
 * @property Selector $selector Original Selector object
 * @property Selectors $selectors Original Selectors object
 * @property DatabaseQuerySelect $parentQuery Parent database query
 * @property PageFinder $pageFinder PageFinder instance that initiated the query
 * @property string $joinType Value 'join', 'leftjoin', or '' (if not yet known), can be overridden (3.0.237+)
 * @property bool $_aggregateScoreFields True when fulltext score SELECT expressions should be aggregated for grouped page results (3.0.262+)
 */
abstract class PageFinderDatabaseQuerySelect extends DatabaseQuerySelect { }
