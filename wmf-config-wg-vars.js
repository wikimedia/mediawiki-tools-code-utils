const fs = require('fs');
const req = require('request-promise-native');
const querystring = require('querystring');

const lines = fs.readFileSync('./wmf-config-wg-vars.log').toString().split('\n');
const varNames = Object.create(null);

// Collect variable names
lines.forEach( line => {
	const fileName = line.split(':', 1)[0];
	if (fileName.slice(-4) !== '.php') {
		// Ignore .html, .xml etc.
		return;
	}
	const rVar = /[$'"]wg([A-Za-z0-9_]+)/g;
	var match;
	while ( (match = rVar.exec(line)) !== null ) {
		if ( !( match[1] in varNames ) ) {
			varNames[ match[1] ] = new Set()
		}
		varNames[ match[1] ].add( fileName );
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

	// Dump vars without matches in source repos
	console.log(varNames);

}());
