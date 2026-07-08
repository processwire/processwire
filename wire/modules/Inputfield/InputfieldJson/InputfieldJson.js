var InputfieldJson = {
	
	loading: false,
	callbacks: [], 
	
	load: function(jsUrl, cssUrl, callback) {
		if(typeof window.JSONEditor !== 'undefined') {
			callback();
			return;
		}
		
		this.callbacks.push(callback);
		if(this.loading) return;
		this.loading = true;
		
		var link = document.createElement('link');
		link.rel = 'stylesheet';
		link.href = cssUrl;
		document.head.appendChild(link);
		
		var script = document.createElement('script');
		script.src = jsUrl;
		script.onload = function() {
			InputfieldJson.loading = false;
			while(InputfieldJson.callbacks.length) {
				var callback = InputfieldJson.callbacks.shift();
				callback();
			}
		};
		script.onerror = function() {
			InputfieldJson.loading = false;
			InputfieldJson.callbacks = [];
		};
		document.head.appendChild(script);
	},
	
	init: function(inputId, jsUrl, cssUrl, options) {
		this.load(jsUrl, cssUrl, function() {
			var input = document.getElementById(inputId);
			var container = document.getElementById('jsonEdit_' + inputId);
			if(!container || !input || container.dataset.jsoneditorReady) return;
			
			if(options.mode === 'tree' || options.mode === 'form' || options.mode === 'view') {
				options.onChangeJSON = function(json) {
					input.value = JSON.stringify(json);
					input.dispatchEvent(new Event('change', {bubbles: true}));
				};
			} else if(options.mode === 'code' || options.mode === 'text') {
				options.onChangeText = function(text) {
					input.value = text;
					input.dispatchEvent(new Event('change', {bubbles: true}));
				}
			}
			
			container.dataset.jsoneditorReady = '1';
			var editor = new JSONEditor(container, options);
			editor.set(JSON.parse(input.value || '{}'));
		});
	}
	
}

