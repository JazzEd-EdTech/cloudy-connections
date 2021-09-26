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

namespace OC\Core\Controller;

use OC\Push\Manager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class PushController extends \OCP\AppFramework\OCSController {

	/** @var Manager */
	private $pushManager;
	/**
	 * @var IUserSession
	 */
	private $userSession;

	public function __construct(
		string $appName,
		IRequest $request,
		Manager $pushManager,
		IUserSession $userSession
	) {
		parent::__construct($appName, $request);

		$this->pushManager = $pushManager;
		$this->userSession = $userSession;
	}

	/**
	 * @NoAdminRequired
	 *
	 * Gets back the JWT to connect to the push service for the given topic
	 */
	public function getAccess(string $appId, string $topic): DataResponse {
		$uid = $this->userSession->getUser()->getUID();

		if (!$this->pushManager->isAvailable()) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!$this->pushManager->validateAccess($uid, $appId, $topic)) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$jwt = $this->pushManager->generateJWT($appId, $topic);
		$endpoint = $this->pushManager->getEndpoint($appId, $topic);

		return new DataResponse([
			'jwt' => $jwt,
			'endpoint' => $endpoint,
		]);
	}
}
