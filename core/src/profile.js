import { getRequestToken } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import Vue from 'vue'

import Profile from './views/Profile'

// eslint-disable-next-line camelcase
__webpack_nonce__ = btoa(getRequestToken())
// eslint-disable-next-line camelcase
__webpack_public_path__ = generateFilePath('core', '', 'js/')

Vue.mixin({
	methods: {
		t,
	},
})

const View = Vue.extend(Profile)
new View().$mount('#user-profile')
new View().$mount('#personal-settings')

console.debug('PROFILE')
