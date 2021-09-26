<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\App;

use OC\App\AppManager;
use OC\Group\Group;
use OC\User\User;
use OCP\App\AppPathNotFoundException;
use OCP\App\IAppManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IURLGenerator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\TestCase;

/**
 * Class AppManagerTest
 *
 * @package Test\App
 */
class AppManagerTest extends TestCase {
	/**
	 * @return IAppConfig|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getAppConfig() {
		$appConfig = array();
		$config = $this->createMock(IAppConfig::class);

		$config->expects($this->any())
			->method('getValue')
			->will($this->returnCallback(function ($app, $key, $default) use (&$appConfig) {
				return (isset($appConfig[$app]) and isset($appConfig[$app][$key])) ? $appConfig[$app][$key] : $default;
			}));
		$config->expects($this->any())
			->method('setValue')
			->will($this->returnCallback(function ($app, $key, $value) use (&$appConfig) {
				if (!isset($appConfig[$app])) {
					$appConfig[$app] = array();
				}
				$appConfig[$app][$key] = $value;
			}));
		$config->expects($this->any())
			->method('getValues')
			->will($this->returnCallback(function ($app, $key) use (&$appConfig) {
				if ($app) {
					return $appConfig[$app];
				} else {
					$values = array();
					foreach ($appConfig as $appid => $appData) {
						if (isset($appData[$key])) {
							$values[$appid] = $appData[$key];
						}
					}
					return $values;
				}
			}));

		return $config;
	}

	/** @var IUserSession|\PHPUnit_Framework_MockObject_MockObject */
	protected $userSession;

	/** @var IGroupManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $groupManager;

	/** @var IAppConfig|\PHPUnit_Framework_MockObject_MockObject */
	protected $appConfig;

	/** @var ICache|\PHPUnit_Framework_MockObject_MockObject */
	protected $cache;

	/** @var ICacheFactory|\PHPUnit_Framework_MockObject_MockObject */
	protected $cacheFactory;

	/** @var EventDispatcherInterface|\PHPUnit_Framework_MockObject_MockObject */
	protected $eventDispatcher;

	/** @var IAppManager */
	protected $manager;

	protected function setUp() {
		parent::setUp();

		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->appConfig = $this->getAppConfig();
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->cache = $this->createMock(ICache::class);
		$this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
		$this->cacheFactory->expects($this->any())
			->method('create')
			->with('settings')
			->willReturn($this->cache);
		$this->manager = new AppManager($this->userSession, $this->appConfig, $this->groupManager, $this->cacheFactory, $this->eventDispatcher);
	}

	protected function expectClearCache() {
		$this->cache->expects($this->once())
			->method('clear')
			->with('listApps');
	}

	public function testEnableApp() {
		$this->expectClearCache();
		// making sure "files_trashbin" is disabled
		if ($this->manager->isEnabledForUser('files_trashbin')) {
			$this->manager->disableApp('files_trashbin');
		}
		$this->manager->enableApp('files_trashbin');
		$this->assertEquals('yes', $this->appConfig->getValue('files_trashbin', 'enabled', 'no'));
	}

	public function testDisableApp() {
		$this->expectClearCache();
		$this->manager->disableApp('files_trashbin');
		$this->assertEquals('no', $this->appConfig->getValue('files_trashbin', 'enabled', 'no'));
	}

	public function testNotEnableIfNotInstalled() {
		try {
			$this->manager->enableApp('some_random_name_which_i_hope_is_not_an_app');
			$this->assertFalse(true, 'If this line is reached the expected exception is not thrown.');
		} catch (AppPathNotFoundException $e) {
			// Exception is expected
			$this->assertEquals('Could not find path for some_random_name_which_i_hope_is_not_an_app', $e->getMessage());
		}

		$this->assertEquals('no', $this->appConfig->getValue(
			'some_random_name_which_i_hope_is_not_an_app', 'enabled', 'no'
		));
	}

	public function testEnableAppForGroups() {
		$groups = array(
			new Group('group1', array(), null),
			new Group('group2', array(), null)
		);
		$this->expectClearCache();
		$this->manager->enableAppForGroups('test', $groups);
		$this->assertEquals('["group1","group2"]', $this->appConfig->getValue('test', 'enabled', 'no'));
	}

	public function dataEnableAppForGroupsAllowedTypes() {
		return [
			[[]],
			[[
				'types' => [],
			]],
			[[
				'types' => ['nickvergessen'],
			]],
		];
	}

	/**
	 * @dataProvider dataEnableAppForGroupsAllowedTypes
	 *
	 * @param array $appInfo
	 */
	public function testEnableAppForGroupsAllowedTypes(array $appInfo) {
		$groups = array(
			new Group('group1', array(), null),
			new Group('group2', array(), null)
		);
		$this->expectClearCache();

		/** @var AppManager|\PHPUnit_Framework_MockObject_MockObject $manager */
		$manager = $this->getMockBuilder(AppManager::class)
			->setConstructorArgs([
				$this->userSession, $this->appConfig, $this->groupManager, $this->cacheFactory, $this->eventDispatcher
			])
			->setMethods([
				'getAppInfo'
			])
			->getMock();

		$manager->expects($this->once())
			->method('getAppInfo')
			->with('test')
			->willReturn($appInfo);

		$manager->enableAppForGroups('test', $groups);
		$this->assertEquals('["group1","group2"]', $this->appConfig->getValue('test', 'enabled', 'no'));
	}

