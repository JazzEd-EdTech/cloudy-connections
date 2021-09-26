<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christian Kampka <christian@kampka.net>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Denis Mosolov <denismosolov@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author michag86 <micha_g@arcor.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Patrik Kernstock <info@pkern.at>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Ruben Homs <ruben@homs.codes>
 * @author sualko <klaus@jsxc.org>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Pulzer <t.pulzer@kniel.de>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

/** @var $application Symfony\Component\Console\Application */
$application->add(new \Stecman\Component\Symfony\Console\BashCompletion\CompletionCommand());
$coreCommandFactories['status'] = \OC\Core\Command\Status::class;
$coreCommandFactories['check'] = \OC\Core\Command\Check::class;
$coreCommandFactories['app:check-code'] = \OC\Core\Command\App\CheckCode::class;
$coreCommandFactories['l10n:createjs'] = \OC\Core\Command\L10n\CreateJs::class;
$application->add(new \OC\Core\Command\Integrity\SignApp(
		\OC::$server->getIntegrityCodeChecker(),
		new \OC\IntegrityCheck\Helpers\FileAccessHelper(),
		\OC::$server->getURLGenerator()
));
$application->add(new \OC\Core\Command\Integrity\SignCore(
		\OC::$server->getIntegrityCodeChecker(),
		new \OC\IntegrityCheck\Helpers\FileAccessHelper()
));
$coreCommandFactories['integrity:check-app'] = \OC\Core\Command\Integrity\CheckApp::class;
$coreCommandFactories['integrity:check-core'] = \OC\Core\Command\Integrity\CheckCore::class;

