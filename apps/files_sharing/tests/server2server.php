<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

use OCA\Files_Sharing\Tests\TestCase;

/**
 * Class Test_Files_Sharing_Api
 *
 * @group DB
 */
class Test_Files_Sharing_S2S_OCS_API extends TestCase {

	const TEST_FOLDER_NAME = '/folder_share_api_test';

	/**
	 * @var \OCP\IDBConnection
	 */
	private $connection;

	/**
	 * @var \OCA\Files_Sharing\API\Server2Server
	 */
	private $s2s;

	protected function setUp() {
		parent::setUp();

		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);
		\OCP\Share::registerBackend('test', 'Test_Share_Backend');

		$config = $this->getMockBuilder('\OCP\IConfig')
				->disableOriginalConstructor()->getMock();
		$clientService = $this->getMock('\OCP\Http\Client\IClientService');
		$httpHelperMock = $this->getMockBuilder('\OC\HTTPHelper')
				->setConstructorArgs([$config, $clientService])
				->getMock();
		$httpHelperMock->expects($this->any())->method('post')->with($this->anything())->will($this->returnValue(true));

		$this->registerHttpHelper($httpHelperMock);

		$this->s2s = new \OCA\Files_Sharing\API\Server2Server();

		$this->connection = \OC::$server->getDatabaseConnection();
	}

	protected function tearDown() {
		$query = \OCP\DB::prepare('DELETE FROM `*PREFIX*share_external`');
		$query->execute();

		$this->restoreHttpHelper();

		parent::tearDown();
	}

	/**
	 * Register an http helper mock for testing purposes.
	 * @param $httpHelper http helper mock
	 */
	private function registerHttpHelper($httpHelper) {
		$this->oldHttpHelper = \OC::$server->query('HTTPHelper');
		\OC::$server->registerService('HTTPHelper', function ($c) use ($httpHelper) {
			return $httpHelper;
		});
	}

	/**
	 * Restore the original http helper
	 */
	private function restoreHttpHelper() {
		$oldHttpHelper = $this->oldHttpHelper;
		\OC::$server->registerService('HTTPHelper', function ($c) use ($oldHttpHelper) {
			return $oldHttpHelper;
		});
	}

	/**
	 * @medium
	 */
	function testCreateShare() {
		// simulate a post request
		$_POST['remote'] = 'localhost';
		$_POST['token'] = 'token';
		$_POST['name'] = 'name';
		$_POST['owner'] = 'owner';
		$_POST['shareWith'] = self::TEST_FILES_SHARING_API_USER2;
		$_POST['remoteId'] = 1;

		$result = $this->s2s->createShare(null);

		$this->assertTrue($result->succeeded());

		$query = \OCP\DB::prepare('SELECT * FROM `*PREFIX*share_external` WHERE `remote_id` = ?');
		$result = $query->execute(array('1'));
		$data = $result->fetchRow();

		$this->assertSame('localhost', $data['remote']);
		$this->assertSame('token', $data['share_token']);
		$this->assertSame('/name', $data['name']);
		$this->assertSame('owner', $data['owner']);
		$this->assertSame(self::TEST_FILES_SHARING_API_USER2, $data['user']);
		$this->assertSame(1, (int)$data['remote_id']);
		$this->assertSame(0, (int)$data['accepted']);
	}


	function testDeclineShare() {
		$dummy = \OCP\DB::prepare('
			INSERT INTO `*PREFIX*share`
			(`share_type`, `uid_owner`, `item_type`, `item_source`, `item_target`, `file_source`, `file_target`, `permissions`, `stime`, `token`, `share_with`)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			');
		$dummy->execute(array(\OCP\Share::SHARE_TYPE_REMOTE, self::TEST_FILES_SHARING_API_USER1, 'test', '1', '/1', '1', '/test.txt', '1', time(), 'token', 'foo@bar'));

		$verify = \OCP\DB::prepare('SELECT * FROM `*PREFIX*share`');
		$result = $verify->execute();
		$data = $result->fetchAll();
		$this->assertSame(1, count($data));

		$_POST['token'] = 'token';
		$this->s2s->declineShare(array('id' => $data[0]['id']));

		$verify = \OCP\DB::prepare('SELECT * FROM `*PREFIX*share`');
		$result = $verify->execute();
		$data = $result->fetchAll();
		$this->assertEmpty($data);
	}

	function testDeclineShareMultiple() {
		$dummy = \OCP\DB::prepare('
			INSERT INTO `*PREFIX*share`
			(`share_type`, `uid_owner`, `item_type`, `item_source`, `item_target`, `file_source`, `file_target`, `permissions`, `stime`, `token`, `share_with`)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			');
		$dummy->execute(array(\OCP\Share::SHARE_TYPE_REMOTE, self::TEST_FILES_SHARING_API_USER1, 'test', '1', '/1', '1', '/test.txt', '1', time(), 'token1', 'foo@bar'));
		$dummy->execute(array(\OCP\Share::SHARE_TYPE_REMOTE, self::TEST_FILES_SHARING_API_USER1, 'test', '1', '/1', '1', '/test.txt', '1', time(), 'token2', 'bar@bar'));

		$verify = \OCP\DB::prepare('SELECT * FROM `*PREFIX*share`');
		$result = $verify->execute();
		$data = $result->fetchAll();
		$this->assertCount(2, $data);

		$_POST['token'] = 'token1';
		$this->s2s->declineShare(array('id' => $data[0]['id']));

		$verify = \OCP\DB::prepare('SELECT * FROM `*PREFIX*share`');
		$result = $verify->execute();
		$data = $result->fetchAll();
		$this->assertCount(1, $data);
		$this->assertEquals('bar@bar', $data[0]['share_with']);

		$_POST['token'] = 'token2';
		$this->s2s->declineShare(array('id' => $data[0]['id']));

		$verify = \OCP\DB::prepare('SELECT * FROM `*PREFIX*share`');
		$result = $verify->execute();
		$data = $result->fetchAll();
		$this->assertEmpty($data);
	}

	/**
	 * @dataProvider dataTestDeleteUser
	 */
	function testDeleteUser($toDelete, $expected, $remainingUsers) {
		$this->createDummyS2SShares();

		$discoveryManager = new \OCA\FederatedFileSharing\DiscoveryManager(
			\OC::$server->getMemCacheFactory(),
			\OC::$server->getHTTPClientService()
		);
		$manager = new OCA\Files_Sharing\External\Manager(
			\OC::$server->getDatabaseConnection(),
			\OC\Files\Filesystem::getMountManager(),
			\OC\Files\Filesystem::getLoader(),
			\OC::$server->getHTTPHelper(),
			\OC::$server->getNotificationManager(),
			$discoveryManager,
			$toDelete
		);

		$manager->removeUserShares($toDelete);

		$query = $this->connection->prepare('SELECT `user` FROM `*PREFIX*share_external`');
		$query->execute();
		$result = $query->fetchAll();

		foreach ($result as $r) {
			$remainingShares[$r['user']] = isset($remainingShares[$r['user']]) ? $remainingShares[$r['user']] + 1 : 1;
		}

		$this->assertSame($remainingUsers, count($remainingShares));

		foreach ($expected as $key => $value) {
			if ($key === $toDelete) {
				$this->assertArrayNotHasKey($key, $remainingShares);
			} else {
				$this->assertSame($value, $remainingShares[$key]);
			}
		}

	}

	function dataTestDeleteUser() {
		return array(
			array('user1', array('user1' => 0, 'user2' => 3, 'user3' => 3), 2),
			array('user2', array('user1' => 4, 'user2' => 0, 'user3' => 3), 2),
			array('user3', array('user1' => 4, 'user2' => 3, 'user3' => 0), 2),
			array('user4', array('user1' => 4, 'user2' => 3, 'user3' => 3), 3),
		);
	}

	private function createDummyS2SShares() {
		$query = $this->connection->prepare('
			INSERT INTO `*PREFIX*share_external`
			(`remote`, `share_token`, `password`, `name`, `owner`, `user`, `mountpoint`, `mountpoint_hash`, `remote_id`, `accepted`)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			');

		$users = array('user1', 'user2', 'user3');

		for ($i = 0; $i < 10; $i++) {
			$user = $users[$i%3];
			$query->execute(array('remote', 'token', 'password', 'name', 'owner', $user, 'mount point', $i, $i, 0));
		}

		$query = $this->connection->prepare('SELECT `id` FROM `*PREFIX*share_external`');
		$query->execute();
		$dummyEntries = $query->fetchAll();

		$this->assertSame(10, count($dummyEntries));
	}

}
