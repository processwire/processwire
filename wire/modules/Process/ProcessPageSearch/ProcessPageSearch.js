ProcessPageSearch = {

	t: 0,
	defaultLabel: 'Search',
	lastQuery: '',

	search: function() {
		var query = $('#ProcessPageSearchQuery').val();
		if(query == this.lastQuery) return false;
		$('#ProcessPageSearchStatus').text('Searching');
		$('#ProcessPageSearchLiveResults').load(ProcessWire.config.urls.admin + 'page/search/', { q: query }, function(data) {
			var numResults = parseInt($('#search_num_results').hide().text());
			if(numResults) {
					$('#search_results').fadeIn("fast").find("a").on('click', function() {
							// reduces time that horiz scrollbar shows in FF
							$("#search_results").css("overflow", "hidden");
					});
					$('#search_status').text(numResults + ' matches');
			} else {
					$('#search_status').text('No matches');
			}
		});
		this.lastQuery = query;
		return false;
	},

	hide: function() {
		$('#search_results').fadeOut("fast", function() { $(this).remove(); });
		$('#search_status').text('');
		$('#search_query').val(this.defaultLabel);
	},

	init: function() {
		this.lastQuery = this.defaultLabel;
		$('#container').append('<div id="search_container"></div><div id="search_status"></div>');

		$('#search_form').off().on('submit', function() {
			return this.search();
		});

		$('#search_query').attr('autocomplete', 'off').on('focus', function() {
			$(this).on('keyup', function() {
				if($(this).val().length < 4) return;
				if(this.t) clearTimeout(this.t);
				this.t = setTimeout("liveSearch.search()", 500);
			});
		}).on('blur', function() {
			setTimeout("liveSearch.hide()", 250);
		});
	}

}

$(document).ready(function() {
	/*
	var $searchQuery = $("#ProcessPageSearchQuery"); 
	var label = $('#ProcessPageSearchSubmit').val();

	$searchQuery.on('focus', function() {
		$(this).prev('label').hide();
	}).on('blur', function() {
		$(this).prev('label').show();
	}); 
	*/
});
