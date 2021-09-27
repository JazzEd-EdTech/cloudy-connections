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

namespace OCA\Settings\Settings\Admin;

use OCA\Settings\AppInfo\Application;
use OCA\Settings\Service\AuthorizedGroupService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\Settings\IDelegatedSettings;
use OCP\Settings\IIconSection;
use OCP\Settings\IManager;
use OCP\Settings\ISettings;

class Delegation implements ISettings {
	protected $appName;

	/** @var IManager */
	private $settingManager;

	/** @var IInitialState $initialStateService */
	private $initialStateService;

	/** @var IGroupManager $groupManager */
	private $groupManager;

	/** @var AuthorizedGroupService $service */
	private $service;

	public function __construct(
		IManager $settingManager,
		IInitialState $initialStateService,
		IGroupManager $groupManager,
		AuthorizedGroupService $service,
		IL10n $l
	) {
		$this->appName = Application::APP_ID;
		$this->settingManager = $settingManager;
		$this->initialStateService = $initialStateService;
		$this->groupManager = $groupManager;
		$this->service = $service;
		$this->l = $l;
	}

	public function getForm(): TemplateResponse {
		$settingsClasses = $this->settingManager->getAdminDelegationAllowedSettings();

		// Available settings page initialization
		$sections = $this->settingManager->getAdminSections();
		$settings = [];
		foreach ($settingsClasses as $settingClass) {
			$setting = \OC::$server->get($settingClass);
			$settingSection = $setting->getSection();
			$sectionName = $settingSection;
			foreach ($sections as $sectionPriority) {
				foreach ($sectionPriority as $section) {
					/** @var IIconSection $section */
					if ($section->getID() == $sectionName) {
						$sectionName = $section->getName();
					}
					break; // break the two foreach loop
				}
			}
			if (($setting instanceof IDelegatedSettings) && $setting->getName()) {
				$sectionName .= ' - ' . $setting->getName();
			}
			$settings[] = [
				'class' => $settingClass,
				'sectionName' => $sectionName
			];
		}
		$this->initialStateService->provideInitialState('available-settings', $settings);

		// Available groups initialization
		$groups = [];
		$groupsClass = $this->groupManager->search('');
		foreach ($groupsClass as $group) {
			if ($group->getGID() === 'admin') {
				continue; // Admin already have access to everything
			}
			$groups[] = [
				'displayName' => $group->getDisplayName(),
				'gid' => $group->getGID(),
			];
		}
		$this->initialStateService->provideInitialState('available-groups', $groups);

		// Already set authorized groups
		$this->initialStateService->provideInitialState('authorized-groups', $this->service->findAll());

		return new TemplateResponse(Application::APP_ID, 'settings/admin/delegation', [], '');
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection() {
		return 'admindelegation';
	}

	/*
	 * @inheritdoc
	 */
	public function getPriority() {
		return 75;
	}
}
