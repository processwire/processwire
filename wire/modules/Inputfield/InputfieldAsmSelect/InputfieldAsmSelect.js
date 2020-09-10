
function initInputfieldAsmSelect($select) {
	var id = $select.attr('id');
	
	// determine options common among all InputfieldAsmSelect instances
	var options = {};
	if(typeof ProcessWire.config == 'undefined') {
		options = { sortable: true };
		
	} else if(typeof ProcessWire.config[id] != "undefined") {
		options = ProcessWire.config[id]; // deprecated/legacy
		
	} else if(typeof ProcessWire.config['InputfieldAsmSelect'] != "undefined") {
		jQuery.extend(options, ProcessWire.config['InputfieldAsmSelect']);
	} 

	// merge options unique to this instance from select.data-asmopt attribute
	var data = $select.attr('data-asmopt'); 
	if(typeof data != "undefined") {
		data = JSON.parse(data); 
		if(data) {
			jQuery.extend(options, data);
			if(typeof ProcessWire.config != "undefined" && typeof ProcessWire.config[id] == "undefined") {
				// for classes like Repeater/Matrix that may be looking for this in ProcessWire.config
				ProcessWire.config[id] = options;
			}
		}
	}
	
	$select.asmSelect(options); 
}

jQuery(document).ready(function($) {
	$(".InputfieldAsmSelect select[multiple]").each(function() {
		initInputfieldAsmSelect($(this));
	}); 
	$(document).on('reloaded', '.InputfieldAsmSelect, .InputfieldPage', function() {
		var $t = $(this);
		if($t.hasClass('InputfieldPage')) $t = $t.find('.InputfieldAsmSelect');
		if(!$t.length) return;
		if($t.find('.asmList').length) return;
		$(this).find("select[multiple]").each(function() {
			initInputfieldAsmSelect($(this));
		});
	});
}); 
