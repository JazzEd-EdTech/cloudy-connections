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
	<AppSettingsDialog :open="open" @update:open="$emit('update:open', !!$event)" :show-navigation="true">
		<AppSettingsSection title="Personal info">
			<div class="personal-info">
				<div class="personal-info__inputs">
					<DisplayName />
					<DisplayName />
					<DisplayName />
					<DisplayName />
				</div>
				<Avatar class="personal-info__avatar" />
			</div>
		</AppSettingsSection>
		<AppSettingsSection title="Contact info">
			<div class="personal-info__inputs">
				<Email />
				<DisplayName />
				<DisplayName />
				<DisplayName />
			</div>
		</AppSettingsSection>
	</AppSettingsDialog>
</template>

<script>
import AppSettingsDialog from '@nextcloud/vue/dist/Components/AppSettingsDialog'
import AppSettingsSection from '@nextcloud/vue/dist/Components/AppSettingsSection'

import Avatar from '../components/profile/Avatar'
import DisplayName from '../components/profile/DisplayName'
import Email from '../components/profile/Email'
export default {
	name: 'ProfileSettings',
	components: {
		AppSettingsDialog,
		AppSettingsSection,

		Avatar,
		DisplayName,
		Email,
	},
	props: {
		open: {
			type: Boolean,
			default: false,
		},
	},
}
</script>

<style lang="scss" scoped>
::v-deep .app-settings__content {
	width: 90vw;
	max-width: 900px;
}

.personal-info {
	display: flex;
	flex-wrap: wrap;
	&__inputs,
	&__avatar {
		flex-basis: 200px;
		min-width: 200px;
		flex-grow: 1;
		margin-right: 20px;
	}
}

</style>
