<?php

declare(strict_types=1);

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 */

namespace OC\Authentication\Token;

use BadMethodCallException;
use OC\Authentication\Events\RemoteWipeFinished;
use OC\Authentication\Events\RemoteWipeStarted;
use OC\Authentication\Exceptions\InvalidTokenException;
use OC\Authentication\Exceptions\WipeTokenException;
use OCP\Activity\IEvent;
use OCP\Activity\IManager as IActivityManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\ILogger;
use OCP\Notification\IManager as INotificationManager;
use Symfony\Component\EventDispatcher\EventDispatcher;

class RemoteWipe {

	/** @var IProvider */
	private $tokenProvider;

	/** @var IEventDispatcher */
	private $eventDispatcher;

	/** @var ILogger */
	private $logger;

	public function __construct(IProvider $tokenProvider,
								IEventDispatcher $eventDispatcher,
								ILogger $logger) {
		$this->tokenProvider = $tokenProvider;
		$this->eventDispatcher = $eventDispatcher;
		$this->logger = $logger;
	}

	/**
	 * @param string $token
	 *
	 * @return bool whether wiping was started
	 * @throws InvalidTokenException
	 *
	 */
	public function start(string $token): bool {
		try {
			$this->tokenProvider->getToken($token);

			// We expect a WipedTokenException here. If we reach this point this
			// is an ordinary token
			return false;
		} catch (WipeTokenException $e) {
			// Expected -> continue below
		}

		$dbToken = $e->getToken();

		$this->logger->info("user " . $dbToken->getUID() . " started a remote wipe");

		$this->eventDispatcher->dispatch(RemoteWipeStarted::class, new RemoteWipeStarted($dbToken));

		return true;
	}

	/**
	 * @param string $token
	 *
	 * @return bool whether wiping could be finished
	 * @throws InvalidTokenException
	 */
	public function finish(string $token): bool {
		try {
			$this->tokenProvider->getToken($token);

			// We expect a WipedTokenException here. If we reach this point this
			// is an ordinary token
			return false;
		} catch (WipeTokenException $e) {
			// Expected -> continue below
		}

		$dbToken = $e->getToken();

		$this->tokenProvider->invalidateToken($token);

		$this->logger->info("user " . $dbToken->getUID() . " finished a remote wipe");
		$this->eventDispatcher->dispatch(RemoteWipeFinished::class, new RemoteWipeFinished($dbToken));

		return true;
	}

}