	public function dataEnableAppForGroupsForbiddenTypes() {
		return [
			['filesystem'],
			['prelogin'],
			['authentication'],
			['logging'],
			['prevent_group_restriction'],
		];
	}

	/**
	 * @dataProvider dataEnableAppForGroupsForbiddenTypes
	 *
	 * @param string $type
	 *
	 * @expectedException \Exception
	 * @expectedExceptionMessage test can't be enabled for groups.
	 */
	public function testEnableAppForGroupsForbiddenTypes($type) {
		$groups = array(
			new Group('group1', array(), null),
			new Group('group2', array(), null)
		);

		/** @var AppManager|\PHPUnit_Framework_MockObject_MockObject $manager */
		$manager = $this->getMockBuilder(AppManager::class)
			->setConstructorArgs([
				$this->userSession, $this->appConfig, $this->groupManager, $this->cacheFactory, $this->eventDispatcher
			])
			->setMethods([
				'getAppInfo'
			])
			->getMock();

		$manager->expects($this->once())
			->method('getAppInfo')
			->with('test')
			->willReturn([
				'types' => [$type],
			]);

		$manager->enableAppForGroups('test', $groups);
	}

	public function testIsInstalledEnabled() {
		$this->appConfig->setValue('test', 'enabled', 'yes');
		$this->assertTrue($this->manager->isInstalled('test'));
	}

	public function testIsInstalledDisabled() {
		$this->appConfig->setValue('test', 'enabled', 'no');
		$this->assertFalse($this->manager->isInstalled('test'));
	}

	public function testIsInstalledEnabledForGroups() {
		$this->appConfig->setValue('test', 'enabled', '["foo"]');
		$this->assertTrue($this->manager->isInstalled('test'));
	}

	private function newUser($uid) {
		$config = $this->createMock(IConfig::class);
		$urlgenerator = $this->createMock(IURLGenerator::class);

		return new User($uid, null, null, $config, $urlgenerator);
	}

	public function testIsEnabledForUserEnabled() {
		$this->appConfig->setValue('test', 'enabled', 'yes');
		$user = $this->newUser('user1');
		$this->assertTrue($this->manager->isEnabledForUser('test', $user));
	}

	public function testIsEnabledForUserDisabled() {
		$this->appConfig->setValue('test', 'enabled', 'no');
		$user = $this->newUser('user1');
		$this->assertFalse($this->manager->isEnabledForUser('test', $user));
	}

	public function testGetAppPath() {
		$this->assertEquals(\OC::$SERVERROOT . '/apps/files', $this->manager->getAppPath('files'));
	}

	public function testGetAppPathFail() {
		$this->expectException(AppPathNotFoundException::class);
		$this->manager->getAppPath('testnotexisting');
	}

	public function testIsEnabledForUserEnabledForGroup() {
		$user = $this->newUser('user1');
		$this->groupManager->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->will($this->returnValue(array('foo', 'bar')));

		$this->appConfig->setValue('test', 'enabled', '["foo"]');
		$this->assertTrue($this->manager->isEnabledForUser('test', $user));
	}

	public function testIsEnabledForUserDisabledForGroup() {
		$user = $this->newUser('user1');
		$this->groupManager->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->will($this->returnValue(array('bar')));

		$this->appConfig->setValue('test', 'enabled', '["foo"]');
		$this->assertFalse($this->manager->isEnabledForUser('test', $user));
	}

	public function testIsEnabledForUserLoggedOut() {
		$this->appConfig->setValue('test', 'enabled', '["foo"]');
		$this->assertFalse($this->manager->isEnabledForUser('test'));
	}

	public function testIsEnabledForUserLoggedIn() {
		$user = $this->newUser('user1');

		$this->userSession->expects($this->once())
			->method('getUser')
			->will($this->returnValue($user));
		$this->groupManager->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->will($this->returnValue(array('foo', 'bar')));

		$this->appConfig->setValue('test', 'enabled', '["foo"]');
		$this->assertTrue($this->manager->isEnabledForUser('test'));
	}

	public function testGetInstalledApps() {
		$this->appConfig->setValue('test1', 'enabled', 'yes');
		$this->appConfig->setValue('test2', 'enabled', 'no');
		$this->appConfig->setValue('test3', 'enabled', '["foo"]');
		$apps = [
			'dav',
			'federatedfilesharing',
			'files',
			'lookup_server_connector',
			'oauth2',
			'provisioning_api',
			'test1',
			'test3',
			'twofactor_backupcodes',
			'workflowengine',
		];
		$this->assertEquals($apps, $this->manager->getInstalledApps());
	}

