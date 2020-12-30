function InputfieldTextLength($inputs) {
	
	var $ = jQuery;
	
	if(typeof ProcessWire != "undefined") {
		var cfg = ProcessWire.config.InputfieldTextLength;
	} else {
		var cfg = config.InputfieldTextLength;
	}
	
	function setNote($input, html) {
		var $note = $input.siblings('.InputfieldTextLengthNote');
		if($note.length) {
			if(!html.length) {
				$note.remove();
				$(window).resize();
			}
		} else if(html.length) {
			$note = $("<div class='InputfieldTextLengthNote'></div>");
			$note.css('margin', '2px 0 0 0');
			if($input.is('textarea') && $input.closest('.InputfieldCKEditor').length) {
				$input.parent().append($note);
			} else {
				$input.after($note);
			}
			$(window).resize();
		} 
		if(html.length) {
			$note.html('<small class="detail">' + html + '</small>');
		}
	}
	
	function getLength($input, countWords) {
		var val; 
		if($input.closest('.InputfieldCKEditor').length) {
			var id = $input.attr('id');
			var editor = CKEDITOR.instances[id];
			if(typeof editor == "undefined") return 0;
			val = editor.getData();
			val = val.replace(/<[^>]+>/g, (countWords ? ' ' : ''));
			val = val.replace(/&[#a-z0-9]+;/gi, ' '); // HTML entity only counts as 1 char
			//val = editor.document.getBody().getText(); // getData();
		} else {
			val = $.trim($input.val());
		}
		if(countWords) {
			if(!val.length) return 0;
			words = val;
			var words = val.replace(/[\r\n\t,:.!?\/]+/g, ' ');
			if(words.indexOf('&nbsp;') > -1) words = words.replace(/&nbsp;/gi, ' ');
			words = $.trim(words.replace(/\s\s+/g, ' '));
			console.log(words);
			return words.split(' ').length;
		} else {
			return val.length;
		}
	}
	
	function updateCounter($input) {
		
		var showCount = $input.attr('data-showCount');
		var len = 0;
		var note = '';
		var hasError = false;

		if(showCount == "1") {
			// character counter
			var minlength = $input.attr('data-minlength');
			var maxlength = $input.attr('maxlength');

			len = getLength($input, false);
			//note = len > 0 ? cfg.chars.replace('%d', len) + ' ' : '';
			note = cfg.chars.replace('%d', len) + ' ';

			if(len == 1) note = cfg.char1 + ' '; // singular

			if(typeof maxlength == 'undefined' || !maxlength) maxlength = $input.attr('data-maxlength');

			minlength = typeof minlength == 'undefined' || !minlength ? 0 : parseInt(minlength);
			maxlength = typeof maxlength == 'undefined' || !maxlength ? 0 : parseInt(maxlength);

			if(minlength) {
				if(len > 0 && len < minlength) {
					note += "<span style='color:red'>" + cfg.min.replace('%d', minlength) + "</span>";
					hasError = true;
				}
			}
			if(maxlength) {
				if(len > 0 && len > maxlength) {
					note += "<span style='color:red'>" + cfg.max.replace('%d', maxlength) + "</span>";
					hasError = true;
				} else if(len >= 0 && !hasError) {
					note += cfg.max.replace('%d', maxlength);
				}
			}
		} else if(showCount == "2") {
			// word counter
			len = getLength($input, true);
			if(len == 1) {
				note = cfg.word1 + ' ';
			} else if(len > 1) {
				note = cfg.words.replace('%d', len) + ' ';
			}
		}

		setNote($input, note);
	}

	if($inputs.length) {
		$inputs.on('keyup focus pw-focus change', function(e) {
			updateCounter($(this));
		});

		$inputs.each(function() {
			updateCounter($(this));
		});
	}
}

jQuery(document).ready(function($) {
	InputfieldTextLength($('.InputfieldTextLength'));
	$(document).on('reloaded', '.Inputfield', function() {
		InputfieldTextLength($(this).find('.InputfieldTextLength'));
	});
});