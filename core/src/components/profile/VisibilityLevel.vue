<!--
  - @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<Actions :default-icon="scopeDetails[value].icon">
		<ActionButton v-for="(scope, id) in scopeDetails" :icon="scope.icon" :title="scope.title" :class="{ 'selected': id === value }" :key="id">
			{{ scope.description }}
		</ActionButton>
	</Actions>
</template>

<script>
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
const SCOPE_PRIVATE = 'v2-private'
const SCOPE_LOCAL = 'v2-local'
const SCOPE_FEDERATED = 'v2-federated'
const SCOPE_PUBLISHED = 'v2-published'

const VISIBILITY_PRIVATE = 'private'
const VISIBILITY_CONTACTS_ONLY = 'contacts'
const VISIBILITY_PUBLIC = 'public'

const mapToScopeV2 = (scope) => {
	switch (scope) {
	case VISIBILITY_PRIVATE:
		return SCOPE_LOCAL
	case VISIBILITY_CONTACTS_ONLY:
		return SCOPE_FEDERATED
	case VISIBILITY_PUBLIC:
		return SCOPE_PUBLISHED
	}
}

const PROPERTY_AVATAR = 'avatar'
const PROPERTY_DISPLAYNAME = 'displayname'
const PROPERTY_PHONE = 'phone'
const PROPERTY_EMAIL = 'email'
const PROPERTY_WEBSITE = 'website'
const PROPERTY_ADDRESS = 'address'
const PROPERTY_TWITTER = 'twitter'

const NOT_VERIFIED = '0'
const VERIFICATION_IN_PROGRESS = '1'
const VERIFIED = '2'

const scopeDetails = {
	[SCOPE_PRIVATE]: {
		title: t('core', 'Private'),
		description: t('core', 'Only visible to people matched via phone number integration through Talk on mobile'),
		icon: 'icon-phone',
	},
	[SCOPE_LOCAL]: {
		title: t('core', 'Local'),
		description: t('core', 'Only visible to people on this instance and guests'),
		icon: 'icon-password',
	},
	[SCOPE_FEDERATED]: {
		title: t('core', 'Federated'),
		description: t('core', 'Only synchronize to trusted servers'),
		icon: 'icon-contacts-dark',
	},
	[SCOPE_PUBLISHED]: {
		title: t('core', 'Published'),
		description: t('core', 'Synchronize to trusted servers and the global and public address book'),
		icon: 'icon-link',
	},
}

export default {
	name: 'VisibilityLevel',
	components: { Actions, ActionButton },
	props: {
		property: {
			type: String,
			required: true,
		},
		value: {
			type: String,
			default: SCOPE_FEDERATED,
		},
	},
	data() {
		return {
			scopeDetails,
		}
	},
}
</script>

<style scoped>

</style>
