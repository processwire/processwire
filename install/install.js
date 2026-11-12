/**
 * ProcessWire installer scripts
 *
 * Used by the profile and database steps of install.php. Kept in this file rather than
 * inline, so that the installer works with a Content-Security-Policy that does not allow
 * inline scripts. Requires no JS framework.
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 */

/**
 * Show the preview for the selected site profile (profile step)
 *
 */
function pwiInitProfileSelect() {
	var select = document.getElementById('select-profile');
	if(!select) return;
	function updatePreview() {
		var previews = document.querySelectorAll('.profile-preview');
		for(var i = 0; i < previews.length; i++) previews[i].style.display = 'none';
		var active = select.value ? document.getElementById(select.value) : null;
		if(active) active.style.display = '';
	}
	select.addEventListener('change', updatePreview);
	updatePreview();
}

/**
 * Show/hide database fields and set their required states (database step)
 *
 */
function pwiInitDatabaseFields() {
	function el(selector) { return document.querySelector(selector); }
	function row(input) { return input ? input.closest('p') : null; }
	function display(node, show) { if(node) node.style.display = show ? '' : 'none'; }
	var ho = el('input[name=dbHost]'), po = el('input[name=dbPort]'),
		so = el('input[name=dbSocket]'), co = el('select[name=dbCon]'),
		no = el('input[name=dbName]'), uo = el('input[name=dbUser]'),
		to = el('select[name=dbType]'),
		ao = el('input[name=installerActivationToken]'),
		activation = el('#installer-activation');
	if(!co) return;
	var testActivation = activation ? activation.classList.contains('pwi-test-activation') : false;
	var defaultPorts = { mysql: '3306', pgsql: '5432' };
	function updatePort() {
		// switch the port to the new database type's default, unless it was customized
		var type = to ? to.value : 'mysql';
		if(!po || !defaultPorts[type]) return;
		var port = po.value.trim(), isDefault = port === '';
		for(var key in defaultPorts) if(defaultPorts[key] === port) isDefault = true;
		if(isDefault) po.value = defaultPorts[type];
	}
	function updateConnectionFields() {
		var sqlite = to ? to.value === 'sqlite' : false,
			pgsql = to ? to.value === 'pgsql' : false,
			useHost = co.value === 'Hostname';
		display(el('#pwi-mysql'), !sqlite);
		display(el('#pwi-sqlite'), sqlite);
		var dbName = el('#pwi-db-name');
		if(dbName) dbName.textContent = pgsql ? 'PostgreSQL' : 'MySQL';
		display(el('#pwi-mysql-intro'), !pgsql);
		display(el('#pwi-pgsql-intro'), pgsql);
		// charset and engine are MySQL settings (PostgreSQL databases are UTF-8 and have no engines)
		display(row(el('select[name=dbCharset]')), !pgsql);
		display(row(el('select[name=dbEngine]')), !pgsql);
		display(el('#pwi-engine-note'), !pgsql);
		// PostgreSQL wants the directory that holds its socket rather than the socket file
		if(so) so.placeholder = pgsql ? 'Socket directory, i.e. /tmp' : '';
		if(no) no.required = !sqlite;
		if(uo) uo.required = !sqlite;
		if(ho) { ho.required = !sqlite && useHost; display(row(ho), useHost); }
		if(po) { po.required = !sqlite && useHost; display(row(po), useHost); }
		if(so) { so.required = !sqlite && !useHost; display(row(so), !useHost); }
		var host = (ho ? ho.value : '').trim().toLowerCase(),
			localHosts = ['localhost', '127.0.0.1', '::1', '[::1]'],
			activationRequired = !sqlite && (testActivation ||
				(useHost && localHosts.indexOf(host) === -1));
		if(ao) ao.required = activationRequired;
		display(activation, activationRequired);
	}
	co.addEventListener('change', updateConnectionFields);
	if(to) to.addEventListener('change', function() { updatePort(); updateConnectionFields(); });
	if(ho) ho.addEventListener('input', updateConnectionFields);
	updateConnectionFields();
}

(function() {
	function init() {
		pwiInitProfileSelect();
		pwiInitDatabaseFields();
	}
	if(document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
