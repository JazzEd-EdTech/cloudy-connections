/*
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

import _ from 'underscore'
import $ from 'jquery'
import Toastify from 'toastify-js'

/**
 * @todo Write documentation
 * @deprecated 17.0.0 use OCP.Toast
 * @namespace OC.Notification
 */
export default {

	updatableNotification: null,

	getDefaultNotificationFunction: null,

	/**
	 * @param callback
	 * @deprecated 17.0.0 use OCP.Toast
	 */
	setDefault: function (callback) {
		this.getDefaultNotificationFunction = callback;
	},

	/**
	 * Hides a notification.
	 *
	 * If a row is given, only hide that one.
	 * If no row is given, hide all notifications.
	 *
	 * @param {jQuery} [$row] notification row
	 * @param {Function} [callback] callback
	 * @deprecated 17.0.0 use OCP.Toast
	 */
	hide: function ($row, callback) {
		var self = this;
		var $notification = $('#content');

		if (_.isFunction($row)) {
			// first arg is the callback
			callback = $row;
			$row = undefined;
		}

		if (!$row) {
			console.error('Missing argument $row in OC.Notification.hide() call, caller needs to be adjusted to only dismiss its own notification');
			return;
		}

		// remove the row directly
		$row.each(function () {
			$(this)[0].toastify.hideToast()
			if (this === this.updatableNotification) {
				this.updatableNotification = null
			}
		})
		if (callback) {
			callback.call()
		}
		if (this.getDefaultNotificationFunction) {
			this.getDefaultNotificationFunction()
		}
	},

	/**
	 * Shows a notification as HTML without being sanitized before.
	 * If you pass unsanitized user input this may lead to a XSS vulnerability.
	 * Consider using show() instead of showHTML()
	 *
	 * @param {string} html Message to display
	 * @param {Object} [options] options
	 * @param {string} [options.type] notification type
	 * @param {int} [options.timeout=0] timeout value, defaults to 0 (permanent)
	 * @return {jQuery} jQuery element for notification row
	 * @deprecated 17.0.0 use OCP.Toast
	 */
	showHtml: function (html, options) {
		options = options || {}
		options.showHtml = true
		options.timeout = (options.timeout === 0) ? -1 : options.timeout
		const toast = window.OCP.Toast.message(html, options)
		return $(toast.toastElement)
	},

	/**
	 * Shows a sanitized notification
	 *
	 * @param {string} text Message to display
	 * @param {Object} [options] options
	 * @param {string} [options.type] notification type
	 * @param {int} [options.timeout=0] timeout value, defaults to 0 (permanent)
	 * @return {jQuery} jQuery element for notification row
	 * @deprecated 17.0.0 use OCP.Toast
	 */
	show: function (text, options) {
		const toast = window.OCP.Toast.message(text, options);
		return $(toast.toastElement);
	},

	/**
	 * Updates (replaces) a sanitized notification.
	 *
	 * @param {string} text Message to display
	 * @return {jQuery} JQuery element for notificaiton row
	 * @deprecated 17.0.0 use OCP.Toast
	 */
	showUpdate: function (text) {
		if (this.updatableNotification) {
			this.updatableNotification.hideToast();
		}
		this.updatableNotification = OCP.Toast.message(text, {timeout: -1})
		return $(this.updatableNotification.toastElement);
	},

	/**
	 * Shows a notification that disappears after x seconds, default is
	 * 7 seconds
	 *
	 * @param {string} text Message to show
	 * @param {array} [options] options array
	 * @param {int} [options.timeout=7] timeout in seconds, if this is 0 it will show the message permanently
	 * @param {boolean} [options.isHTML=false] an indicator for HTML notifications (true) or text (false)
	 * @param {string} [options.type] notification type
	 * @deprecated 17.0.0 use OCP.Toast
	 */
	showTemporary: function (text, options) {
		options = options || {}
		options.timeout = options.timeout || 7;
		const toast = window.OCP.Toast.message(text, options);
		return $(toast.toastElement);
	},

	/**
	 * Returns whether a notification is hidden.
	 * @return {boolean}
	 * @deprecated 17.0.0 use OCP.Toast
	 */
	isHidden: function () {
		return !$('#content').find('.toastify').length;
	}
}
