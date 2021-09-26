<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace Tests\Settings;

use OC\Accounts\AccountManager;
use OC\Settings\Admin\Sharing;
use OC\Settings\Manager;
use OC\Settings\Mapper;
use OC\Settings\Personal\Security;
use OC\Settings\Section;
use OCP\App\IAppManager;
use OCP\Encryption\IManager;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Lock\ILockingProvider;
use Test\TestCase;

class ManagerTest extends TestCase {
	/** @var Manager|\PHPUnit_Framework_MockObject_MockObject */
	private $manager;
	/** @var ILogger|\PHPUnit_Framework_MockObject_MockObject */
	private $logger;
	/** @var IDBConnection|\PHPUnit_Framework_MockObject_MockObject */
	private $dbConnection;
	/** @var IL10N|\PHPUnit_Framework_MockObject_MockObject */
	private $l10n;
	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $config;
	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	private $encryptionManager;
	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	private $userManager;
	/** @var ILockingProvider|\PHPUnit_Framework_MockObject_MockObject */
	private $lockingProvider;
	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;
	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	private $url;
	/** @var AccountManager|\PHPUnit_Framework_MockObject_MockObject */
	private $accountManager;
	/** @var  IGroupManager|\PHPUnit_Framework_MockObject_MockObject */
	private $groupManager;
	/** @var  IFactory|\PHPUnit_Framework_MockObject_MockObject */
	private $l10nFactory;
	/** @var IAppManager */
	private $appManager;

	public function setUp() {
		parent::setUp();

		$this->logger = $this->createMock(ILogger::class);
		$this->dbConnection = $this->createMock(IDBConnection::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->config = $this->createMock(IConfig::class);
		$this->encryptionManager = $this->createMock(IManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->lockingProvider = $this->createMock(ILockingProvider::class);
		$this->request = $this->createMock(IRequest::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->accountManager = $this->createMock(AccountManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->appManager = $this->createMock(IAppManager::class);

		$this->manager = new Manager(
			$this->logger,
			$this->dbConnection,
			$this->l10n,
			$this->config,
			$this->encryptionManager,
			$this->userManager,
			$this->lockingProvider,
			$this->request,
			$this->url,
			$this->accountManager,
			$this->groupManager,
			$this->l10nFactory,
			$this->appManager
		);
	}

	public function testGetAdminSections() {
		$this->l10n
			->expects($this->any())
			->method('t')
			->will($this->returnArgument(0));

		$this->manager->registerSection('admin', \OCA\WorkflowEngine\Settings\Section::class);

		$this->url->expects($this->exactly(6))
			->method('imagePath')
			->willReturnMap([
				['settings', 'admin.svg', '1'],
				['core', 'actions/share.svg', '2'],
				['core', 'actions/password.svg', '3'],
				['core', 'actions/settings-dark.svg', '4'],
				['settings', 'help.svg', '5'],
			]);

		$this->assertEquals([
			0 => [new Section('server', 'Basic settings', 0, '1')],
			5 => [new Section('sharing', 'Sharing', 0, '2')],
			10 => [new Section('security', 'Security', 0, '3')],
			45 => [new Section('encryption', 'Encryption', 0, '3')],
			55 => [\OC::$server->query(\OCA\WorkflowEngine\Settings\Section::class)],
			98 => [new Section('additional', 'Additional settings', 0, '4')],
			99 => [new Section('tips-tricks', 'Tips & tricks', 0, '5')],
		], $this->manager->getAdminSections());
	}

	public function testGetPersonalSections() {
		$this->l10n
			->expects($this->any())
			->method('t')
			->will($this->returnArgument(0));

		$this->manager->registerSection('personal', \OCA\WorkflowEngine\Settings\Section::class);

		$this->url->expects($this->exactly(3))
			->method('imagePath')
			->willReturnMap([
				['core', 'actions/info.svg', '1'],
				['settings', 'password.svg', '2'],
				['settings', 'change.svg', '3'],
			]);

		$this->assertEquals([
			0 => [new Section('personal-info', 'Personal info', 0, '1')],
			5 => [new Section('security', 'Security', 0, '2')],
			15 => [new Section('sync-clients', 'Sync clients', 0, '3')],
			55 => [\OC::$server->query(\OCA\WorkflowEngine\Settings\Section::class)],
		], $this->manager->getPersonalSections());
	}

	public function testGetAdminSectionsEmptySection() {
		$this->l10n
			->expects($this->any())
			->method('t')
			->will($this->returnArgument(0));

		$this->url->expects($this->exactly(6))
			->method('imagePath')
			->willReturnMap([
				['settings', 'admin.svg', '1'],
				['core', 'actions/share.svg', '2'],
				['core', 'actions/password.svg', '3'],
				['core', 'actions/settings-dark.svg', '4'],
				['settings', 'help.svg', '5'],
			]);

		$this->assertEquals([
			0 => [new Section('server', 'Basic settings', 0, '1')],
			5 => [new Section('sharing', 'Sharing', 0, '2')],
			10 => [new Section('security', 'Security', 0, '3')],
			45 => [new Section('encryption', 'Encryption', 0, '3')],
			98 => [new Section('additional', 'Additional settings', 0, '4')],
			99 => [new Section('tips-tricks', 'Tips & tricks', 0, '5')],
		], $this->manager->getAdminSections());
	}

	public function testGetPersonalSectionsEmptySection() {
		$this->l10n
			->expects($this->any())
			->method('t')
			->will($this->returnArgument(0));

		$this->url->expects($this->exactly(3))
			->method('imagePath')
			->willReturnMap([
				['core', 'actions/info.svg', '1'],
				['settings', 'password.svg', '2'],
				['settings', 'change.svg', '3'],
			]);

		$this->assertArraySubset([
			0 => [new Section('personal-info', 'Personal info', 0, '1')],
			5 => [new Section('security', 'Security', 0, '2')],
			15 => [new Section('sync-clients', 'Sync clients', 0, '3')],
		], $this->manager->getPersonalSections());
	}

	public function testGetAdminSettings() {
		$this->assertEquals([
			0 => [new Sharing($this->config)],
		], $this->manager->getAdminSettings('sharing'));
	}

	public function testGetPersonalSettings() {
		$this->assertEquals([
			10 => [new Security()],
		], $this->manager->getPersonalSettings('security'));
	}
}
