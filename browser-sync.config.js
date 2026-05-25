/** @type {import('browser-sync').Options} */
module.exports = {
	proxy: 'sastt.local',
	files: [
		'**/*.php',
		'build/**/*.{js,css}',
		'assets/css/**/*.css',
	],
	open: false,
	notify: false,
};
