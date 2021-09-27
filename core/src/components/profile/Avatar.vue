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
		<PropertyHeader :title="t('core', 'Profile picture')" property="avatar" />
		<div v-if="!imgSrc" class="avatar-preview">
			<div :class="{ 'icon-loading': loading }"><img :src="avatarUrl" class="cropped-image"></div>
			<div>
				<Actions>
					<ActionButton icon="icon-upload" @click="showFileChooser">
						{{ t('core', 'Upload avatar') }}
					</ActionButton>
				</Actions>
				<Actions>
					<ActionButton icon="icon-folder" @click="showFilePickerDialog">
						{{ t('core', 'Select from files') }}
					</ActionButton>
				</Actions>
				<Actions>
					<ActionButton icon="icon-delete" @click="removeAvatar">
						{{ t('core', 'Remove avatar') }}
					</ActionButton>
				</Actions>
			</div>
			<p><em>{{ t('core', 'png or jpg, max. 20 MB') }}</em></p>
		</div>
		<div v-else class="avatar-crop">
			<div class="crop-area">
				<VueCropper
					ref="cropper"
					:aspect-ratio="1 / 1"
					:src="imgSrc"
					preview=".preview" />
			</div>
			<button @click="imgSrc=null">
				{{ t('core', 'Cancel') }}
			</button>
			<button class="primary" @click="cropImage">
				{{ t('core', 'Set avatar') }}
			</button>
		</div>
		<input ref="input"
			type="file"
			name="image"
			accept="image/*"
			@change="setImage">
	</div>
</template>

<script>
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import VueCropper from 'vue-cropperjs'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import 'cropperjs/dist/cropper.css'
import PropertyHeader from './PropertyHeader'

const picker = getFilePickerBuilder(t('deck', 'Select avatar image'))
	.setMultiSelect(false)
	.setModal(true)
	.setType(1)
	.allowDirectories()
	.build()

export default {
	name: 'Avatar',
	components: { VueCropper, Actions, ActionButton, LibraryAvatar: Avatar, PropertyHeader },
	data() {
		return {
			imgSrc: null,
			avatarUrl: null,
			data: null,
			loading: false,
		}
	},
	beforeMount() {
		this.updateAvatar()
	},
	methods: {
		cropImage() {
			this.imgSrc = null
			this.saveAvatar()
		},
		setImage(e) {
			const file = e.target.files[0]
			if (file.type.indexOf('image/') === -1) {
				alert('Please select an image file')
				return
			}
			if (typeof FileReader === 'function') {
				const reader = new FileReader()
				reader.onload = (event) => {
					this.imgSrc = event.target.result
					this.$nextTick(() => this.$refs.cropper.replace(event.target.result))
				}
				reader.readAsDataURL(file)
			} else {
				alert('Sorry, FileReader API not supported')
			}
		},
		showFileChooser() {
			this.$refs.input.click()
		},

		saveAvatar() {
			this.loading = true
			this.$refs.cropper.getCroppedCanvas().toBlob((blob) => {
				const formData = new FormData()
				formData.append('files[]', blob)
				axios.post(generateUrl('/avatar/'), formData, {
					headers: {
						'Content-Type': 'multipart/form-data',
					},
				}).then(() => {
					this.updateAvatar()
				})
			})
		},

		async showFilePickerDialog() {
			const path = await picker.pick()
			await axios.post(generateUrl('/avatar/'), { path })
			this.imgSrc = generateUrl('/avatar/tmp') + '?requesttoken=' + encodeURIComponent(OC.requestToken) + '#' + Math.floor(Math.random() * 1000)
		},

		updateAvatar() {
			this.loading = true
			const newAvatarUrl = generateUrl('/avatar/') + getCurrentUser().uid + '/256?v=' + Date.now()
			const img = new Image()
			img.onload = () => {
				this.loading = false
				this.avatarUrl = newAvatarUrl
				oc_userconfig.avatar.version = Date.now()
			}
			img.src = newAvatarUrl
			// FIXME: we should emit an event to update all avatars on the page here
		},

		async removeAvatar() {
			this.loading = true
			await axios.delete(generateUrl('/avatar/'))
			window.oc_userconfig.avatar.generated = true
			this.updateAvatar()
		},
	},
}
</script>
<style lang="scss" scoped>
input[type="file"] {
	display: none;
}

.crop-area, .cropped-image {
	width: 300px;
}

.avatar-preview {
	display: flex;
	flex-direction: column;
	align-items: center;
	width: 300px;

	.cropped-image {
		width: 200px;
		height: 200px;
		border-radius: 50%;
		overflow: hidden;
		margin-bottom: 12px;
	}
}


img {
	width: 100%;
}

.crop-placeholder {
	width: 300px;
	height: 300px;
	border-radius: 50%;
	background: #ccc;
}

::v-deep .cropper-view-box {
	border-radius: 50%;
}
</style>
