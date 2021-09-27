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


namespace OC\Core\Controller;


use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\UserStatus\IManager;
use OCP\UserStatus\IUserStatus;

class ProfileController extends \OCP\AppFramework\Controller {

	/** @var IL10N */
	private $l10n;
	/** @var IUserSession */
	private $userSession;
	/** @var IAccountManager */
	private $accountManager;

	public function __construct($appName, IRequest $request, IL10N $l10n, IUserSession $userSession, IInitialState $initialState, IAccountManager $accountManager) {
		parent::__construct($appName, $request);
		$this->l10n = $l10n;
		$this->userSession = $userSession;
		$this->initialState = $initialState;
		$this->accountManager = $accountManager;
	}

	/**
	 * @NoCSRFRequired
	 * @UseSession
	 * FIXME: Public page annotation blocks the user session somehow
	 * @param string|null $user
	 */
	public function profile(string $userId = null) {
		$isLoggedIn = $this->userSession->isLoggedIn();
		$account = $this->accountManager->getAccount(\OC::$server->getUserManager()->get($userId));
		$this->initialState->provideInitialState('userId', $userId);
		$this->initialState->provideInitialState('account', $account->jsonSerialize());

		/** @var IManager $status */
		$status = \OC::$server->get(IManager::class);
		$status = $status->getUserStatuses([$userId]);
		$status = array_pop($status);
		if ($status) {
			$this->initialState->provideInitialState('status', [
				'icon' => $status->getIcon(),
				'message' => $status->getMessage(),
				'status' => $status->getStatus(),
			]);
		}
		\OCP\Util::addScript('core', 'dist/profile');
		return new TemplateResponse(
			'core', 'profile',
			['userId' => $userId],
			($isLoggedIn ? TemplateResponse::RENDER_AS_USER : TemplateResponse::RENDER_AS_PUBLIC)
		);
	}
}
