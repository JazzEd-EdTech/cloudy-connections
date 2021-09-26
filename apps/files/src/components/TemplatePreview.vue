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
	<li class="template-picker__item" :style="style">
		<input :id="id"
			:checked="checked"
			type="radio"
			class="radio"
			name="template-picker"
			@change="onCheck">

		<label class="template-picker__preview" :for="id">
			<img class="template-picker__image"
				:class="failedPreview ? 'template-picker__image--failed' : ''"
				:src="previewUrl"
				alt=""
				@error="onFailure">

			<span class="template-picker__label">
				{{ basename }}
			</span>
		</label>
	</li>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { encodeFilePath } from '../utils/fileUtils'
import { getToken, isPublic } from '../utils/davUtils'

const margin = 8
const width = margin * 20

export default {
	name: 'TemplatePreview',

	props: {
		basename: {
			type: String,
			required: true,
		},
		checked: {
			type: Boolean,
			default: false,
		},
		fileid: {
			type: [String, Number],
			required: true,
		},
		filename: {
			type: String,
			required: true,
		},
		hasPreview: {
			type: Boolean,
			default: true,
		},
		mime: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			failedPreview: false,
		}
	},

	computed: {
		id() {
			return `template-picker-${this.fileid}`
		},

		previewUrl() {
			// If original preview failed, fallback to mime icon
			if (this.failedPreview && this.mimeIcon) {
				return generateUrl(this.mimeIcon)
			}

			// TODO: find a nicer standard way of doing this?
			if (isPublic()) {
				return generateUrl(`/apps/files_sharing/publicpreview/${getToken()}?fileId=${this.fileid}&file=${encodeFilePath(this.filename)}&x=${width}&y=${width}`)
			}
			return generateUrl(`/core/preview?fileId=${this.fileid}&x=${width}&y=${width}`)
		},

		mimeIcon() {
			return OC.MimeType.getIconUrl(this.mime)
		},

		/**
		 * Style css vars bin,d
		 * @returns {Object}
		 */
		style() {
			return {
				'--margin': margin + 'px',
				'--width': width + 'px',
			}
		},
	},

	methods: {
		onCheck() {
			this.$emit('check', this.fileid)
		},
		onFailure() {
			this.failedPreview = true
		},
	},
}
</script>

<style lang="scss" scoped>

.template-picker {
	&__item {
		display: flex;
	}

	&__preview {
		display: flex;
		overflow: hidden;
		flex-direction: column;
		justify-content: center;
		width: var(--width);
		min-height: var(--width);
		margin: var(--margin);
		padding: 0;
		text-align: center;
		border: 2px solid var(--color-main-background);
		border-radius: var(--border-radius-large);
		background-color: var(--color-background-hover);

		* {
			cursor: pointer;
		}

		&::before {
			display: none !important;
		}

		input:checked + & {
			border-color: var(--color-primary);
		}
	}

	&__image {
		max-width: 100%;
		padding: var(--margin);
		padding-bottom: 0;
		background-color: var(--color-main-background);

		&--failed {
			background-color: transparent !important;
		}
	}

	&__label {
		padding: var(--margin);
	}
}

</style>
