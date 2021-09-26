const path = require('path');

module.exports = {
	entry: {
		'sidebar': path.join(__dirname, 'src', 'sidebar.js'),
	},
	output: {
		path: path.resolve(__dirname, './js/dist/'),
		publicPath: '/js/',
		filename: '[name].js',
		chunkFilename: 'files.[id].js'
	}
}
