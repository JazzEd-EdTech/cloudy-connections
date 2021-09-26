<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
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

// load needed apps
$RUNTIME_APPTYPES = ['filesystem', 'authentication', 'logging'];

OC_App::loadApps($RUNTIME_APPTYPES);

OC_Util::obEnd();

// Backends
$authBackend = new OCA\DAV\Connector\PublicAuth(\OC::$server->getConfig());

$serverFactory = new OCA\DAV\Connector\Sabre\ServerFactory(
	\OC::$server->getConfig(),
	\OC::$server->getLogger(),
	\OC::$server->getDatabaseConnection(),
	\OC::$server->getUserSession(),
	\OC::$server->getMountManager(),
	\OC::$server->getTagManager(),
	\OC::$server->getRequest()
);

$requestUri = \OC::$server->getRequest()->getRequestUri();

$linkCheckPlugin = new \OCA\DAV\Files\Sharing\PublicLinkCheckPlugin();

$server = $serverFactory->createServer($baseuri, $requestUri, $authBackend, function (\Sabre\DAV\Server $server) use ($authBackend, $linkCheckPlugin) {
	$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
	if (OCA\Files_Sharing\Helper::isOutgoingServer2serverShareEnabled() === false && !$isAjax) {
		// this is what is thrown when trying to access a non-existing share
		throw new \Sabre\DAV\Exception\NotAuthenticated();
	}

	$share = $authBackend->getShare();
	$rootShare = \OCP\Share::resolveReShare($share);
	$owner = $rootShare['uid_owner'];
	$isWritable = $share['permissions'] & (\OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_CREATE);
	$isReadable = $share['permissions'] & \OCP\Constants::PERMISSION_READ;
	$fileId = $share['file_source'];

	if (!$isWritable) {
		\OC\Files\Filesystem::addStorageWrapper('readonly', function ($mountPoint, $storage) {
			return new \OC\Files\Storage\Wrapper\PermissionsMask(array('storage' => $storage, 'mask' => \OCP\Constants::PERMISSION_READ + \OCP\Constants::PERMISSION_SHARE));
		});
	}
	if (!$isReadable) {
		return false;
	}

	OC_Util::setupFS($owner);
	$ownerView = \OC\Files\Filesystem::getView();
	$path = $ownerView->getPath($fileId);
	$fileInfo = $ownerView->getFileInfo($path);
	$linkCheckPlugin->setFileInfo($fileInfo);

	return new \OC\Files\View($ownerView->getAbsolutePath($path));
});

$server->addPlugin($linkCheckPlugin);

// And off we go!
$server->exec();
