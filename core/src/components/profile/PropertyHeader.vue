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
	<h4>
		<div class="propertywrapper">
			<span>
				{{ title }}
			</span>
			<img id="verify-email" title="Verify" data-status="0" src="/core/img/actions/verify.svg" />
		</div>
		<VisibilityLevel :property="property" value="v2-local" />
	</h4>
</template>

<script>
import VisibilityLevel from './VisibilityLevel'

export default {
	name: 'PropertyHeader',
	components: { VisibilityLevel },
	props: {
		title: {
			type: String,
			required: true,
		},
		property: {
			type: String,
			required: true,
		},
	},
}
</script>

<style lang="scss" scoped>
h4 {
	display: flex;
	align-items: center;
	justify-content: space-between;
}

.propertywrapper {
	display: flex;
	align-items: center;
	justify-content: space-between;

	img {
		margin-left: 10px;
	}
}
</style>
