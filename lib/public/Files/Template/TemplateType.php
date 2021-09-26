<?php
/*
 * @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);

namespace OCP\Files\Template;

/**
 * @since 21.0.0
 */
class TemplateType implements \JsonSerializable {

	protected $appId;
	protected $mimetypes = [];
	protected $actionName;
	protected $fileExtension;
	protected $iconClass;

	public function __construct(
		string $appId, string $actionName, string $fileExtension
	) {
		$this->appId = $appId;
		$this->actionName = $actionName;
		$this->fileExtension = $fileExtension;
	}

	public function setIconClass(string $iconClass): TemplateType {
		$this->iconClass = $iconClass;
		return $this;
	}

	public function addMimetype(string $mimetype): TemplateType {
		$this->mimetypes[] = $mimetype;
		return $this;
	}

	public function getMimetypes(): array {
		return $this->mimetypes;
	}

	final public function jsonSerialize() {
		return [
			'app' => $this->appId,
			'label' => $this->actionName,
			'extension' => $this->fileExtension,
			'iconClass' => $this->iconClass,
			'mimetypes' => $this->mimetypes
		];
	}
}
