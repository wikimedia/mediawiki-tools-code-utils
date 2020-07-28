/**
 * wmf-config-wg-vars.js: Find bad wg-variable references in wmf-config.
 *
 * Requires Node 8 or later.
 *
 * Usage:
 *
 * ```
 * # Update copy of mediawiki-config
 * you$ cd /path/to/operations/mediawiki-config
 * you:mediawiki-config$ git pull
 *
 * # Dump matches
 * you$ cd code-utils/
 * you:code-utils$ ack "[$'\"]wg[A-Z]" /path/to/operations/mediawiki-config > wgvars.log
 *
 * # - Enter isolated environment (e.g. using Fresh)
 * # - install deps
 * # - run this script
 * you:code-utils$ fresh-node
 * nobody:code-utils$ npm install request@2 request-promise-native@1
 * nobody:code-utils$ node wmf-config-wg-vars.js
 * ```
 */

const fs = require('fs');
const req = require('request-promise-native');
const querystring = require('querystring');

const lines = fs.readFileSync('./wgvars.log').toString().split('\n');
const varNames = Object.create(null);

// Collect variable names
lines.forEach( line => {
	// Abbreviate the common case of 'wmf-config'
	const fileName = line.split(':', 1)[0].replace(/^wmf-config\//, '');
	if (fileName.slice(-4) !== '.php') {
		// Ignore .html, .xml etc.
		return;
	}
	const rVar = /[$'"]wg([A-Za-z0-9_]+)/g;
	var match;
	while ((match = rVar.exec(line)) !== null) {
		if (!(match[1] in varNames)) {
			varNames[ match[1] ] = new Set()
		}
		varNames[ match[1] ].add(fileName);
	}
} );
// Clear
lines.length = 0;

// Report vars
// console.log(Object.keys(varNames));
console.log('Found ' + Object.keys(varNames).length + ' unique wg* variable names.');

// Search, find, and reduce!
(async function () {
	const headers = {
		'User-Agent': 'wmf-config-wg-vars.js; Contact <https://meta.wikimedia.org/wiki/User:Krinkle>'
	};
	for (const varName in varNames) {
		console.log('Searching... for ' + varName)
		let result = await req({
			headers: headers,
			url: 'https://codesearch.wmflabs.org/search/api/v1/search?'
				+ querystring.stringify({
					repos: '*',
					rng: ':20',
					q: '(\'|"|wg)' + varName,
					files: '',
					i: 'nope'
				})
		});
		result = JSON.parse(result).Results;
		// Ignore self
		delete result['Wikimedia MediaWiki config'];
		result = Object.keys(result);
		if (result.length) {
			delete varNames[varName];
		}
	}

	// Report the vars for which we found no matches in any other repo
	// console.log(varNames);
	const sortedKeys = Object.keys(varNames).sort();
	// Start table
	console.log('{| class="wikitable sortable"');
	console.log('! Param !! Filename !! Component !! Status');
	sortedKeys.forEach( varName => {
		const fileNames = Array.from(varNames[varName]);
		// Add table row
		console.log('|-');
		console.log('|<code>%s</code>', varName);
		console.log('|<code>%s</code>', fileNames.join('</code>\n<code>'));
		console.log('|');
		console.log('|');
	} );
	// End table
	console.log('|}');
}());
