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
	<div class="profile">
		<div class="profile__header">
			<h2>
				{{ account.displayname.value }}
				<button v-if="isCurrentUser" class="primary" @click="openSettings">
					{{ t('core', 'Edit profile') }}
				</button>
			</h2>
			<p>
				Software engineer - Würzburg, Germany
			</p>
			<p class="user-status" v-if="status">
				{{ status.icon }} {{ status.message }}
			</p>
		</div>
		<div class="profile__content">
			<div class="profile__sidebar">
				<Avatar :user="userId" :size="180" :show-user-status-compact="false" :disable-menu="true" />

				<div class="user-actions">
					<Actions :primary="true" default-icon="icon-talk" menu-title="Talk to me" class="user-actions__primary">
						<ActionButton icon="icon-talk" title="foooo">Talk to {{ account.displayname.value }}</ActionButton>
					</Actions>
					<div class="user-actions__other">
						<Actions>
							<ActionButton icon="icon-mail">Send a mail</ActionButton>
						</Actions>
						<Actions>
							<ActionButton icon="icon-mail">Call via phone</ActionButton>
						</Actions>
						<Actions>
							<ActionButton icon="icon-mail">Open website</ActionButton>
							<ActionButton icon="icon-twitter">Open twitter profile</ActionButton>
						</Actions>
					</div>
				</div>
			</div>
			<div v-if="false" class="profile__blocks">
				<pre>{{ account }}</pre>
				<div class="biography">
					<p>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.</p>
				</div>
				<div class="content-block">
					<h3>Shared files with {{ userId }}</h3>
				</div>
				<div class="content-block">
					<h3>Recent messages with {{ userId }}</h3>
				</div>
				<div class="content-block">
					<h3>Public activity by {{ userId }}</h3>
				</div>

				<div class="biography">
					<p>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.</p>
				</div>
				<div class="biography">
					<p>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.</p>
				</div>
				<div class="biography">
					<p>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.</p>
				</div>
				<div class="biography">
					<p>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.</p>
				</div>
				<div class="biography">
					<p>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.</p>
				</div>
			</div>
		</div>
		<ProfileSettings :open.sync="showSettings" />
	</div>
</template>

<script>
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import ProfileSettings from './ProfileSettings'

import { getCurrentUser } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'Profile',
	components: {
		Avatar,
		Actions,
		ActionButton,
		Modal,
		ProfileSettings,
	},
	data() {
		return {
			userId: loadState('core', 'userId', null),
			account: loadState('core', 'account', null),
			status: loadState('core', 'status', null),
			showSettings: false,
		}
	},
	methods: {
		openSettings() {
			this.showSettings = true
		},
	},
	computed: {
		isCurrentUser() {
			return getCurrentUser()?.uid === this.userId
		},
	},
}
</script>

<style lang="scss" scoped>
	$profile-max-width: 1024px;
	.profile {
		width: 100%;

		&__header {
			background-color: var(--color-primary);
			height: 190px;
			position: sticky;
			top: -40px;

			h2 {
				color: var(--color-primary-text);
				max-width: $profile-max-width;
				margin-bottom: 0;
				margin-left: auto;
				margin-right: auto;
				width: 100%;
				padding-left: 240px;
				padding-top: 113px;
			}

			p {
				color: var(--color-primary-text);
				max-width: 1024px;
				margin-left: auto;
				margin-right: auto;
				width: 100%;
				padding-left: 240px;
			}

			button.primary {
				margin-right: 40px;
				border: 1px solid var(--color-primary-text);
			}

			.user-status {
				margin-top: 10px;
				color: var(--color-main-text);
			}
		}
		&__sidebar {
			position: sticky;
			top: var(--header-height);
			align-self: flex-start;
			padding-top: 20px;
			min-width: 240px;
			margin-top: -150px;

			.avatardiv, h2 {
				text-align: center;
				margin: auto;
				display: block;
				padding: 8px;
			}
		}
		&__content {
			max-width: $profile-max-width;
			margin-left: auto;
			display: flex;
			margin-right: auto;
			width: 100%;
		}
	}

	.user-info {
		li {
			display: flex;
			background-position: left;
			margin-left: 0;
			padding: 3px;
			padding-left: 30px;
			opacity: .8;
			span {
				flex-grow: 1;
				padding-top: 11px;
			}
		}
	}

	.user-actions {
		display: flex;
		flex-direction: column;
		&__other {
			display: flex;
		}
	}

	.icon-twitter {
		background-image: url("data:image/svg+xml,%3Csvg width='32' height='32' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M21.61 4.62h.16c1.77 0 3.37.75 4.5 1.94 1.4-.27 2.71-.79 3.9-1.5a6.17 6.17 0 01-2.7 3.41C28.7 8.32 29.9 8 31 7.5a12.47 12.47 0 01-3.07 3.19l.02.8c0 8.13-6.2 17.51-17.52 17.51-3.47 0-6.7-1.02-9.43-2.77a12.46 12.46 0 009.11-2.54 6.16 6.16 0 01-5.75-4.28 6.1 6.1 0 002.78-.1 6.16 6.16 0 01-4.93-6.04v-.08c.83.46 1.78.74 2.78.77a6.15 6.15 0 01-1.9-8.22 17.48 17.48 0 0012.69 6.44 6.16 6.16 0 015.83-7.56z'/%3E%3C/svg%3E");
		background-size: 16px;
	}

	.content-block {
		h3 {
			font-weight: bold;
		}
	}
</style>
