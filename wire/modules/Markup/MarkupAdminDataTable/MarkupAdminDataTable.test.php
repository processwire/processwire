<?php namespace ProcessWire;

/**
 * Tests for ProcessWire MarkupAdminDataTable.
 *
 */
class WireTest_MarkupAdminDataTable extends WireTest {

	public function execute() {
		$this->testFreshInstancesAndDefaults();
		$this->testHeaderFooterRowsAndEncoding();
		$this->testRowFormsAndAttributes();
		$this->testResponsiveClassOptions();
		$this->testActionsWithoutRows();
	}

	protected function table() {
		return $this->wire()->modules->get('MarkupAdminDataTable');
	}

	protected function testFreshInstancesAndDefaults() {
		$table1 = $this->table();
		$table2 = $this->table();

		$this->check('module returns fresh instances', false, $table1 === $table2);
		$this->check('new instance starts with no rows', array(), $table1->rows);
		$this->check('encodeEntities default is true', true, $table1->encodeEntities);
		$this->check('sortable default is true', true, $table1->sortable);
		$this->check('resizable default is false', false, $table1->resizable);
		$this->check('responsive default is responsiveYes', MarkupAdminDataTable::responsiveYes, $table1->responsive);
		$this->check('render() with no rows/actions returns empty string', '', $table1->render());
	}

	protected function testHeaderFooterRowsAndEncoding() {
		$table = $this->table();
		$table->setID('wire-test-table');
		$table->setCaption('People <Admin>');
		$table->setClass('wire-extra');
		$table->addClass('wire-more');
		$table->headerRow(array(
			'Name <raw>',
			array('Status', 'status-col'),
			'Email',
		));
		$table->footerRow(array('Total <all>', '', '$5,200'));
		$table->setColNotSortable(1);
		$table->row(array('Ryan <Admin>', 'Active', 'ryan@example.com'));

		$out = $table->render();

		$this->check('render() includes explicit id', true, strpos($out, "id='wire-test-table'") !== false);
		$this->check('render() includes default table class', true, strpos($out, 'AdminDataTable') !== false);
		$this->check('render() includes custom classes', true, strpos($out, 'wire-extra wire-more') !== false);
		$this->check('caption is encoded', true, strpos($out, '<caption>People &lt;Admin&gt;</caption>') !== false);
		$this->check('header cell content is encoded', true, strpos($out, '<th>Name &lt;raw&gt;</th>') !== false);
		$this->check('header array class is rendered', true, strpos($out, "class='status-col sorter-false'") !== false);
		$this->check('footer cell content is encoded', true, strpos($out, '<td>Total &lt;all&gt;</td>') !== false);
		$this->check('blank footer cell renders empty td', true, strpos($out, '<td></td>') !== false);
		$this->check('body cell content is encoded', true, strpos($out, '<td>Ryan &lt;Admin&gt;</td>') !== false);
	}

	protected function testRowFormsAndAttributes() {
		$table = $this->table();
		$table->setID('wire-test-row-forms');
		$table->setSortable(false);
		$table->setResponsive(false);
		$table->headerRow(array('Name', 'Status', 'Amount'));
		$table->row(array(
			array('Ryan & Co' => '/admin/?id=1&view=edit'),
			array('Active <ok>', 'status-good'),
			'$1,200',
		), array(
			'separator' => true,
			'class' => 'highlight',
			'attrs' => array('data-id' => '42&7'),
		));
		$table->row(array('Summary', true, '$3,000'));

		$out = $table->render();

		$this->check('sortable class can be disabled', false, strpos($out, 'AdminDataTableSortable') !== false);
		$this->check('responsive class can be disabled', false, strpos($out, 'AdminDataTableResponsive') !== false);
		$this->check('row attrs are rendered and encoded', true, strpos($out, "data-id='42&amp;7'") !== false);
		$this->check('separator adds separator class', true, strpos($out, 'AdminDataListSeparator') !== false);
		$this->check('row custom class is rendered', true, strpos($out, "class='highlight AdminDataListSeparator'") !== false);
		$this->check('associative cell renders encoded link', true, strpos($out, "<a href='/admin/?id=1&amp;view=edit'>Ryan &amp; Co</a>") !== false);
		$this->check('array cell renders class and encoded value', true, strpos($out, "<td class='status-good'>Active &lt;ok&gt;</td>") !== false);
		$this->check('boolean true expands previous column colspan', true, strpos($out, "<td colspan='2'>Summary</td>") !== false);

		$table->removeClass('AdminDataList AdminDataTable');
		$out = $table->render();
		$this->check('removeClass() removes multiple table classes at render time', true, strpos($out, "<table id='wire-test-row-forms' class=''") !== false);
	}

	protected function testResponsiveClassOptions() {
		$table = $this->table();
		$table->setID('wire-test-responsive-alt');
		$table->setResponsive(MarkupAdminDataTable::responsiveAlt);
		$table->setResizable(true);
		$table->headerRow(array('Name'));
		$table->row(array('Ryan'));

		$out = $table->render();

		$this->check('responsiveAlt includes responsive class', true, strpos($out, 'AdminDataTableResponsive') !== false);
		$this->check('responsiveAlt includes alt class', true, strpos($out, 'AdminDataTableResponsiveAlt') !== false);
		$this->check('resizable includes resizable class', true, strpos($out, 'AdminDataTableResizable') !== false);
		$this->check('responsive output includes init script', true, strpos($out, "AdminDataTable.initTable($('#wire-test-responsive-alt'))") !== false);
	}

	protected function testActionsWithoutRows() {
		$table = $this->table();
		$table->action(array('Continue' => '../next/'));
		$table->action(array('Export CSV' => './export/', 'Import' => './import/'));

		$out = $table->render();

		$this->check('actions render even without rows', true, strpos($out, '<p>') !== false);
		$this->check('first action label renders', true, strpos($out, 'Continue') !== false);
		$this->check('first action href renders', true, strpos($out, '../next/') !== false);
		$this->check('second action label renders', true, strpos($out, 'Export CSV') !== false);
		$this->check('third action label renders', true, strpos($out, 'Import') !== false);
		$this->check('actions property contains all actions', 3, count($table->actions));
	}
}
