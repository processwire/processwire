
var InputfieldPageListSelectMultiple = {
	
	init: function($inputfield) {
		if($inputfield.hasClass('InputfieldPageListSelectMultipleData')) {
			var $t = $inputfield;
		} else {
			var $t = $inputfield.find(".InputfieldPageListSelectMultipleData");
		}
		if(!$t.length) return;
		if($t.hasClass('InputfieldPageListSelectMultipleInit')) return;
		$t.ProcessPageList({
			mode: 'select',
			rootPageID: $t.attr('data-root'),
			selectShowPageHeader: false,
			selectSelectHref: $t.attr('data-href'),
			selectStartLabel: $t.attr('data-start'),
			selectCancelLabel: $t.attr('data-cancel'),
			selectSelectLabel: $t.attr('data-select'),
			selectUnselectLabel: $t.attr('data-unselect'),
			moreLabel: $t.attr('data-more'),
			labelName: $t.attr('data-labelName')
		}).hide().addClass('InputfieldPageListSelectMultipleInit');
		$t.bind('pageSelected', $t, InputfieldPageListSelectMultiple.pageSelected);
		InputfieldPageListSelectMultiple.initList($('#' + $t.attr('id') + '_items'));
	},

	/**
	 * Initialize the given InputfieldPageListSelectMultiple OL by making it sortable
	 *
	 */
	initList: function($ol) {

		var makeSortable = function($ol) { 
			$ol.sortable({
				// items: '.InputfieldPageListSelectMultiple ol > li',
				axis: 'y',
				update: function(e, data) {
					InputfieldPageListSelectMultiple.rebuildInput($(this)); 
				},
				start: function(e, data) {
					data.item.addClass('ui-state-highlight');
				},
				stop: function(e, data) {
					data.item.removeClass('ui-state-highlight');
				}
			}); 
			$ol.addClass('InputfieldPageListSelectMultipleSortable'); 
		};

		$('#' + $ol.attr('id')).on('mouseover', '>li', function() {
			$(this).removeClass('ui-state-default').addClass('ui-state-hover'); 
			if(!$ol.is(".InputfieldPageListSelectMultipleSortable")) makeSortable($ol); 
		}).on('mouseout', '>li', function() {
			$(this).removeClass('ui-state-hover').addClass('ui-state-default'); 
		});

		$ol.on('click', 'a.itemRemove', function() {
			// $(".InputfieldPageListSelectMultiple ol li a.itemRemove").live('click', function() {
			var $li = $(this).parent();
			var $ol = $li.parent();
			var id = $li.children(".itemValue").text();
			$li.remove();
			InputfieldPageListSelectMultiple.rebuildInput($ol);
			return false;
		});

	},
	

	/**
	 * Callback function executed when a page is selected from PageList
	 *
	 */
	pageSelected: function(e, page) {

		$input = e.data;

		var $ol = $('#' + $input.attr('id') + '_items');
		var $li = $ol.children(".itemTemplate").clone();

		$li.removeClass("itemTemplate"); 
		$li.children('.itemValue').text(page.id); 
		$li.children('.itemLabel').text(page.title); 

		$ol.append($li);

		InputfieldPageListSelectMultiple.rebuildInput($ol); 

	},

	/**
	 * Rebuild the CSV values present in the hidden input[text] field
	 *
	 */
	rebuildInput: function($ol) {
		var id = $ol.attr('id');
		id = id.substring(0, id.lastIndexOf('_')); 
		var $input = $('#' + id);
		var value = '';
		var selected = {};
		$ol.children(':not(.itemTemplate)').each(function() {
			var $li = $(this);
			var v = $li.children('.itemValue').text();
			if(typeof selected[v] != "undefined") {
				// item already in list
				if(jQuery.ui) selected[v].effect('highlight', 1000);
				$li.remove();
			} else {
				// add new item to list
				selected[v] = $li;
				if(value.length > 0) value += ',';
				value += v;
			}
		}); 
		$input.val(value);
		$input.change();
	}


}; 

$(document).ready(function() {
	$(".InputfieldPageListSelectMultiple").each(function() {
		InputfieldPageListSelectMultiple.init($(this));
	});
	$(document).on('reloaded', '.InputfieldPageListSelectMultiple, .InputfieldPage', function() {
		InputfieldPageListSelectMultiple.init($(this));
	});

}); 


