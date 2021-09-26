/**
 * @copyright Copyright (c) 2016 Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

const path = require('path')

module.exports = {
	entry: {
		dashboard: path.join(__dirname, 'src', 'dashboard'),
		'user-status-menu': path.join(__dirname, 'src', 'main-user-status-menu'),
	},
	output: {
		path: path.resolve(__dirname, './js'),
		publicPath: '/js/',
		filename: '[name].js?v=[chunkhash]',
		jsonpFunction: 'webpackJsonpUserStatus',
	},
	optimization: {
		splitChunks: {
			automaticNameDelimiter: '-',
		},
	},
}
