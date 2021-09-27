<?php

/**
 * @copyright Copyright (c) 2021 Nextcloud GmbH
 *
 * @author Carl Schwan <carl@carlschwan.eu>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Settings\Controller;

use OC\Settings\AuthorizedGroup;
use OCA\Settings\Service\AuthorizedGroupService;
use OCA\Settings\Service\NotFoundException;
use OCP\DB\Exception;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

class AuthorizedGroupController extends Controller {
	use Errors;

	/** @var AuthorizedGroupService $service */
	private $service;

	public function __construct($AppName, IRequest $request, AuthorizedGroupService $service) {
		parent::__construct($AppName, $request);
		$this->service = $service;
	}

	/**
	 * @throws NotFoundException
	 * @throws Exception
	 * @AuthorizedAdminSetting(settings=OCA\Settings\Settings\Admin\Delegation)
	 */
	public function saveSettings(array $groups, string $class): DataResponse {
		$oldGroups = $this->service->findOldGroups($class);

		foreach ($oldGroups as $group) {
			/** @var AuthorizedGroup $group */
			$removed = true;
			foreach ($groups as $groupData) {
				if ($groupData['gid'] === $group->getGroupId()) {
					$removed = false;
					break;
				}
			}
			if ($removed) {
				$this->service->delete($group->getId());
			}
		}

		foreach ($groups as $groupData) {
			$added = true;
			foreach ($oldGroups as $group) {
				/** @var AuthorizedGroup $group */
				if ($groupData['gid'] === $group->getGroupId()) {
					$added = false;
					break;
				}
			}
			if ($added) {
				$this->service->create($groupData['gid'], $class);
			}
		}
		return new DataResponse(['valid' => true]);
	}
}
