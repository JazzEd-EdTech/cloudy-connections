<?php
/**
* ownCloud
*
* @author Michael Gapczynski
* @copyright 2012 Michael Gapczynski mtgap@owncloud.com
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the License, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Affero General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace Test\Share;
use OC\Share\Share;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserManager;

/**
 * Class Test_Share
 *
 * @group DB
 */
class ShareTest extends \Test\TestCase {

	protected $itemType;

	/** @var IUser */
	protected $user1;
	/** @var IUser */
	protected $user2;
	/** @var IUser */
	protected $user3;
	/** @var IUser */
	protected $user4;
	/** @var IUser */
	protected $user5;
	/** @var IUser */
	protected $user6;
	/** @var IUser */
	protected $groupAndUser_user;

	/** @var IGroup */
	protected $group1;
	/** @var IGroup */
	protected $group2;
	/** @var IGroup */
	protected $groupAndUser_group;

	protected $resharing;
	protected $dateInFuture;
	protected $dateInPast;

	/** @var IGroupManager */
	protected $groupManager;
	/** @var IUserManager */
	protected $userManager;

	protected function setUp() {
		parent::setUp();

		$this->groupManager = \OC::$server->getGroupManager();
		$this->userManager = \OC::$server->getUserManager();

		$this->userManager->clearBackends();
		$this->userManager->registerBackend(new \Test\Util\User\Dummy());

		$this->user1 = $this->userManager->createUser($this->getUniqueID('user1_'), 'pass');
		$this->user2 = $this->userManager->createUser($this->getUniqueID('user2_'), 'pass');
		$this->user3 = $this->userManager->createUser($this->getUniqueID('user3_'), 'pass');
		$this->user4 = $this->userManager->createUser($this->getUniqueID('user4_'), 'pass');
		$this->user5 = $this->userManager->createUser($this->getUniqueID('user5_'), 'pass');
		$this->user6 = $this->userManager->createUser($this->getUniqueID('user6_'), 'pass');
		$groupAndUserId = $this->getUniqueID('groupAndUser_');
		$this->groupAndUser_user = $this->userManager->createUser($groupAndUserId, 'pass');
		\OC_User::setUserId($this->user1->getUID());

		$this->groupManager->clearBackends();
		$this->groupManager->addBackend(new \Test\Util\Group\Dummy());
		$this->group1 = $this->groupManager->createGroup($this->getUniqueID('group1_'));
		$this->group2 = $this->groupManager->createGroup($this->getUniqueID('group2_'));
		$this->groupAndUser_group = $this->groupManager->createGroup($groupAndUserId);

		$this->group1->addUser($this->user1);
		$this->group1->addUser($this->user2);
		$this->group1->addUser($this->user3);
		$this->group2->addUser($this->user2);
		$this->group2->addUser($this->user4);
		$this->groupAndUser_group->addUser($this->user2);
		$this->groupAndUser_group->addUser($this->user3);

		\OC\Share\Share::registerBackend('test', 'Test\Share\Backend');
		\OC_Hook::clear('OCP\\Share');
		\OC::registerShareHooks();
		$this->resharing = \OC::$server->getConfig()->getAppValue('core', 'shareapi_allow_resharing', 'yes');
		\OC::$server->getConfig()->setAppValue('core', 'shareapi_allow_resharing', 'yes');

		// 20 Minutes in the past, 20 minutes in the future.
		$now = time();
		$dateFormat = 'Y-m-d H:i:s';
		$this->dateInPast = date($dateFormat, $now - 20 * 60);
		$this->dateInFuture = date($dateFormat, $now + 20 * 60);
	}

	protected function tearDown() {
		$query = \OC_DB::prepare('DELETE FROM `*PREFIX*share` WHERE `item_type` = ?');
		$query->execute(array('test'));
		\OC::$server->getConfig()->setAppValue('core', 'shareapi_allow_resharing', $this->resharing);

		$this->user1->delete();
		$this->user2->delete();
		$this->user3->delete();
		$this->user4->delete();
		$this->user5->delete();
		$this->user6->delete();
		$this->groupAndUser_user->delete();

		$this->group1->delete();
		$this->group2->delete();
		$this->groupAndUser_group->delete();

		$this->logout();
		parent::tearDown();
	}

	/**
	 * @param boolean|string $token
	 * @return array
	 */
	protected function getShareByValidToken($token) {
		$row = \OCP\Share::getShareByToken($token);
		$this->assertInternalType(
			'array',
			$row,
			"Failed asserting that a share for token $token exists."
		);
		return $row;
	}

