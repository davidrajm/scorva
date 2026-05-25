const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		coordinator: path.resolve( __dirname, 'src/coordinator/index.js' ),
		reviewer: path.resolve( __dirname, 'src/reviewer/index.js' ),
		landing: path.resolve( __dirname, 'src/landing/index.js' ),
	},
};
