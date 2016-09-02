var InputfieldPageListSelect = {
	init: function($inputfield) {
		if(!$inputfield.hasClass('InputfieldPageListSelectData')) {
			$inputfield = $inputfield.find('.InputfieldPageListSelectData');
		}
		if(!$inputfield.length || $inputfield.hasClass('InputfieldPageListSelectInit')) return;
		$inputfield.ProcessPageList({
			mode: 'select',
			rootPageID: parseInt($inputfield.attr('data-root')),
			selectShowPath: parseInt($inputfield.attr('data-showPath')) > 0 ? true : false, 
			selectAllowUnselect: parseInt($inputfield.attr('data-allowUnselect')) > 0 ? true : false,
			selectShowPageHeader: true, 
			selectStartLabel: $inputfield.attr('data-start'),
			selectSelectLabel: $inputfield.attr('data-select'),
			selectUnselectLabel: $inputfield.attr('data-unselect'),
			moreLabel: $inputfield.attr('data-more'),
			selectCancelLabel: $inputfield.attr('data-cancel'),
			labelName: $inputfield.attr('data-labelName')
		}).hide().addClass('InputfieldPageListSelectInit');
	}
};

$(document).ready(function() { 
	$(".InputfieldPageListSelectData").each(function() {
		InputfieldPageListSelect.init($(this));
	});
	$(document).on('reloaded', '.InputfieldPageListSelect, .InputfieldPage', function() {
		InputfieldPageListSelect.init($(this));
	});
});