	public function testGetAppsForUser() {
		$user = $this->newUser('user1');
		$this->groupManager->expects($this->any())
			->method('getUserGroupIds')
			->with($user)
			->will($this->returnValue(array('foo', 'bar')));

		$this->appConfig->setValue('test1', 'enabled', 'yes');
		$this->appConfig->setValue('test2', 'enabled', 'no');
		$this->appConfig->setValue('test3', 'enabled', '["foo"]');
		$this->appConfig->setValue('test4', 'enabled', '["asd"]');
		$enabled = [
			'dav',
			'federatedfilesharing',
			'files',
			'lookup_server_connector',
			'oauth2',
			'provisioning_api',
			'test1',
			'test3',
			'twofactor_backupcodes',
			'workflowengine',
		];
		$this->assertEquals($enabled, $this->manager->getEnabledAppsForUser($user));
	}

	public function testGetAppsNeedingUpgrade() {
		/** @var AppManager|\PHPUnit_Framework_MockObject_MockObject $manager */
		$manager = $this->getMockBuilder(AppManager::class)
			->setConstructorArgs([$this->userSession, $this->appConfig, $this->groupManager, $this->cacheFactory, $this->eventDispatcher])
			->setMethods(['getAppInfo'])
			->getMock();

		$appInfos = [
			'dav' => ['id' => 'dav'],
			'files' => ['id' => 'files'],
			'federatedfilesharing' => ['id' => 'federatedfilesharing'],
			'provisioning_api' => ['id' => 'provisioning_api'],
			'lookup_server_connector' => ['id' => 'lookup_server_connector'],
			'test1' => ['id' => 'test1', 'version' => '1.0.1', 'requiremax' => '9.0.0'],
			'test2' => ['id' => 'test2', 'version' => '1.0.0', 'requiremin' => '8.2.0'],
			'test3' => ['id' => 'test3', 'version' => '1.2.4', 'requiremin' => '9.0.0'],
			'test4' => ['id' => 'test4', 'version' => '3.0.0', 'requiremin' => '8.1.0'],
			'testnoversion' => ['id' => 'testnoversion', 'requiremin' => '8.2.0'],
			'twofactor_backupcodes' => ['id' => 'twofactor_backupcodes'],
			'workflowengine' => ['id' => 'workflowengine'],
			'oauth2' => ['id' => 'oauth2'],
		];

		$manager->expects($this->any())
			->method('getAppInfo')
			->will($this->returnCallback(
				function($appId) use ($appInfos) {
					return $appInfos[$appId];
				}
			));

		$this->appConfig->setValue('test1', 'enabled', 'yes');
		$this->appConfig->setValue('test1', 'installed_version', '1.0.0');
		$this->appConfig->setValue('test2', 'enabled', 'yes');
		$this->appConfig->setValue('test2', 'installed_version', '1.0.0');
		$this->appConfig->setValue('test3', 'enabled', 'yes');
		$this->appConfig->setValue('test3', 'installed_version', '1.0.0');
		$this->appConfig->setValue('test4', 'enabled', 'yes');
		$this->appConfig->setValue('test4', 'installed_version', '2.4.0');

		$apps = $manager->getAppsNeedingUpgrade('8.2.0');

		$this->assertCount(2, $apps);
		$this->assertEquals('test1', $apps[0]['id']);
		$this->assertEquals('test4', $apps[1]['id']);
	}

	public function testGetIncompatibleApps() {
		/** @var AppManager|\PHPUnit_Framework_MockObject_MockObject $manager */
		$manager = $this->getMockBuilder(AppManager::class)
			->setConstructorArgs([$this->userSession, $this->appConfig, $this->groupManager, $this->cacheFactory, $this->eventDispatcher])
			->setMethods(['getAppInfo'])
			->getMock();

		$appInfos = [
			'dav' => ['id' => 'dav'],
			'files' => ['id' => 'files'],
			'federatedfilesharing' => ['id' => 'federatedfilesharing'],
			'provisioning_api' => ['id' => 'provisioning_api'],
			'lookup_server_connector' => ['id' => 'lookup_server_connector'],
			'test1' => ['id' => 'test1', 'version' => '1.0.1', 'requiremax' => '8.0.0'],
			'test2' => ['id' => 'test2', 'version' => '1.0.0', 'requiremin' => '8.2.0'],
			'test3' => ['id' => 'test3', 'version' => '1.2.4', 'requiremin' => '9.0.0'],
			'testnoversion' => ['id' => 'testnoversion', 'requiremin' => '8.2.0'],
			'twofactor_backupcodes' => ['id' => 'twofactor_backupcodes'],
			'workflowengine' => ['id' => 'workflowengine'],
			'oauth2' => ['id' => 'oauth2'],
		];

		$manager->expects($this->any())
			->method('getAppInfo')
			->will($this->returnCallback(
				function($appId) use ($appInfos) {
					return $appInfos[$appId];
				}
			));

		$this->appConfig->setValue('test1', 'enabled', 'yes');
		$this->appConfig->setValue('test2', 'enabled', 'yes');
		$this->appConfig->setValue('test3', 'enabled', 'yes');

		$apps = $manager->getIncompatibleApps('8.2.0');

		$this->assertCount(2, $apps);
		$this->assertEquals('test1', $apps[0]['id']);
		$this->assertEquals('test3', $apps[1]['id']);
	}
}
