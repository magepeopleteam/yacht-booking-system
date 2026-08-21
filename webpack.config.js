const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: path.resolve( __dirname, 'assets/admin/src/index.js' ),
		frontend: path.resolve( __dirname, 'assets/frontend/src/index.js' ),
		'booking-block': path.resolve( __dirname, 'assets/frontend/src/block/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/build' ),
	},
};
