/**
 * Example “Hello” plugin
 *
 */
tinymce.PluginManager.add('hello', (editor, url) => {
	editor.ui.registry.addButton('hello', {
		text: 'Hello',
		icon: 'user',
		onAction: function() {
			alert('Hello!');
		}
	});
	// Adds a menu item, which can then be included in any menu via the menu/menubar configuration 
	editor.ui.registry.addMenuItem('hello', {
		text: 'Hello',
		icon: 'user',
		onAction: function() {
			alert('Hello!');
		}
	});
	// Return metadata for the plugin 
	return {
		getMetadata: () => ({ name: 'Hello' })
	};
});