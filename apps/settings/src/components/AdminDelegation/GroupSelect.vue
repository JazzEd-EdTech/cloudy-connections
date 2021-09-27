<template>
	<Multiselect
		v-model="selected"
		class="group-multiselect"
		:placeholder="t('settings', 'None')"
		track-by="gid"
		label="displayName"
		:options="groups"
		open-direction="bottom"
		:multiple="true"
		:allow-empty="true" />
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'GroupSelect',
	components: {
		Multiselect,
	},
	props: {
		groups: {
			type: Array,
			default: () => [],
		},
		setting: {
			type: Object,
			required: true,
		},
		initialState: {
			type: Array,
			required: true,
		},
	},
	data() {
		const selected = []
		for (const initialGroup of this.initialState) {
			if (initialGroup.class === this.setting.class) {
				const group = this.groups.find((group) => group.gid === initialGroup.group_id)
				if (group) {
					selected.push(group)
				}
			}
		}
		return {
			selected,
		}
	},
	watch: {
		selected() {
			this.saveGroups()
		},
	},
	methods: {
		async saveGroups() {
			const data = {
				groups: this.selected,
				class: this.setting.class,
			}
			await axios.post(generateUrl('/apps/settings/') + '/settings/authorizedgroups/saveSettings', data)
		},
	}
}
</script>

<style lang="scss">
.group-multiselect {
	width: 100%;
	margin-right: 0;
}
</style>
