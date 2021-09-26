<?php
/**
 * @copyright Copyright (c) 2016 Morris Jobke <hey@morrisjobke.de>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\WorkflowEngine\AppInfo;

use OCA\WorkflowEngine\Manager;
use OCP\AppFramework\QueryException;
use OCP\Template;
use OCA\WorkflowEngine\Controller\RequestTime;
use OCP\WorkflowEngine\IEntity;
use OCP\WorkflowEngine\IOperation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Application extends \OCP\AppFramework\App {

	const APP_ID = 'workflowengine';

	/** @var EventDispatcherInterface */
	protected $dispatcher;
	/** @var Manager */
	protected $manager;

	public function __construct() {
		parent::__construct(self::APP_ID);

		$this->getContainer()->registerAlias('RequestTimeController', RequestTime::class);

		$this->dispatcher = $this->getContainer()->getServer()->getEventDispatcher();
		$this->manager = $this->getContainer()->query(Manager::class);
	}

	/**
	 * Register all hooks and listeners
	 */
	public function registerHooksAndListeners() {
		$this->dispatcher->addListener(
			'OCP\WorkflowEngine::loadAdditionalSettingScripts',
			function() {
				if (!function_exists('style')) {
					// This is hacky, but we need to load the template class
					class_exists(Template::class, true);
				}

				style(self::APP_ID, [
					'admin',
				]);

				script('core', [
					'files/fileinfo',
					'files/client',
					'systemtags/systemtags',
					'systemtags/systemtagmodel',
					'systemtags/systemtagscollection',
				]);

				script(self::APP_ID, [
					'workflowengine',
				]);
			},
			-100
		);
	}

	public function registerRuleListeners() {
		$configuredEvents = $this->manager->getAllConfiguredEvents();

		foreach ($configuredEvents as $operationClass => $events) {
			foreach ($events as $entityClass => $eventNames) {
				array_map(function (string $eventName) use ($operationClass, $entityClass) {
					$this->dispatcher->addListener(
						$eventName,
						function (GenericEvent $event) use ($eventName, $operationClass, $entityClass) {
							$ruleMatcher = $this->manager->getRuleMatcher();
							try {
								/** @var IEntity $entity */
								$entity = $this->getContainer()->query($entityClass);
								$entity->prepareRuleMatcher($ruleMatcher, $eventName, $event);
								/** @var IOperation $operation */
								$operation = $this->getContainer()->query($operationClass);
								$operation->onEvent($eventName, $event, $ruleMatcher);
							} catch (QueryException $e) {
								// Ignore query exceptions since they might occur when an entity/operation were setup before by an app that is disabled now
							}
						}
					);
				}, $eventNames);
			}
		}
	}
}
