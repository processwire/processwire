<?php namespace ProcessWire;

/**
 * Page Table Field (for FieldtypePageTable)
 *
 * Configured with FieldtypePageTable
 * ==================================
 * @property int|bool $autoTrash Deprecated, replaced by trashOnDelete
 * @property int $trashOnDelete
 * @property int $unpubOnTrash
 * @property int $unpubOnUnpub
 * @property int|array $template_id
 * @property int $parent_id
 * @property string $sortfields
 *
 * Configured with InputfieldPageTable
 * ===================================
 * @property string $columns
 * @property string $nameFormat
 * @property int $noclose
 *
 * @since 3.0.221
 *
 */
class PageTableField extends Field {
}
