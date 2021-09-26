<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Dominik Schmidt <dev@dominik-schmidt.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

OCP\App::registerAdmin('user_ldap', 'settings');

$helper = new \OCA\user_ldap\lib\Helper();
$configPrefixes = $helper->getServerConfigurationPrefixes(true);
$ldapWrapper = new OCA\user_ldap\lib\LDAP();
$ocConfig = \OC::$server->getConfig();
if(count($configPrefixes) === 1) {
	$dbc = \OC::$server->getDatabaseConnection();
	$userManager = new OCA\user_ldap\lib\user\Manager($ocConfig,
		new OCA\user_ldap\lib\FilesystemHelper(),
		new OCA\user_ldap\lib\LogWrapper(),
		\OC::$server->getAvatarManager(),
		new \OCP\Image(),
		$dbc,
		\OC::$server->getUserManager()
	);
	$connector = new OCA\user_ldap\lib\Connection($ldapWrapper, $configPrefixes[0]);
	$ldapAccess = new OCA\user_ldap\lib\Access($connector, $ldapWrapper, $userManager);

	$ldapAccess->setUserMapper(new OCA\User_LDAP\Mapping\UserMapping($dbc));
	$ldapAccess->setGroupMapper(new OCA\User_LDAP\Mapping\GroupMapping($dbc));
	$userBackend  = new OCA\user_ldap\USER_LDAP($ldapAccess, $ocConfig);
	$groupBackend = new OCA\user_ldap\GROUP_LDAP($ldapAccess);
} else if(count($configPrefixes) > 1) {
	$userBackend  = new OCA\user_ldap\User_Proxy(
		$configPrefixes, $ldapWrapper, $ocConfig
	);
	$groupBackend  = new OCA\user_ldap\Group_Proxy($configPrefixes, $ldapWrapper);
}

if(count($configPrefixes) > 0) {
	// register user backend
	OC_User::useBackend($userBackend);
	OC_Group::useBackend($groupBackend);
}

\OCP\Util::connectHook(
	'\OCA\Files_Sharing\API\Server2Server',
	'preLoginNameUsedAsUserName',
	'\OCA\user_ldap\lib\Helper',
	'loginName2UserName'
);

if(OCP\App::isEnabled('user_webdavauth')) {
	OCP\Util::writeLog('user_ldap',
		'user_ldap and user_webdavauth are incompatible. You may experience unexpected behaviour',
		OCP\Util::WARN);
}