	public function testGetItemSharedWithUser() {
		\OC_User::setUserId($this->user1->getUID());

		//add dummy values to the share table
		$query = \OC_DB::prepare('INSERT INTO `*PREFIX*share` ('
			.' `item_type`, `item_source`, `item_target`, `share_type`,'
			.' `share_with`, `uid_owner`) VALUES (?,?,?,?,?,?)');
		$args = array('test', 99, 'target1', \OCP\Share::SHARE_TYPE_USER, $this->user2->getUID(), $this->user1->getUID());
		$query->execute($args);
		$args = array('test', 99, 'target2', \OCP\Share::SHARE_TYPE_USER, $this->user4->getUID(), $this->user1->getUID());
		$query->execute($args);
		$args = array('test', 99, 'target3', \OCP\Share::SHARE_TYPE_USER, $this->user3->getUID(), $this->user2->getUID());
		$query->execute($args);
		$args = array('test', 99, 'target4', \OCP\Share::SHARE_TYPE_USER, $this->user3->getUID(), $this->user4->getUID());
		$query->execute($args);
		$args = array('test', 99, 'target4', \OCP\Share::SHARE_TYPE_USER, $this->user6->getUID(), $this->user4->getUID());
		$query->execute($args);


		$result1 = \OCP\Share::getItemSharedWithUser('test', 99, $this->user2->getUID(), $this->user1->getUID());
		$this->assertSame(1, count($result1));
		$this->verifyResult($result1, array('target1'));

		$result2 = \OCP\Share::getItemSharedWithUser('test', 99, null, $this->user1->getUID());
		$this->assertSame(2, count($result2));
		$this->verifyResult($result2, array('target1', 'target2'));

		$result3 = \OCP\Share::getItemSharedWithUser('test', 99, $this->user3->getUID());
		$this->assertSame(2, count($result3));
		$this->verifyResult($result3, array('target3', 'target4'));

		$result4 = \OCP\Share::getItemSharedWithUser('test', 99, null, null);
		$this->assertSame(5, count($result4)); // 5 because target4 appears twice
		$this->verifyResult($result4, array('target1', 'target2', 'target3', 'target4'));

		$result6 = \OCP\Share::getItemSharedWithUser('test', 99, $this->user6->getUID(), null);
		$this->assertSame(1, count($result6));
		$this->verifyResult($result6, array('target4'));
	}

	public function testGetItemSharedWithUserFromGroupShare() {
		\OC_User::setUserId($this->user1->getUID());

		//add dummy values to the share table
		$query = \OC_DB::prepare('INSERT INTO `*PREFIX*share` ('
			.' `item_type`, `item_source`, `item_target`, `share_type`,'
			.' `share_with`, `uid_owner`) VALUES (?,?,?,?,?,?)');
		$args = array('test', 99, 'target1', \OCP\Share::SHARE_TYPE_GROUP, $this->group1->getGID(), $this->user1->getUID());
		$query->execute($args);
		$args = array('test', 99, 'target2', \OCP\Share::SHARE_TYPE_GROUP, $this->group2->getGID(), $this->user1->getUID());
		$query->execute($args);
		$args = array('test', 99, 'target3', \OCP\Share::SHARE_TYPE_GROUP, $this->group1->getGID(), $this->user2->getUID());
		$query->execute($args);
		$args = array('test', 99, 'target4', \OCP\Share::SHARE_TYPE_GROUP, $this->group1->getGID(), $this->user4->getUID());
		$query->execute($args);

		// user2 is in group1 and group2
		$result1 = \OCP\Share::getItemSharedWithUser('test', 99, $this->user2->getUID(), $this->user1->getUID());
		$this->assertSame(2, count($result1));
		$this->verifyResult($result1, array('target1', 'target2'));

		$result2 = \OCP\Share::getItemSharedWithUser('test', 99, null, $this->user1->getUID());
		$this->assertSame(2, count($result2));
		$this->verifyResult($result2, array('target1', 'target2'));

		// user3 is in group1 and group2
		$result3 = \OCP\Share::getItemSharedWithUser('test', 99, $this->user3->getUID());
		$this->assertSame(3, count($result3));
		$this->verifyResult($result3, array('target1', 'target3', 'target4'));

		$result4 = \OCP\Share::getItemSharedWithUser('test', 99, null, null);
		$this->assertSame(4, count($result4));
		$this->verifyResult($result4, array('target1', 'target2', 'target3', 'target4'));

		$result6 = \OCP\Share::getItemSharedWithUser('test', 99, $this->user6->getUID(), null);
		$this->assertSame(0, count($result6));
	}

	public function verifyResult($result, $expected) {
		foreach ($result as $r) {
			if (in_array($r['item_target'], $expected)) {
				$key = array_search($r['item_target'], $expected);
				unset($expected[$key]);
			}
		}
		$this->assertEmpty($expected, 'did not found all expected values');
	}

	/**
	 * @dataProvider checkPasswordProtectedShareDataProvider
	 * @param $expected
	 * @param $item
	 */
	public function testCheckPasswordProtectedShare($expected, $item) {
		\OC::$server->getSession()->set('public_link_authenticated', '100');
		$result = \OC\Share\Share::checkPasswordProtectedShare($item);
		$this->assertEquals($expected, $result);
	}

