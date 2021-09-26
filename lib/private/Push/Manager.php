<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Push;

use OCP\AppFramework\QueryException;
use OCP\ILogger;
use OCP\IServerContainer;
use OCP\Push\IManager;
use OCP\Push\IPushApp;
use OCP\Push\IValidateAccess;

class Manager implements IPushApp {

	/** @var array */
	private $validatorClasses = [];

	/** @var string */
	private $pushClass;

	/** @var IPushApp */
	private $push;

	/** @var IServerContainer */
	private $container;
	/** @var ILogger */
	private $logger;

	public function __construct(IServerContainer $container, ILogger $logger) {
		$this->container = $container;
		$this->logger = $logger;
	}

	public function registerAccessValidator(string $appId, string $service): void {
		$this->logger->debug('Adding access validator "' . $service . '" for app: '. $appId, ['app' => 'internal_push']);
		$this->validatorClasses[$appId] = $service;
	}

	public function registerPushApp(string $service): void {
		$this->logger->debug('Registering push app "' . $service, ['app' => 'internal_push']);
		$this->pushClass = $service;
	}

	public function isAvailable(): bool {
		return $this->getPushApp()->isAvailable();
	}

	public function push(string $appId, string $topic, \JsonSerializable $payload): void {
		$this->getPushApp()->push($appId, $topic, $payload);
	}

	public function generateJWT(string $appId, string $topic): string {
		return $this->getPushApp()->generateJWT($appId, $topic);
	}

	public function validateAccess(string $userId, string $appId, string $topic): bool {
		if (!isset($this->validatorClasses[$appId])) {
			$this->logger->debug('No validator found for ' . $appId, ['app' => 'internal_push']);
			return false;
		}

		try {
			$validator = $this->container->query($this->validatorClasses[$appId]);
		} catch (QueryException $e) {
			$this->logger->debug('Could not query ' . $appId, ['app' => 'internal_push']);
			return false;
		}

		if (!($validator instanceof IValidateAccess)) {
			$this->logger->debug($this->validatorClasses[$appId] . ' is not an instance of ' . IValidateAccess::class, ['app' => 'internal_push']);
			return false;
		}

		try {
			return $validator->hasAccess($topic, $userId);
		} catch (\Throwable $e) {
			$this->logger->logException($e, ['app' => 'internal_push']);
			return false;
		}
	}

	public function getEndpoint(string $appId, string $topic): string {
		return $this->getPushApp()->getEndpoint($appId, $topic);
	}


	private function getPushApp(): IPushApp {
		if ($this->push !== null) {
			return $this->push;
		}

		$push = new VoidPushApp();
		if ($this->pushClass !== null) {
			try {
				$query = $this->container->query($this->pushClass);
				if ($query instanceof IPushApp) {
					$push = $query;
				} else {
					$this->logger->debug($this->pushClass . ' is not and instance of ' . IPushApp::class, ['app' => 'internal_push']);
				}
			} catch (QueryException $e) {
				$this->logger->debug('Could not query ' . $this->pushClass, ['app' => 'internal_push']);
			}
		}

		$this->push = $push;
		return $push;
	}


}