if (\OC::$server->getConfig()->getSystemValue('installed', false)) {
	$coreCommandFactories['app:enable'] = \OC\Core\Command\App\Enable::class;
	$coreCommandFactories['app:disable'] = \OC\Core\Command\App\Disable::class;
	$coreCommandFactories['app:install'] = \OC\Core\Command\App\Install::class;
	$coreCommandFactories['app:getpath'] = \OC\Core\Command\App\GetPath::class;
	$coreCommandFactories['app:list'] = \OC\Core\Command\App\ListApps::class;
	$coreCommandFactories['app:remove'] = \OC\Core\Command\App\Remove::class;
	$coreCommandFactories['app:update'] = \OC\Core\Command\App\Update::class;

	$coreCommandFactories['twofactorauth:cleanup'] = \OC\Core\Command\TwoFactorAuth\Cleanup::class;
	$coreCommandFactories['twofactorauth:enforce'] = \OC\Core\Command\TwoFactorAuth\Enforce::class;
	$coreCommandFactories['twofactorauth:enable'] = \OC\Core\Command\TwoFactorAuth\Enable::class;
	$coreCommandFactories['twofactorauth:disable'] = \OC\Core\Command\TwoFactorAuth\Disable::class;
	$coreCommandFactories['twofactorauth:state'] = \OC\Core\Command\TwoFactorAuth\State::class;

	$coreCommandFactories['background:cron'] = \OC\Core\Command\Background\Cron::class;
	$coreCommandFactories['background:webcron'] = \OC\Core\Command\Background\WebCron::class;
	$coreCommandFactories['background:ajax'] = \OC\Core\Command\Background\Ajax::class;

	$coreCommandFactories['broadcast:test'] = \OC\Core\Command\Broadcast\Test::class;

	$coreCommandFactories['config:app:delete'] = \OC\Core\Command\Config\App\DeleteConfig::class;
	$coreCommandFactories['config:app:get'] = \OC\Core\Command\Config\App\GetConfig::class;
	$coreCommandFactories['config:app:set'] = \OC\Core\Command\Config\App\SetConfig::class;
	$coreCommandFactories['config:import'] = \OC\Core\Command\Config\Import::class;
	$coreCommandFactories['config:list'] = \OC\Core\Command\Config\ListConfigs::class;
	$coreCommandFactories['config:system:delete'] = \OC\Core\Command\Config\System\DeleteConfig::class;
	$coreCommandFactories['config:system:get'] = \OC\Core\Command\Config\System\GetConfig::class;
	$coreCommandFactories['config:system:set'] = \OC\Core\Command\Config\System\SetConfig::class;

	$coreCommandFactories['db:convert-type'] = \OC\Core\Command\Db\ConvertType::class;
	$coreCommandFactories['db:convert-mysql-charset'] = \OC\Core\Command\Db\ConvertMysqlToMB4::class;
	$coreCommandFactories['db:convert-filecache-bigint'] = \OC\Core\Command\Db\ConvertFilecacheBigInt::class;
	$coreCommandFactories['db:add-missing-indices'] = \OC\Core\Command\Db\AddMissingIndices::class;

	$coreCommandFactories['migrations:status'] = \OC\Core\Command\Db\Migrations\StatusCommand::class;
	$coreCommandFactories['migrations:migrate'] = \OC\Core\Command\Db\Migrations\MigrateCommand::class;
	$coreCommandFactories['migrations:generate'] = \OC\Core\Command\Db\Migrations\GenerateCommand::class;
	$coreCommandFactories['migrations:generate-from-schema'] = \OC\Core\Command\Db\Migrations\GenerateFromSchemaFileCommand::class;
	$coreCommandFactories['migrations:execute'] = \OC\Core\Command\Db\Migrations\ExecuteCommand::class;

	$application->add(new OC\Core\Command\Encryption\Disable(\OC::$server->getConfig()));
	$application->add(new OC\Core\Command\Encryption\Enable(\OC::$server->getConfig(), \OC::$server->getEncryptionManager()));
	$application->add(new OC\Core\Command\Encryption\ListModules(\OC::$server->getEncryptionManager(), \OC::$server->getConfig()));
	$application->add(new OC\Core\Command\Encryption\SetDefaultModule(\OC::$server->getEncryptionManager(), \OC::$server->getConfig()));
	$application->add(new OC\Core\Command\Encryption\Status(\OC::$server->getEncryptionManager()));
	$application->add(new OC\Core\Command\Encryption\EncryptAll(\OC::$server->getEncryptionManager(), \OC::$server->getAppManager(), \OC::$server->getConfig(), new \Symfony\Component\Console\Helper\QuestionHelper()));
	$application->add(new OC\Core\Command\Encryption\DecryptAll(
		\OC::$server->getEncryptionManager(),
		\OC::$server->getAppManager(),
		\OC::$server->getConfig(),
		new \OC\Encryption\DecryptAll(\OC::$server->getEncryptionManager(), \OC::$server->getUserManager(), new \OC\Files\View()),
		new \Symfony\Component\Console\Helper\QuestionHelper())
	);

	$coreCommandFactories['log:manage'] = \OC\Core\Command\Log\Manage::class;
	$coreCommandFactories['log:file'] = \OC\Core\Command\Log\File::class;

	$view = new \OC\Files\View();
	$util = new \OC\Encryption\Util(
		$view,
		\OC::$server->getUserManager(),
		\OC::$server->getGroupManager(),
		\OC::$server->getConfig()
	);
	$application->add(new OC\Core\Command\Encryption\ChangeKeyStorageRoot(
			$view,
			\OC::$server->getUserManager(),
			\OC::$server->getConfig(),
			$util,
			new \Symfony\Component\Console\Helper\QuestionHelper()
		)
	);
	$application->add(new OC\Core\Command\Encryption\ShowKeyStorageRoot($util));

	$coreCommandFactories['maintenance:data-fingerprint'] = \OC\Core\Command\Maintenance\DataFingerprint::class;
	$coreCommandFactories['maintenance:mimetype:update-db'] = \OC\Core\Command\Maintenance\Mimetype\UpdateDB::class;
	$coreCommandFactories['maintenance:mimetype:update-js'] = \OC\Core\Command\Maintenance\Mimetype\UpdateJS::class;
	$coreCommandFactories['maintenance:mode'] = \OC\Core\Command\Maintenance\Mode::class;
	$coreCommandFactories['maintenance:update:htaccess'] = \OC\Core\Command\Maintenance\UpdateHtaccess::class;
	$coreCommandFactories['maintenance:theme:update'] = \OC\Core\Command\Maintenance\UpdateTheme::class;

	$coreCommandFactories['upgrade'] = \OC\Core\Command\Upgrade::class;
	$application->add(new OC\Core\Command\Maintenance\Repair(
		new \OC\Repair([], \OC::$server->getEventDispatcher()),
		\OC::$server->getConfig(),
		\OC::$server->getEventDispatcher(),
		\OC::$server->getAppManager()
	));

	$coreCommandFactories['user:add'] = \OC\Core\Command\User\Add::class;
	$coreCommandFactories['user:delete'] = \OC\Core\Command\User\Delete::class;
	$coreCommandFactories['user:disable'] = \OC\Core\Command\User\Disable::class;
	$coreCommandFactories['user:enable'] = \OC\Core\Command\User\Enable::class;
	$coreCommandFactories['user:lastseen'] = \OC\Core\Command\User\LastSeen::class;
	$coreCommandFactories['user:resetpassword'] = \OC\Core\Command\User\ResetPassword::class;
	$coreCommandFactories['user:setting'] = \OC\Core\Command\User\Setting::class;
	$coreCommandFactories['user:list'] = \OC\Core\Command\User\ListCommand::class;
	$coreCommandFactories['user:info'] = \OC\Core\Command\User\Info::class;

	$coreCommandFactories['group:add'] = \OC\Core\Command\Group\Add::class;
	$coreCommandFactories['group:delete'] = \OC\Core\Command\Group\Delete::class;
	$coreCommandFactories['group:list'] = \OC\Core\Command\Group\ListCommand::class;
	$coreCommandFactories['group:adduser'] = \OC\Core\Command\Group\AddUser::class;
	$coreCommandFactories['group:removeuser'] = \OC\Core\Command\Group\RemoveUser::class;

	$application->add(new OC\Core\Command\Security\ListCertificates(\OC::$server->getCertificateManager(null), \OC::$server->getL10N('core')));
	$application->add(new OC\Core\Command\Security\ImportCertificate(\OC::$server->getCertificateManager(null)));
	$application->add(new OC\Core\Command\Security\RemoveCertificate(\OC::$server->getCertificateManager(null)));
} else {
	$application->add(new OC\Core\Command\Maintenance\Install(\OC::$server->getSystemConfig()));
}
