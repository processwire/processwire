/**
 * PageFrontEndEditLoad 
 * 
 * Load multiple JS files in order, not loading the next until the previous is loaded.
 * 
 * Example of 'items' argument:
 * 
 * var items = [ { 
 * 		test: function() { return true; (true if load needed, false if not) }, // optional
 *		file: 'url to file 1',
 *		after: function() { any additional code to execute after load } // optional, called only if file was specifically loaded
 *	}, {
 *		test: function() { return false },
 *		file: 'url to file 2'
 *	} ];	
 * 
 * 
 */

function PageFrontEditLoad(items) {
	
	var item = null;
	var load = true;
	
	function itemOnLoad() {
		if(item && load && typeof item.after != "undefined") {
			item.after();
		}
		if(!items.length) return;
		item = items.shift();
		load = typeof item.test == "undefined" || item.test();
		if(load) {
			loadItem(item);
		} else {
			itemOnLoad();
		}
	}
	
	function loadItem(item) {
		var script = document.createElement('script');
		script.src = item.file;
		script.onload = itemOnLoad;
		document.body.appendChild(script); 
	}

	itemOnLoad();
}

