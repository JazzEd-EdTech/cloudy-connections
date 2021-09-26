<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files_Sharing\API;

use OCA\FederatedFileSharing\DiscoveryManager;
use OCA\Files_Sharing\Activity;
use OCP\Files\NotFoundException;

class Server2Server {

	/**
	 * create a new share
	 *
	 * @param array $params
	 * @return \OC_OCS_Result
	 */
	public function createShare($params) {

		if (!$this->isS2SEnabled(true)) {
			return new \OC_OCS_Result(null, 503, 'Server does not support federated cloud sharing');
		}

		$remote = isset($_POST['remote']) ? $_POST['remote'] : null;
		$token = isset($_POST['token']) ? $_POST['token'] : null;
		$name = isset($_POST['name']) ? $_POST['name'] : null;
		$owner = isset($_POST['owner']) ? $_POST['owner'] : null;
		$shareWith = isset($_POST['shareWith']) ? $_POST['shareWith'] : null;
		$remoteId = isset($_POST['remoteId']) ? (int)$_POST['remoteId'] : null;

		if ($remote && $token && $name && $owner && $remoteId && $shareWith) {

			if(!\OCP\Util::isValidFileName($name)) {
				return new \OC_OCS_Result(null, 400, 'The mountpoint name contains invalid characters.');
			}

			// FIXME this should be a method in the user management instead
			\OCP\Util::writeLog('files_sharing', 'shareWith before, ' . $shareWith, \OCP\Util::DEBUG);
			\OCP\Util::emitHook(
				'\OCA\Files_Sharing\API\Server2Server',
				'preLoginNameUsedAsUserName',
				array('uid' => &$shareWith)
			);
			\OCP\Util::writeLog('files_sharing', 'shareWith after, ' . $shareWith, \OCP\Util::DEBUG);

			if (!\OCP\User::userExists($shareWith)) {
				return new \OC_OCS_Result(null, 400, 'User does not exists');
			}

			\OC_Util::setupFS($shareWith);

			$discoveryManager = new DiscoveryManager(
				\OC::$server->getMemCacheFactory(),
				\OC::$server->getHTTPClientService()
			);
			$externalManager = new \OCA\Files_Sharing\External\Manager(
					\OC::$server->getDatabaseConnection(),
					\OC\Files\Filesystem::getMountManager(),
					\OC\Files\Filesystem::getLoader(),
					\OC::$server->getHTTPHelper(),
					\OC::$server->getNotificationManager(),
					$discoveryManager,
					$shareWith
				);

			try {
				$externalManager->addShare($remote, $token, '', $name, $owner, false, $shareWith, $remoteId);
				$shareId = \OC::$server->getDatabaseConnection()->lastInsertId('*PREFIX*share_external');

				$user = $owner . '@' . $this->cleanupRemote($remote);

				\OC::$server->getActivityManager()->publishActivity(
					Activity::FILES_SHARING_APP, Activity::SUBJECT_REMOTE_SHARE_RECEIVED, array($user, trim($name, '/')), '', array(),
					'', '', $shareWith, Activity::TYPE_REMOTE_SHARE, Activity::PRIORITY_LOW);

				$urlGenerator = \OC::$server->getURLGenerator();

				$notificationManager = \OC::$server->getNotificationManager();
				$notification = $notificationManager->createNotification();
				$notification->setApp('files_sharing')
					->setUser($shareWith)
					->setDateTime(new \DateTime())
					->setObject('remote_share', $shareId)
					->setSubject('remote_share', [$user, trim($name, '/')]);

				$declineAction = $notification->createAction();
				$declineAction->setLabel('decline')
					->setLink($urlGenerator->getAbsoluteURL($urlGenerator->linkTo('', 'ocs/v1.php/apps/files_sharing/api/v1/remote_shares/pending/' . $shareId)), 'DELETE');
				$notification->addAction($declineAction);

				$acceptAction = $notification->createAction();
				$acceptAction->setLabel('accept')
					->setLink($urlGenerator->getAbsoluteURL($urlGenerator->linkTo('', 'ocs/v1.php/apps/files_sharing/api/v1/remote_shares/pending/' . $shareId)), 'POST');
				$notification->addAction($acceptAction);

				$notificationManager->notify($notification);

				return new \OC_OCS_Result();
			} catch (\Exception $e) {
				\OCP\Util::writeLog('files_sharing', 'server can not add remote share, ' . $e->getMessage(), \OCP\Util::ERROR);
				return new \OC_OCS_Result(null, 500, 'internal server error, was not able to add share from ' . $remote);
			}
		}

		return new \OC_OCS_Result(null, 400, 'server can not add remote share, missing parameter');
	}

	/**
	 * accept server-to-server share
	 *
	 * @param array $params
	 * @return \OC_OCS_Result
	 */
	public function acceptShare($params) {

		if (!$this->isS2SEnabled()) {
			return new \OC_OCS_Result(null, 503, 'Server does not support federated cloud sharing');
		}

		$id = $params['id'];
		$token = isset($_POST['token']) ? $_POST['token'] : null;
		$share = self::getShare($id, $token);

		if ($share) {
			list($file, $link) = self::getFile($share['uid_owner'], $share['file_source']);

			$event = \OC::$server->getActivityManager()->generateEvent();
			$event->setApp(Activity::FILES_SHARING_APP)
				->setType(Activity::TYPE_REMOTE_SHARE)
				->setAffectedUser($share['uid_owner'])
				->setSubject(Activity::SUBJECT_REMOTE_SHARE_ACCEPTED, [$share['share_with'], basename($file)])
				->setObject('files', $share['file_source'], $file)
				->setLink($link);
			\OC::$server->getActivityManager()->publish($event);
		}

		return new \OC_OCS_Result();
	}

