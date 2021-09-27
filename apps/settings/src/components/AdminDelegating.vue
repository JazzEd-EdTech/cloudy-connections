<template>
	<div id="adminrightsubgranting" class="section">
		<h2>{{ t('settings', 'Admin Right Management') }}</h2>
		<p class="settings-hint">
			{{ t('settings', 'Here you can decide which group can access some of the admin settings.') }}
		</p>

		<div class="setting-list">
			<div v-for="setting in availableSettings" :key="setting.class">
				<h3>{{ setting.sectionName }}</h3>
				<GroupSelect :groups="availableGroups" :initial-state="authorizedGroups" :setting="setting" />
			</div>
		</div>
	</div>
</template>

<script>
import GroupSelect from './AdminDelegation/GroupSelect'

export default {
	name: 'AdminDelegating',
	components: {
		GroupSelect,
	},
	props: {
		availableSettings: {
			type: Array,
			required: true,
		},
		availableGroups: {
			type: Array,
			required: true,
		},
		authorizedGroups: {
			type: Array,
			required: true,
		},
	},
	computed: {},
	methods: {},
}
</script>