	function checkPasswordProtectedShareDataProvider() {
		return array(
			array(true, array()),
			array(true, array('share_with' => null)),
			array(true, array('share_with' => '')),
			array(true, array('share_with' => '1234567890', 'share_type' => '1')),
			array(true, array('share_with' => '1234567890', 'share_type' => 1)),
			array(true, array('share_with' => '1234567890', 'share_type' => '3', 'id' => '100')),
			array(true, array('share_with' => '1234567890', 'share_type' => 3, 'id' => '100')),
			array(true, array('share_with' => '1234567890', 'share_type' => '3', 'id' => 100)),
			array(true, array('share_with' => '1234567890', 'share_type' => 3, 'id' => 100)),
			array(false, array('share_with' => '1234567890', 'share_type' => '3', 'id' => '101')),
			array(false, array('share_with' => '1234567890', 'share_type' => 3, 'id' => '101')),
			array(false, array('share_with' => '1234567890', 'share_type' => '3', 'id' => 101)),
			array(false, array('share_with' => '1234567890', 'share_type' => 3, 'id' => 101)),
		);
	}

	/**
	 * @dataProvider urls
	 * @param string $url
	 * @param string $expectedResult
	 */
	function testRemoveProtocolFromUrl($url, $expectedResult) {
		$share = new \OC\Share\Share();
		$result = self::invokePrivate($share, 'removeProtocolFromUrl', array($url));
		$this->assertSame($expectedResult, $result);
	}

	function urls() {
		return array(
			array('http://owncloud.org', 'owncloud.org'),
			array('https://owncloud.org', 'owncloud.org'),
			array('owncloud.org', 'owncloud.org'),
		);
	}

	/**
	 * @dataProvider dataProviderTestGroupItems
	 * @param array $ungrouped
	 * @param array $grouped
	 */
	function testGroupItems($ungrouped, $grouped) {

		$result = DummyShareClass::groupItemsTest($ungrouped);

		$this->compareArrays($grouped, $result);

	}

	function compareArrays($result, $expectedResult) {
		foreach ($expectedResult as $key => $value) {
			if (is_array($value)) {
				$this->compareArrays($result[$key], $value);
			} else {
				$this->assertSame($value, $result[$key]);
			}
		}
	}

	function dataProviderTestGroupItems() {
		return array(
			// one array with one share
			array(
				array( // input
					array('item_source' => 1, 'permissions' => \OCP\Constants::PERMISSION_ALL, 'item_target' => 't1')),
				array( // expected result
					array('item_source' => 1, 'permissions' => \OCP\Constants::PERMISSION_ALL, 'item_target' => 't1'))),
			// two shares both point to the same source
			array(
				array( // input
					array('item_source' => 1, 'permissions' => \OCP\Constants::PERMISSION_READ, 'item_target' => 't1'),
					array('item_source' => 1, 'permissions' => \OCP\Constants::PERMISSION_UPDATE, 'item_target' => 't1'),
					),
				array( // expected result
					array('item_source' => 1, 'permissions' => \OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_UPDATE, 'item_target' => 't1',
						'grouped' => array(
							array('item_source' => 1, 'permissions' => \OCP\Constants::PERMISSION_READ, 'item_target' => 't1'),
							array('item_source' => 1, 'permissions' => \OCP\Constants::PERMISSION_UPDATE, 'item_target' => 't1'),
							)
						),
					)
				),
			// two shares both point to the same source but with different targets
			array(
				array( // input
					array('item_source' => 1, 'permissions' => \OCP\Constants::PERMISSION_READ, 'item_target' => 't1'),
					array('item_source' => 1, 'permissions' => \OCP\Constants::PERMISSION_UPDATE, 'item_target' => 't2'),
					),
				array( // expected result
					array('item_source' => 1, 'permissions' => \OCP\Constants::PERMISSION_READ, 'item_target' => 't1'),
					array('item_source' => 1, 'permissions' => \OCP\Constants::PERMISSION_UPDATE, 'item_target' => 't2'),
					)
				),
			// three shares two point to the same source
			array(
				array( // input
					array('item_source' => 1, 'permissions' => \OCP\Constants::PERMISSION_READ, 'item_target' => 't1'),
					array('item_source' => 2, 'permissions' => \OCP\Constants::PERMISSION_CREATE, 'item_target' => 't2'),
					array('item_source' => 1, 'permissions' => \OCP\Constants::PERMISSION_UPDATE, 'item_target' => 't1'),
					),
				array( // expected result
					array('item_source' => 1, 'permissions' => \OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_UPDATE, 'item_target' => 't1',
						'grouped' => array(
							array('item_source' => 1, 'permissions' => \OCP\Constants::PERMISSION_READ, 'item_target' => 't1'),
							array('item_source' => 1, 'permissions' => \OCP\Constants::PERMISSION_UPDATE, 'item_target' => 't1'),
							)
						),
					array('item_source' => 2, 'permissions' => \OCP\Constants::PERMISSION_CREATE, 'item_target' => 't2'),
					)
				),
		);
	}
}

class DummyShareClass extends \OC\Share\Share {
	public static function groupItemsTest($items) {
		return parent::groupItems($items, 'test');
	}
}

class DummyHookListener {
	static $shareType = null;

	public static function listen($params) {
		self::$shareType = $params['shareType'];
	}
}
