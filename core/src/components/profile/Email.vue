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
	<div>
		<form id="emailform">
			<PropertyHeader :title="t('core', 'E-mail')" property="email" />
			<input
				type="email"
				class="icon-checkmark"
				name="email"
				id="email"
				value="admin@example.net"
				placeholder="Your email address"
				autocomplete="on"
				autocapitalize="none"
				autocorrect="off" />
			<p class="hint">
				<em>{{ t('core', 'For password reset and notifications') }}</em>
			</p>
			<input type="hidden" id="emailscope" value="v2-federated">
		</form>
		<input type="email" placeholder="user@example.com" />
		<input type="email" placeholder="user@example.com" />
		<input type="email" placeholder="user@example.com" />
		<button class="icon-add">
			{{ t('core', 'Add additional email address') }}
		</button>
	</div>
</template>

<script>
import PropertyHeader from './PropertyHeader'

export default {
	name: 'Email',
	components: { PropertyHeader },
}
</script>

<style lang="scss" scoped>
input[type="email"] {
	width: 100%;

	.icon-checkmark {
		background-position: calc(100% - 13px);
	}
}
</style>