	/**
	 * decline server-to-server share
	 *
	 * @param array $params
	 * @return \OC_OCS_Result
	 */
	public function declineShare($params) {

		if (!$this->isS2SEnabled()) {
			return new \OC_OCS_Result(null, 503, 'Server does not support federated cloud sharing');
		}

		$id = $params['id'];
		$token = isset($_POST['token']) ? $_POST['token'] : null;

		$share = $this->getShare($id, $token);

		if ($share) {
			// userId must be set to the user who unshares
			\OCP\Share::unshare($share['item_type'], $share['item_source'], $share['share_type'], $share['share_with'], $share['uid_owner']);

			list($file, $link) = $this->getFile($share['uid_owner'], $share['file_source']);

			$event = \OC::$server->getActivityManager()->generateEvent();
			$event->setApp(Activity::FILES_SHARING_APP)
				->setType(Activity::TYPE_REMOTE_SHARE)
				->setAffectedUser($share['uid_owner'])
				->setSubject(Activity::SUBJECT_REMOTE_SHARE_DECLINED, [$share['share_with'], basename($file)])
				->setObject('files', $share['file_source'], $file)
				->setLink($link);
			\OC::$server->getActivityManager()->publish($event);
		}

		return new \OC_OCS_Result();
	}

	/**
	 * remove server-to-server share if it was unshared by the owner
	 *
	 * @param array $params
	 * @return \OC_OCS_Result
	 */
	public function unshare($params) {

		if (!$this->isS2SEnabled()) {
			return new \OC_OCS_Result(null, 503, 'Server does not support federated cloud sharing');
		}

		$id = $params['id'];
		$token = isset($_POST['token']) ? $_POST['token'] : null;

		$query = \OCP\DB::prepare('SELECT * FROM `*PREFIX*share_external` WHERE `remote_id` = ? AND `share_token` = ?');
		$query->execute(array($id, $token));
		$share = $query->fetchRow();

		if ($token && $id && !empty($share)) {

			$remote = $this->cleanupRemote($share['remote']);

			$owner = $share['owner'] . '@' . $remote;
			$mountpoint = $share['mountpoint'];
			$user = $share['user'];

			$query = \OCP\DB::prepare('DELETE FROM `*PREFIX*share_external` WHERE `remote_id` = ? AND `share_token` = ?');
			$query->execute(array($id, $token));

			if ($share['accepted']) {
				$path = trim($mountpoint, '/');
			} else {
				$path = trim($share['name'], '/');
			}

			$notificationManager = \OC::$server->getNotificationManager();
			$notification = $notificationManager->createNotification();
			$notification->setApp('files_sharing')
				->setUser($share['user'])
				->setObject('remote_share', (int) $share['id']);
			$notificationManager->markProcessed($notification);

			\OC::$server->getActivityManager()->publishActivity(
				Activity::FILES_SHARING_APP, Activity::SUBJECT_REMOTE_SHARE_UNSHARED, array($owner, $path), '', array(),
				'', '', $user, Activity::TYPE_REMOTE_SHARE, Activity::PRIORITY_MEDIUM);
		}

		return new \OC_OCS_Result();
	}

	private function cleanupRemote($remote) {
		$remote = substr($remote, strpos($remote, '://') + 3);

		return rtrim($remote, '/');
	}

	/**
	 * get share
	 *
	 * @param int $id
	 * @param string $token
	 * @return array
	 */
	private function getShare($id, $token) {
		$query = \OCP\DB::prepare('SELECT * FROM `*PREFIX*share` WHERE `id` = ? AND `token` = ? AND `share_type` = ?');
		$query->execute(array($id, $token, \OCP\Share::SHARE_TYPE_REMOTE));
		$share = $query->fetchRow();

		return $share;
	}

	/**
	 * get file
	 *
	 * @param string $user
	 * @param int $fileSource
	 * @return array with internal path of the file and a absolute link to it
	 */
	private function getFile($user, $fileSource) {
		\OC_Util::setupFS($user);

		try {
			$file = \OC\Files\Filesystem::getPath($fileSource);
		} catch (NotFoundException $e) {
			$file = null;
		}
		$args = \OC\Files\Filesystem::is_dir($file) ? array('dir' => $file) : array('dir' => dirname($file), 'scrollto' => $file);
		$link = \OCP\Util::linkToAbsolute('files', 'index.php', $args);

		return array($file, $link);

	}

	/**
	 * check if server-to-server sharing is enabled
	 *
	 * @param bool $incoming
	 * @return bool
	 */
	private function isS2SEnabled($incoming = false) {

		$result = \OCP\App::isEnabled('files_sharing');

		if ($incoming) {
			$result = $result && \OCA\Files_Sharing\Helper::isIncomingServer2serverShareEnabled();
		} else {
			$result = $result && \OCA\Files_Sharing\Helper::isOutgoingServer2serverShareEnabled();
		}

		return $result;
	}

}
