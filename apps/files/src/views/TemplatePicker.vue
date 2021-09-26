<!--
  - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<Modal v-if="opened"
		:clear-view-delay="-1"
		class="templates-picker"
		size="large"
		@close="close">
		<form class="templates-picker__form" @submit.prevent.stop="onSubmit">
			<h3>{{ t('files', 'Pick a template') }}</h3>

			<!-- Templates list -->
			<ul class="templates-picker__list">
				<TemplatePreview
					v-for="template in provider.templates"
					:key="template.fileid"
					v-bind="template"
					:checked="checked === template.fileid"
					@check="onCheck" />

				<TemplatePreview
					v-bind="emptyTemplate"
					:checked="checked === emptyTemplate.fileid"
					@check="onCheck" />
			</ul>

			<!-- Cancel and submit -->
			<div class="templates-picker__buttons">
				<button @click="close">
					{{ t('files', 'Cancel') }}
				</button>
				<input type="submit"
					class="primary"
					:value="t('files', 'Create')"
					:aria-label="t('files', 'Create a new file with the ')">
			</div>
		</form>

		<EmptyContent class="templates-picker__loading" v-if="loading" icon="icon-loading">
			{{ t('files', 'Creating file') }}
		</EmptyContent>
	</Modal>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'

import axios from '@nextcloud/axios'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import Modal from '@nextcloud/vue/dist/Components/Modal'

import TemplatePreview from '../components/TemplatePreview'

export default {
	name: 'TemplatePicker',

	components: {
		EmptyContent,
		Modal,
		TemplatePreview,
	},

	props: {
		logger: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			// Check empty template by default
			checked: -1,
			loading: false,
			name: null,
			opened: false,
			provider: null,
		}
	},

	computed: {
		emptyTemplate() {
			return {
				basename: t('files', 'Empty file'),
				fileid: -1,
				filename: this.t('files', 'Empty file'),
				hasPreview: false,
				mime: this.provider?.mimetypes[0] || this.provider?.mimetypes,
			}
		},

		selectedTemplate() {
			return this.provider.templates.find(template => template.fileid === this.checked)
		},
	},

	methods: {
		/**
		 * Open the picker
		 * @param {string} name the file name to create
		 * @param {object} provider the template provider picked
		 */
		open(name, provider) {
			this.checked = this.emptyTemplate.fileid
			this.name = name
			this.opened = true
			this.provider = provider
		},

		/**
		 * Close the picker and reset variables
		 */
		close() {
			this.checked = this.emptyTemplate.fileid
			this.loading = false
			this.name = null
			this.opened = false
			this.provider = null
		},

		/**
		 * Manages the radio template picker change
		 * @param {number} fileid the selected template file id
		 */
		onCheck(fileid) {
			this.checked = fileid
		},

		async onSubmit() {
			this.loading = true
			const currentDirectory = this.getCurrentDirectory()
			const fileList = OCA?.Files?.App?.currentFileList

			try {
				const response = await axios.post(generateOcsUrl('apps/files/api/v1/templates', 2) + 'create', {
					filePath: `${currentDirectory}/${this.name}`,
					templatePath: this.selectedTemplate?.filename,
				})

				const fileInfo = response.data.ocs.data
				this.logger.debug('Created new file', fileInfo)

				// Run default action
				const fileAction = OCA.Files.fileActions.getDefaultFileAction(fileInfo.mime, 'file', OC.PERMISSION_ALL)
				fileAction.action(fileInfo.basename, {
					$file: null,
					dir: currentDirectory,
					fileList,
					fileActions: fileList?.fileActions,
				})

				// Reload files list
				fileList?.reload?.() || window.location.reload()

				this.close()
			} catch (error) {
				this.logger.error('Error while creating the new file from template', error)
				showError(this.t('files', 'Unable to create new file from template'))
			} finally {
				this.loading = false
			}
		},

		/**
		 * Return the current directory, fallback to root
		 * @returns {string}
		 */
		getCurrentDirectory() {
			const currentDirInfo = OCA?.Files?.App?.currentFileList?.dirInfo
				|| { path: '/', name: '' }

			// Make sure we don't have double slashes
			return `${currentDirInfo.path}/${currentDirInfo.name}`.replace(/\/\//gi, '/')
		},
	},
}
</script>

<style lang="scss" scoped>
.templates-picker {
	&__form {
		padding: 16px;
	}

	&__list {
		display: flex;
		flex-wrap: wrap;
	}
	&__buttons {
		display: flex;
		justify-content: space-between;
		margin-top: 16px;
	}

	// Make sure we're relative for the loading emptycontent on top
	/deep/ .modal-container {
		position: relative;
	}

	&__loading {
		position: absolute;
		top: 0;
		left: 0;
		justify-content: center;
		width: 100%;
		height: 100%;
		margin: 0;
		background-color: var(--color-main-background-translucent);
	}
}

</style>
