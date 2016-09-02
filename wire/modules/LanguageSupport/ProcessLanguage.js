jQuery(document).ready(function($) {
	
	$('.language-phrase-search').each(function() {
		var $input = $(this);
		$input.autocomplete({
			source: function(request, response) {
				var data = [];
				var n = 0;
				var offset = 0;
				do {
					offset = phraseIndex.toLowerCase().indexOf(request.term.toLowerCase(), offset);
					if(offset < 0) break;
					var first = phraseIndex.lastIndexOf('|', offset);
					var last = phraseIndex.indexOf('|', offset);
					var phrase = phraseIndex.substring(first + 1, last);
					first = phraseIndex.indexOf('^', offset);
					last = phraseIndex.indexOf('|', first + 1);
					var col = phraseIndex.indexOf(':', first);
					var textdomain = phraseIndex.substring(first + 1, col);
					var file = phraseIndex.substring(col + 1, last - 1);
					var basename = file.substring(file.lastIndexOf('/') + 1);
					if(phrase.length > 100) phrase = phrase.substring(0, 100) + '…';
					offset = first;
					if(phrase.indexOf('^') > -1) continue;
					phrase = basename + ': ' + phrase;
					data[n++] = {label: phrase, value: textdomain + ':' + file};
					if(n > 50) break;
				} while(1);
				if(n > 50) n = "50+";
				$input.next(".language-phrase-search-cnt").text(n);
				response(data);
			},
			minLength: 3,
			select: function(event, ui) {
				if(ui.item) {
					var x = ui.item.value.split(':');
					var textdomain = x[0];
					var file = x[1];
					var url = '../../language-translator/edit/?language_id=' + phraseLanguageID +
						'&textdomain=' + textdomain + '&filename=' + file;
					window.location.href = url;
				}
				/*
				 console.log( ui.item ?
				 'Selected: ' + ui.item.label :
				 'Nothing selected, input was ' + this.value);
				 */
			}
		}).blur(function() {
			$input.next(".language-phrase-search-cnt").text('');
		}).keydown(function(e) {
			if(e.keyCode == 13) return false;
		});
	});
});