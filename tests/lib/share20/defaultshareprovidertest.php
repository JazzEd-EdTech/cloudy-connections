<?php
/**
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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
namespace Test\Share20;

use OC\Share20\Exception\ProviderException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\Files\IRootFolder;
use OC\Share20\DefaultShareProvider;

/**
 * Class DefaultShareProviderTest
 *
 * @package Test\Share20
 * @group DB
 */
class DefaultShareProviderTest extends \Test\TestCase {

	/** @var IDBConnection */
	protected $dbConn;

	/** @var IUserManager | \PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;

	/** @var IGroupManager | \PHPUnit_Framework_MockObject_MockObject */
	protected $groupManager;

	/** @var IRootFolder | \PHPUnit_Framework_MockObject_MockObject */
	protected $rootFolder;

	/** @var DefaultShareProvider */
	protected $provider;

	public function setUp() {
		$this->dbConn = \OC::$server->getDatabaseConnection();
		$this->userManager = $this->getMock('OCP\IUserManager');
		$this->groupManager = $this->getMock('OCP\IGroupManager');
		$this->rootFolder = $this->getMock('OCP\Files\IRootFolder');

		//Empty share table
		$this->dbConn->getQueryBuilder()->delete('share')->execute();

		$this->provider = new DefaultShareProvider(
			$this->dbConn,
			$this->userManager,
			$this->groupManager,
			$this->rootFolder
		);
	}

	public function tearDown() {
		$this->dbConn->getQueryBuilder()->delete('share')->execute();
	}

	/**
	 * @param int $shareType
	 * @param string $sharedWith
	 * @param string $sharedBy
	 * @param string $shareOwner
	 * @param string $itemType
	 * @param int $fileSource
	 * @param string $fileTarget
	 * @param int $permissions
	 * @param $token
	 * @param $expiration
	 * @return int
	 */
	private function addShareToDB($shareType, $sharedWith, $sharedBy, $shareOwner,
			$itemType, $fileSource, $fileTarget, $permissions, $token, $expiration,
			$parent = null) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share');

		if ($shareType) $qb->setValue('share_type', $qb->expr()->literal($shareType));
		if ($sharedWith) $qb->setValue('share_with', $qb->expr()->literal($sharedWith));
		if ($sharedBy) $qb->setValue('uid_initiator', $qb->expr()->literal($sharedBy));
		if ($shareOwner) $qb->setValue('uid_owner', $qb->expr()->literal($shareOwner));
		if ($itemType) $qb->setValue('item_type', $qb->expr()->literal($itemType));
		if ($fileSource) $qb->setValue('file_source', $qb->expr()->literal($fileSource));
		if ($fileTarget) $qb->setValue('file_target', $qb->expr()->literal($fileTarget));
		if ($permissions) $qb->setValue('permissions', $qb->expr()->literal($permissions));
		if ($token) $qb->setValue('token', $qb->expr()->literal($token));
		if ($expiration) $qb->setValue('expiration', $qb->createNamedParameter($expiration, IQueryBuilder::PARAM_DATE));
		if ($parent) $qb->setValue('parent', $qb->expr()->literal($parent));

		$this->assertEquals(1, $qb->execute());
		return$qb->getLastInsertId();
	}



	/**
	 * @expectedException \OCP\Share\Exceptions\ShareNotFound
	 */
	public function testGetShareByIdNotExist() {
		$this->provider->getShareById(1);
	}

	public function testGetShareByIdUserShare() {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->insert('share')
			->values([
				'share_type'  => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_USER),
				'share_with'  => $qb->expr()->literal('sharedWith'),
				'uid_owner'   => $qb->expr()->literal('sharedBy'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$qb->execute();

		$id = $qb->getLastInsertId();

		$sharedWith = $this->getMock('OCP\IUser');
		$sharedBy = $this->getMock('OCP\IUser');
		$sharedBy->method('getUID')->willReturn('sharedBy');
		$shareOwner = $this->getMock('OCP\IUser');
		$shareOwner->method('getUID')->willReturn('shareOwner');

		$sharedByPath = $this->getMock('\OCP\Files\File');
		$ownerPath = $this->getMock('\OCP\Files\File');

		$sharedByPath->method('getOwner')->willReturn($shareOwner);

		$sharedByFolder = $this->getMock('\OCP\Files\Folder');
		$sharedByFolder->method('getById')->with(42)->willReturn([$sharedByPath]);

		$shareOwnerFolder = $this->getMock('\OCP\Files\Folder');
		$shareOwnerFolder->method('getById')->with(42)->willReturn([$ownerPath]);

		$this->rootFolder
			->method('getUserFolder')
			->will($this->returnValueMap([
				['sharedBy', $sharedByFolder],
				['shareOwner', $shareOwnerFolder],
			]));

		$share = $this->provider->getShareById($id);

		$this->assertEquals($id, $share->getId());
		$this->assertEquals(\OCP\Share::SHARE_TYPE_USER, $share->getShareType());
		$this->assertEquals('sharedWith', $share->getSharedWith());
		$this->assertEquals('sharedBy', $share->getSharedBy());
		$this->assertEquals('shareOwner', $share->getShareOwner());
		$this->assertEquals($ownerPath, $share->getNode());
		$this->assertEquals(13, $share->getPermissions());
		$this->assertEquals(null, $share->getToken());
		$this->assertEquals(null, $share->getExpirationDate());
		$this->assertEquals('myTarget', $share->getTarget());
	}

	public function testGetShareByIdLazy() {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->insert('share')
			->values([
				'share_type'  => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_USER),
				'share_with'  => $qb->expr()->literal('sharedWith'),
				'uid_owner'   => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$qb->execute();

		$id = $qb->getLastInsertId();

		$this->rootFolder->expects($this->never())->method('getUserFolder');

		$share = $this->provider->getShareById($id);

		// We do not fetch the node so the rootfolder is never called.

		$this->assertEquals($id, $share->getId());
		$this->assertEquals(\OCP\Share::SHARE_TYPE_USER, $share->getShareType());
		$this->assertEquals('sharedWith', $share->getSharedWith());
		$this->assertEquals('sharedBy', $share->getSharedBy());
		$this->assertEquals('shareOwner', $share->getShareOwner());
		$this->assertEquals(13, $share->getPermissions());
		$this->assertEquals(null, $share->getToken());
		$this->assertEquals(null, $share->getExpirationDate());
		$this->assertEquals('myTarget', $share->getTarget());
	}

	public function testGetShareByIdLazy2() {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->insert('share')
			->values([
				'share_type'  => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_USER),
				'share_with'  => $qb->expr()->literal('sharedWith'),
				'uid_owner'   => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$qb->execute();

		$id = $qb->getLastInsertId();

		$ownerPath = $this->getMock('\OCP\Files\File');

		$shareOwnerFolder = $this->getMock('\OCP\Files\Folder');
		$shareOwnerFolder->method('getById')->with(42)->willReturn([$ownerPath]);

		$this->rootFolder
			->method('getUserFolder')
			->with('shareOwner')
			->willReturn($shareOwnerFolder);

		$share = $this->provider->getShareById($id);

		// We fetch the node so the root folder is eventually called

		$this->assertEquals($id, $share->getId());
		$this->assertEquals(\OCP\Share::SHARE_TYPE_USER, $share->getShareType());
		$this->assertEquals('sharedWith', $share->getSharedWith());
		$this->assertEquals('sharedBy', $share->getSharedBy());
		$this->assertEquals('shareOwner', $share->getShareOwner());
		$this->assertEquals($ownerPath, $share->getNode());
		$this->assertEquals(13, $share->getPermissions());
		$this->assertEquals(null, $share->getToken());
		$this->assertEquals(null, $share->getExpirationDate());
		$this->assertEquals('myTarget', $share->getTarget());
	}

	public function testGetShareByIdGroupShare() {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_GROUP),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('sharedBy'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());

		// Get the id
		$id = $qb->getLastInsertId();

		$sharedWith = $this->getMock('OCP\IGroup');
		$sharedBy = $this->getMock('OCP\IUser');
		$sharedBy->method('getUID')->willReturn('sharedBy');
		$shareOwner = $this->getMock('OCP\IUser');
		$shareOwner->method('getUID')->willReturn('shareOwner');

		$sharedByPath = $this->getMock('\OCP\Files\Folder');
		$ownerPath = $this->getMock('\OCP\Files\Folder');

		$sharedByPath->method('getOwner')->willReturn($shareOwner);

		$sharedByFolder = $this->getMock('\OCP\Files\Folder');
		$sharedByFolder->method('getById')->with(42)->willReturn([$sharedByPath]);

		$shareOwnerFolder = $this->getMock('\OCP\Files\Folder');
		$shareOwnerFolder->method('getById')->with(42)->willReturn([$ownerPath]);

		$this->rootFolder
				->method('getUserFolder')
				->will($this->returnValueMap([
						['sharedBy', $sharedByFolder],
						['shareOwner', $shareOwnerFolder],
				]));

		$share = $this->provider->getShareById($id);

		$this->assertEquals($id, $share->getId());
		$this->assertEquals(\OCP\Share::SHARE_TYPE_GROUP, $share->getShareType());
		$this->assertEquals('sharedWith', $share->getSharedWith());
		$this->assertEquals('sharedBy', $share->getSharedBy());
		$this->assertEquals('shareOwner', $share->getShareOwner());
		$this->assertEquals($ownerPath, $share->getNode());
		$this->assertEquals(13, $share->getPermissions());
		$this->assertEquals(null, $share->getToken());
		$this->assertEquals(null, $share->getExpirationDate());
		$this->assertEquals('myTarget', $share->getTarget());
	}

	public function testGetShareByIdUserGroupShare() {
		$id = $this->addShareToDB(\OCP\Share::SHARE_TYPE_GROUP, 'group0', 'user0', 'user0', 'file', 42, 'myTarget', 31, null, null);
		$this->addShareToDB(2, 'user1', 'user0', 'user0', 'file', 42, 'userTarget', 0, null, null, $id);

		$user0 = $this->getMock('OCP\IUser');
		$user0->method('getUID')->willReturn('user0');
		$user1 = $this->getMock('OCP\IUser');
		$user1->method('getUID')->willReturn('user1');

		$group0 = $this->getMock('OCP\IGroup');
		$group0->method('inGroup')->with($user1)->willReturn(true);

		$node = $this->getMock('\OCP\Files\Folder');
		$node->method('getId')->willReturn(42);

		$this->rootFolder->method('getUserFolder')->with('user0')->will($this->returnSelf());
		$this->rootFolder->method('getById')->willReturn([$node]);

		$this->userManager->method('get')->will($this->returnValueMap([
			['user0', $user0],
			['user1', $user1],
		]));
		$this->groupManager->method('get')->with('group0')->willReturn($group0);

		$share = $this->provider->getShareById($id, 'user1');

		$this->assertEquals($id, $share->getId());
		$this->assertEquals(\OCP\Share::SHARE_TYPE_GROUP, $share->getShareType());
		$this->assertSame('group0', $share->getSharedWith());
		$this->assertSame('user0', $share->getSharedBy());
		$this->assertSame('user0', $share->getShareOwner());
		$this->assertSame($node, $share->getNode());
		$this->assertEquals(0, $share->getPermissions());
		$this->assertEquals(null, $share->getToken());
		$this->assertEquals(null, $share->getExpirationDate());
		$this->assertEquals('userTarget', $share->getTarget());
	}

	public function testGetShareByIdLinkShare() {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_LINK),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('sharedBy'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
				'token' => $qb->expr()->literal('token'),
				'expiration' => $qb->expr()->literal('2000-01-02 00:00:00'),
			]);
		$this->assertEquals(1, $qb->execute());

		$id = $qb->getLastInsertId();

		$sharedBy = $this->getMock('OCP\IUser');
		$sharedBy->method('getUID')->willReturn('sharedBy');
		$shareOwner = $this->getMock('OCP\IUser');
		$shareOwner->method('getUID')->willReturn('shareOwner');

		$sharedByPath = $this->getMock('\OCP\Files\Folder');
		$ownerPath = $this->getMock('\OCP\Files\Folder');

		$sharedByPath->method('getOwner')->willReturn($shareOwner);

		$sharedByFolder = $this->getMock('\OCP\Files\Folder');
		$sharedByFolder->method('getById')->with(42)->willReturn([$sharedByPath]);

		$shareOwnerFolder = $this->getMock('\OCP\Files\Folder');
		$shareOwnerFolder->method('getById')->with(42)->willReturn([$ownerPath]);

		$this->rootFolder
				->method('getUserFolder')
				->will($this->returnValueMap([
						['sharedBy', $sharedByFolder],
						['shareOwner', $shareOwnerFolder],
				]));

		$this->userManager
			->method('get')
			->will($this->returnValueMap([
				['sharedBy', $sharedBy],
				['shareOwner', $shareOwner],
			]));

		$share = $this->provider->getShareById($id);

		$this->assertEquals($id, $share->getId());
		$this->assertEquals(\OCP\Share::SHARE_TYPE_LINK, $share->getShareType());
		$this->assertEquals('sharedWith', $share->getPassword());
		$this->assertEquals('sharedBy', $share->getSharedBy());
		$this->assertEquals('shareOwner', $share->getShareOwner());
		$this->assertEquals($ownerPath, $share->getNode());
		$this->assertEquals(13, $share->getPermissions());
		$this->assertEquals('token', $share->getToken());
		$this->assertEquals(\DateTime::createFromFormat('Y-m-d H:i:s', '2000-01-02 00:00:00'), $share->getExpirationDate());
		$this->assertEquals('myTarget', $share->getTarget());
	}

	public function testDeleteSingleShare() {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('sharedBy'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());

		$id = $qb->getLastInsertId();

		$share = $this->getMock('OCP\Share\IShare');
		$share->method('getId')->willReturn($id);

		$provider = $this->getMockBuilder('OC\Share20\DefaultShareProvider')
			->setConstructorArgs([
				$this->dbConn,
				$this->userManager,
				$this->groupManager,
				$this->rootFolder,
			])
			->setMethods(['getShareById'])
			->getMock();

		$provider->delete($share);

		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share');

		$cursor = $qb->execute();
		$result = $cursor->fetchAll();
		$cursor->closeCursor();

		$this->assertEmpty($result);
	}

	public function testDeleteSingleShareLazy() {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());

		$id = $qb->getLastInsertId();

		$this->rootFolder->expects($this->never())->method($this->anything());

		$share = $this->provider->getShareById($id);
		$this->provider->delete($share);

		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share');

		$cursor = $qb->execute();
		$result = $cursor->fetchAll();
		$cursor->closeCursor();

		$this->assertEmpty($result);
	}

	public function testDeleteGroupShareWithUserGroupShares() {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_GROUP),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('sharedBy'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());
		$id = $qb->getLastInsertId();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(2),
				'share_with' => $qb->expr()->literal('sharedWithUser'),
				'uid_owner' => $qb->expr()->literal('sharedBy'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
				'parent'      => $qb->expr()->literal($id),
			]);
		$this->assertEquals(1, $qb->execute());

		$share = $this->getMock('OCP\Share\IShare');
		$share->method('getId')->willReturn($id);
		$share->method('getShareType')->willReturn(\OCP\Share::SHARE_TYPE_GROUP);

		$provider = $this->getMockBuilder('OC\Share20\DefaultShareProvider')
			->setConstructorArgs([
				$this->dbConn,
				$this->userManager,
				$this->groupManager,
				$this->rootFolder,
			])
			->setMethods(['getShareById'])
			->getMock();

		$provider->delete($share);

		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share');

		$cursor = $qb->execute();
		$result = $cursor->fetchAll();
		$cursor->closeCursor();

		$this->assertEmpty($result);
	}

	public function testGetChildren() {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type'  => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_USER),
				'share_with'  => $qb->expr()->literal('sharedWith'),
				'uid_owner'   => $qb->expr()->literal('sharedBy'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$qb->execute();

		// Get the id
		$qb = $this->dbConn->getQueryBuilder();
		$cursor = $qb->select('id')
			->from('share')
			->setMaxResults(1)
			->orderBy('id', 'DESC')
			->execute();
		$id = $cursor->fetch();
		$id = $id['id'];
		$cursor->closeCursor();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type'  => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_USER),
				'share_with'  => $qb->expr()->literal('user1'),
				'uid_owner'   => $qb->expr()->literal('user2'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(1),
				'file_target' => $qb->expr()->literal('myTarget1'),
				'permissions' => $qb->expr()->literal(2),
				'parent'      => $qb->expr()->literal($id),
			]);
		$qb->execute();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type'  => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_GROUP),
				'share_with'  => $qb->expr()->literal('group1'),
				'uid_owner'   => $qb->expr()->literal('user3'),
				'item_type'   => $qb->expr()->literal('folder'),
				'file_source' => $qb->expr()->literal(3),
				'file_target' => $qb->expr()->literal('myTarget2'),
				'permissions' => $qb->expr()->literal(4),
				'parent'      => $qb->expr()->literal($id),
			]);
		$qb->execute();

		$shareOwner = $this->getMock('OCP\IUser');
		$shareOwner->method('getUID')->willReturn('shareOwner');
		$user1 = $this->getMock('OCP\IUser');
		$user2 = $this->getMock('OCP\IUser');
		$user2->method('getUID')->willReturn('user2');
		$user3 = $this->getMock('OCP\IUser');
		$user3->method('getUID')->willReturn('user3');

		$user2Path = $this->getMock('\OCP\Files\File');
		$user2Path->expects($this->once())->method('getOwner')->willReturn($shareOwner);
		$user2Folder = $this->getMock('\OCP\Files\Folder');
		$user2Folder->expects($this->once())
			->method('getById')
			->with(1)
			->willReturn([$user2Path]);

		$user3Path = $this->getMock('\OCP\Files\Folder');
		$user3Path->expects($this->once())->method('getOwner')->willReturn($shareOwner);
		$user3Folder = $this->getMock('\OCP\Files\Folder');
		$user3Folder->expects($this->once())
			->method('getById')
			->with(3)
			->willReturn([$user3Path]);

		$ownerPath = $this->getMock('\OCP\Files\Folder');
		$ownerFolder = $this->getMock('\OCP\Files\Folder');
		$ownerFolder->method('getById')->willReturn([$ownerPath]);

		$this->rootFolder
			->method('getUserFolder')
			->will($this->returnValueMap([
				['shareOwner', $ownerFolder],
				['user2', $user2Folder],
				['user3', $user3Folder],
			]));

		$this->userManager
			->method('get')
			->will($this->returnValueMap([
				['user1', $user1],
				['user2', $user2],
				['user3', $user3],
			]));

		$group1 = $this->getMock('OCP\IGroup');
		$this->groupManager
			->method('get')
			->will($this->returnValueMap([
				['group1', $group1]
			]));

		$share = $this->getMock('\OCP\Share\IShare');
		$share->method('getId')->willReturn($id);

		$children = $this->provider->getChildren($share);

		$this->assertCount(2, $children);

		//Child1
		$this->assertEquals(\OCP\Share::SHARE_TYPE_USER, $children[0]->getShareType());
		$this->assertEquals('user1', $children[0]->getSharedWith());
		$this->assertEquals('user2', $children[0]->getSharedBy());
		$this->assertEquals('shareOwner', $children[0]->getShareOwner());
		$this->assertEquals($ownerPath, $children[0]->getNode());
		$this->assertEquals(2, $children[0]->getPermissions());
		$this->assertEquals(null, $children[0]->getToken());
		$this->assertEquals(null, $children[0]->getExpirationDate());
		$this->assertEquals('myTarget1', $children[0]->getTarget());

		//Child2
		$this->assertEquals(\OCP\Share::SHARE_TYPE_GROUP, $children[1]->getShareType());
		$this->assertEquals('group1', $children[1]->getSharedWith());
		$this->assertEquals('user3', $children[1]->getSharedBy());
		$this->assertEquals('shareOwner', $children[1]->getShareOwner());
		$this->assertEquals($ownerPath, $children[1]->getNode());
		$this->assertEquals(4, $children[1]->getPermissions());
		$this->assertEquals(null, $children[1]->getToken());
		$this->assertEquals(null, $children[1]->getExpirationDate());
		$this->assertEquals('myTarget2', $children[1]->getTarget());
	}

	public function testCreateUserShare() {
		$share = new \OC\Share20\Share($this->rootFolder);

		$shareOwner = $this->getMock('OCP\IUser');
		$shareOwner->method('getUID')->WillReturn('shareOwner');

		$path = $this->getMock('\OCP\Files\File');
		$path->method('getId')->willReturn(100);
		$path->method('getOwner')->willReturn($shareOwner);

		$ownerFolder = $this->getMock('OCP\Files\Folder');
		$userFolder = $this->getMock('OCP\Files\Folder');
		$this->rootFolder
			->method('getUserFolder')
			->will($this->returnValueMap([
				['sharedBy', $userFolder],
				['shareOwner', $ownerFolder],
			]));

		$userFolder->method('getById')
			->with(100)
			->willReturn([$path]);
		$ownerFolder->method('getById')
			->with(100)
			->willReturn([$path]);

		$share->setShareType(\OCP\Share::SHARE_TYPE_USER);
		$share->setSharedWith('sharedWith');
		$share->setSharedBy('sharedBy');
		$share->setShareOwner('shareOwner');
		$share->setNode($path);
		$share->setPermissions(1);
		$share->setTarget('/target');

		$share2 = $this->provider->create($share);

		$this->assertNotNull($share2->getId());
		$this->assertSame('ocinternal:'.$share2->getId(), $share2->getFullId());
		$this->assertSame(\OCP\Share::SHARE_TYPE_USER, $share2->getShareType());
		$this->assertSame('sharedWith', $share2->getSharedWith());
		$this->assertSame('sharedBy', $share2->getSharedBy());
		$this->assertSame('shareOwner', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());
		$this->assertSame('/target', $share2->getTarget());
		$this->assertLessThanOrEqual(new \DateTime(), $share2->getShareTime());
		$this->assertSame($path, $share2->getNode());
	}

	public function testCreateGroupShare() {
		$share = new \OC\Share20\Share($this->rootFolder);

		$shareOwner = $this->getMock('\OCP\IUser');
		$shareOwner->method('getUID')->willReturn('shareOwner');

		$path = $this->getMock('\OCP\Files\Folder');
		$path->method('getId')->willReturn(100);
		$path->method('getOwner')->willReturn($shareOwner);

		$ownerFolder = $this->getMock('OCP\Files\Folder');
		$userFolder = $this->getMock('OCP\Files\Folder');
		$this->rootFolder
			->method('getUserFolder')
			->will($this->returnValueMap([
				['sharedBy', $userFolder],
				['shareOwner', $ownerFolder],
			]));

		$userFolder->method('getById')
			->with(100)
			->willReturn([$path]);
		$ownerFolder->method('getById')
			->with(100)
			->willReturn([$path]);

		$share->setShareType(\OCP\Share::SHARE_TYPE_GROUP);
		$share->setSharedWith('sharedWith');
		$share->setSharedBy('sharedBy');
		$share->setShareOwner('shareOwner');
		$share->setNode($path);
		$share->setPermissions(1);
		$share->setTarget('/target');

		$share2 = $this->provider->create($share);

		$this->assertNotNull($share2->getId());
		$this->assertSame('ocinternal:'.$share2->getId(), $share2->getFullId());
		$this->assertSame(\OCP\Share::SHARE_TYPE_GROUP, $share2->getShareType());
		$this->assertSame('sharedWith', $share2->getSharedWith());
		$this->assertSame('sharedBy', $share2->getSharedBy());
		$this->assertSame('shareOwner', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());
		$this->assertSame('/target', $share2->getTarget());
		$this->assertLessThanOrEqual(new \DateTime(), $share2->getShareTime());
		$this->assertSame($path, $share2->getNode());
	}

	public function testCreateLinkShare() {
		$share = new \OC\Share20\Share($this->rootFolder);

		$shareOwner = $this->getMock('\OCP\IUser');
		$shareOwner->method('getUID')->willReturn('shareOwner');

		$path = $this->getMock('\OCP\Files\Folder');
		$path->method('getId')->willReturn(100);
		$path->method('getOwner')->willReturn($shareOwner);

		$ownerFolder = $this->getMock('OCP\Files\Folder');
		$userFolder = $this->getMock('OCP\Files\Folder');
		$this->rootFolder
				->method('getUserFolder')
				->will($this->returnValueMap([
						['sharedBy', $userFolder],
						['shareOwner', $ownerFolder],
				]));

		$userFolder->method('getById')
				->with(100)
				->willReturn([$path]);
		$ownerFolder->method('getById')
				->with(100)
				->willReturn([$path]);

		$share->setShareType(\OCP\Share::SHARE_TYPE_LINK);
		$share->setSharedBy('sharedBy');
		$share->setShareOwner('shareOwner');
		$share->setNode($path);
		$share->setPermissions(1);
		$share->setPassword('password');
		$share->setToken('token');
		$expireDate = new \DateTime();
		$share->setExpirationDate($expireDate);
		$share->setTarget('/target');

		$share2 = $this->provider->create($share);

		$this->assertNotNull($share2->getId());
		$this->assertSame('ocinternal:'.$share2->getId(), $share2->getFullId());
		$this->assertSame(\OCP\Share::SHARE_TYPE_LINK, $share2->getShareType());
		$this->assertSame('sharedBy', $share2->getSharedBy());
		$this->assertSame('shareOwner', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());
		$this->assertSame('/target', $share2->getTarget());
		$this->assertLessThanOrEqual(new \DateTime(), $share2->getShareTime());
		$this->assertSame($path, $share2->getNode());
		$this->assertSame('password', $share2->getPassword());
		$this->assertSame('token', $share2->getToken());
		$this->assertEquals($expireDate, $share2->getExpirationDate());
	}

	public function testGetShareByToken() {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->insert('share')
			->values([
				'share_type'    => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_LINK),
				'share_with'    => $qb->expr()->literal('password'),
				'uid_owner'     => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type'     => $qb->expr()->literal('file'),
				'file_source'   => $qb->expr()->literal(42),
				'file_target'   => $qb->expr()->literal('myTarget'),
				'permissions'   => $qb->expr()->literal(13),
				'token'         => $qb->expr()->literal('secrettoken'),
			]);
		$qb->execute();
		$id = $qb->getLastInsertId();

		$file = $this->getMock('\OCP\Files\File');

		$this->rootFolder->method('getUserFolder')->with('shareOwner')->will($this->returnSelf());
		$this->rootFolder->method('getById')->with(42)->willReturn([$file]);

		$share = $this->provider->getShareByToken('secrettoken');
		$this->assertEquals($id, $share->getId());
		$this->assertSame('shareOwner', $share->getShareOwner());
		$this->assertSame('sharedBy', $share->getSharedBy());
		$this->assertSame('secrettoken', $share->getToken());
		$this->assertSame('password', $share->getPassword());
		$this->assertSame(null, $share->getSharedWith());
	}

	/**
	 * @expectedException \OCP\Share\Exceptions\ShareNotFound
	 */
	public function testGetShareByTokenNotFound() {
		$this->provider->getShareByToken('invalidtoken');
	}

	public function testGetSharedWithUser() {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());
		$id = $qb->getLastInsertId();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith2'),
				'uid_owner' => $qb->expr()->literal('shareOwner2'),
				'uid_initiator' => $qb->expr()->literal('sharedBy2'),
				'item_type'   => $qb->expr()->literal('file2'),
				'file_source' => $qb->expr()->literal(43),
				'file_target' => $qb->expr()->literal('myTarget2'),
				'permissions' => $qb->expr()->literal(14),
			]);
		$this->assertEquals(1, $qb->execute());

		$file = $this->getMock('\OCP\Files\File');
		$this->rootFolder->method('getUserFolder')->with('shareOwner')->will($this->returnSelf());
		$this->rootFolder->method('getById')->with(42)->willReturn([$file]);

		$share = $this->provider->getSharedWith('sharedWith', \OCP\Share::SHARE_TYPE_USER, null, 1 , 0);
		$this->assertCount(1, $share);

		$share = $share[0];
		$this->assertEquals($id, $share->getId());
		$this->assertEquals('sharedWith', $share->getSharedWith());
		$this->assertEquals('shareOwner', $share->getShareOwner());
		$this->assertEquals('sharedBy', $share->getSharedBy());
		$this->assertEquals(\OCP\Share::SHARE_TYPE_USER, $share->getShareType());
	}

	public function testGetSharedWithGroup() {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_GROUP),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner2'),
				'uid_initiator' => $qb->expr()->literal('sharedBy2'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(43),
				'file_target' => $qb->expr()->literal('myTarget2'),
				'permissions' => $qb->expr()->literal(14),
			]);
		$this->assertEquals(1, $qb->execute());

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_GROUP),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());
		$id = $qb->getLastInsertId();

		$groups = [];
		foreach(range(0, 100) as $i) {
			$group = $this->getMock('\OCP\IGroup');
			$group->method('getGID')->willReturn('group'.$i);
			$groups[] = $group;
		}

		$group = $this->getMock('\OCP\IGroup');
		$group->method('getGID')->willReturn('sharedWith');
		$groups[] = $group;

		$user = $this->getMock('\OCP\IUser');
		$user->method('getUID')->willReturn('sharedWith');
		$owner = $this->getMock('\OCP\IUser');
		$owner->method('getUID')->willReturn('shareOwner');
		$initiator = $this->getMock('\OCP\IUser');
		$initiator->method('getUID')->willReturn('sharedBy');

		$this->userManager->method('get')->willReturnMap([
			['sharedWith', $user],
			['shareOwner', $owner],
			['sharedBy', $initiator],
		]);
		$this->groupManager->method('getUserGroups')->with($user)->willReturn($groups);
		$this->groupManager->method('get')->with('sharedWith')->willReturn($group);

		$file = $this->getMock('\OCP\Files\File');
		$this->rootFolder->method('getUserFolder')->with('shareOwner')->will($this->returnSelf());
		$this->rootFolder->method('getById')->with(42)->willReturn([$file]);

		$share = $this->provider->getSharedWith('sharedWith', \OCP\Share::SHARE_TYPE_GROUP, null, 20 , 1);
		$this->assertCount(1, $share);

		$share = $share[0];
		$this->assertEquals($id, $share->getId());
		$this->assertEquals('sharedWith', $share->getSharedWith());
		$this->assertEquals('shareOwner', $share->getShareOwner());
		$this->assertEquals('sharedBy', $share->getSharedBy());
		$this->assertEquals(\OCP\Share::SHARE_TYPE_GROUP, $share->getShareType());
	}

	public function testGetSharedWithGroupUserModified() {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_GROUP),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());
		$id = $qb->getLastInsertId();

		/*
		 * Wrong share. Should not be taken by code.
		 */
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(2),
				'share_with' => $qb->expr()->literal('user2'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('wrongTarget'),
				'permissions' => $qb->expr()->literal(31),
				'parent' => $qb->expr()->literal($id),
			]);
		$this->assertEquals(1, $qb->execute());

		/*
		 * Correct share. should be taken by code path.
		 */
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(2),
				'share_with' => $qb->expr()->literal('user'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('userTarget'),
				'permissions' => $qb->expr()->literal(0),
				'parent' => $qb->expr()->literal($id),
			]);
		$this->assertEquals(1, $qb->execute());

		$group = $this->getMock('\OCP\IGroup');
		$group->method('getGID')->willReturn('sharedWith');
		$groups = [$group];

		$user = $this->getMock('\OCP\IUser');
		$user->method('getUID')->willReturn('user');
		$owner = $this->getMock('\OCP\IUser');
		$owner->method('getUID')->willReturn('shareOwner');
		$initiator = $this->getMock('\OCP\IUser');
		$initiator->method('getUID')->willReturn('sharedBy');

		$this->userManager->method('get')->willReturnMap([
			['user', $user],
			['shareOwner', $owner],
			['sharedBy', $initiator],
		]);
		$this->groupManager->method('getUserGroups')->with($user)->willReturn($groups);
		$this->groupManager->method('get')->with('sharedWith')->willReturn($group);

		$file = $this->getMock('\OCP\Files\File');
		$this->rootFolder->method('getUserFolder')->with('shareOwner')->will($this->returnSelf());
		$this->rootFolder->method('getById')->with(42)->willReturn([$file]);

		$share = $this->provider->getSharedWith('user', \OCP\Share::SHARE_TYPE_GROUP, null, -1, 0);
		$this->assertCount(1, $share);

		$share = $share[0];
		$this->assertSame($id, $share->getId());
		$this->assertSame('sharedWith', $share->getSharedWith());
		$this->assertSame('shareOwner', $share->getShareOwner());
		$this->assertSame('sharedBy', $share->getSharedBy());
		$this->assertSame(\OCP\Share::SHARE_TYPE_GROUP, $share->getShareType());
		$this->assertSame(0, $share->getPermissions());
		$this->assertSame('userTarget', $share->getTarget());
	}

	public function testGetSharedWithUserWithNode() {
		$this->addShareToDB(\OCP\Share::SHARE_TYPE_USER, 'user0', 'user1', 'user1',
			'file', 42, 'myTarget', 31, null, null, null);
		$id = $this->addShareToDB(\OCP\Share::SHARE_TYPE_USER, 'user0', 'user1', 'user1',
			'file', 43, 'myTarget', 31, null, null, null);

		$user0 = $this->getMock('\OCP\IUser');
		$user0->method('getUID')->willReturn('user0');
		$user1 = $this->getMock('\OCP\IUser');
		$user1->method('getUID')->willReturn('user1');

		$this->userManager->method('get')->willReturnMap([
			['user0', $user0],
			['user1', $user1],
		]);

		$file = $this->getMock('\OCP\Files\File');
		$file->method('getId')->willReturn(43);
		$this->rootFolder->method('getUserFolder')->with('user1')->will($this->returnSelf());
		$this->rootFolder->method('getById')->with(43)->willReturn([$file]);

		$share = $this->provider->getSharedWith('user0', \OCP\Share::SHARE_TYPE_USER, $file, -1, 0);
		$this->assertCount(1, $share);

		$share = $share[0];
		$this->assertEquals($id, $share->getId());
		$this->assertSame('user0', $share->getSharedWith());
		$this->assertSame('user1', $share->getShareOwner());
		$this->assertSame('user1', $share->getSharedBy());
		$this->assertSame($file, $share->getNode());
		$this->assertEquals(\OCP\Share::SHARE_TYPE_USER, $share->getShareType());
	}

	public function testGetSharedWithGroupWithNode() {
		$this->addShareToDB(\OCP\Share::SHARE_TYPE_GROUP, 'group0', 'user1', 'user1',
			'file', 42, 'myTarget', 31, null, null, null);
		$id = $this->addShareToDB(\OCP\Share::SHARE_TYPE_GROUP, 'group0', 'user1', 'user1',
			'file', 43, 'myTarget', 31, null, null, null);

		$user0 = $this->getMock('\OCP\IUser');
		$user0->method('getUID')->willReturn('user0');
		$user1 = $this->getMock('\OCP\IUser');
		$user1->method('getUID')->willReturn('user1');

		$this->userManager->method('get')->willReturnMap([
			['user0', $user0],
			['user1', $user1],
		]);

		$group0 = $this->getMock('\OCP\IGroup');
		$group0->method('getGID')->willReturn('group0');

		$this->groupManager->method('get')->with('group0')->willReturn($group0);
		$this->groupManager->method('getUserGroups')->with($user0)->willReturn([$group0]);

		$node = $this->getMock('\OCP\Files\Folder');
		$node->method('getId')->willReturn(43);
		$this->rootFolder->method('getUserFolder')->with('user1')->will($this->returnSelf());
		$this->rootFolder->method('getById')->with(43)->willReturn([$node]);

		$share = $this->provider->getSharedWith('user0', \OCP\Share::SHARE_TYPE_GROUP, $node, -1, 0);
		$this->assertCount(1, $share);

		$share = $share[0];
		$this->assertEquals($id, $share->getId());
		$this->assertSame('group0', $share->getSharedWith());
		$this->assertSame('user1', $share->getShareOwner());
		$this->assertSame('user1', $share->getSharedBy());
		$this->assertSame($node, $share->getNode());
		$this->assertEquals(\OCP\Share::SHARE_TYPE_GROUP, $share->getShareType());
	}

	public function testGetSharesBy() {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());
		$id = $qb->getLastInsertId();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy2'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('userTarget'),
				'permissions' => $qb->expr()->literal(0),
				'parent' => $qb->expr()->literal($id),
			]);
		$this->assertEquals(1, $qb->execute());

		$file = $this->getMock('\OCP\Files\File');
		$this->rootFolder->method('getUserFolder')->with('shareOwner')->will($this->returnSelf());
		$this->rootFolder->method('getById')->with(42)->willReturn([$file]);

		$share = $this->provider->getSharesBy('sharedBy', \OCP\Share::SHARE_TYPE_USER, null, false, 1, 0);
		$this->assertCount(1, $share);

		$share = $share[0];
		$this->assertEquals($id, $share->getId());
		$this->assertEquals('sharedWith', $share->getSharedWith());
		$this->assertEquals('shareOwner', $share->getShareOwner());
		$this->assertEquals('sharedBy', $share->getSharedBy());
		$this->assertEquals(\OCP\Share::SHARE_TYPE_USER, $share->getShareType());
		$this->assertEquals(13, $share->getPermissions());
		$this->assertEquals('myTarget', $share->getTarget());
	}

	public function testGetSharesNode() {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());
		$id = $qb->getLastInsertId();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type'   => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(43),
				'file_target' => $qb->expr()->literal('userTarget'),
				'permissions' => $qb->expr()->literal(0),
				'parent' => $qb->expr()->literal($id),
			]);
		$this->assertEquals(1, $qb->execute());

		$file = $this->getMock('\OCP\Files\File');
		$file->method('getId')->willReturn(42);
		$this->rootFolder->method('getUserFolder')->with('shareOwner')->will($this->returnSelf());
		$this->rootFolder->method('getById')->with(42)->willReturn([$file]);

		$share = $this->provider->getSharesBy('sharedBy', \OCP\Share::SHARE_TYPE_USER, $file, false, 1, 0);
		$this->assertCount(1, $share);

		$share = $share[0];
		$this->assertEquals($id, $share->getId());
		$this->assertEquals('sharedWith', $share->getSharedWith());
		$this->assertEquals('shareOwner', $share->getShareOwner());
		$this->assertEquals('sharedBy', $share->getSharedBy());
		$this->assertEquals(\OCP\Share::SHARE_TYPE_USER, $share->getShareType());
		$this->assertEquals(13, $share->getPermissions());
		$this->assertEquals('myTarget', $share->getTarget());
	}

	public function testGetSharesReshare() {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('shareOwner'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());
		$id1 = $qb->getLastInsertId();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('userTarget'),
				'permissions' => $qb->expr()->literal(0),
			]);
		$this->assertEquals(1, $qb->execute());
		$id2 = $qb->getLastInsertId();

		$file = $this->getMock('\OCP\Files\File');
		$file->method('getId')->willReturn(42);
		$this->rootFolder->method('getUserFolder')->with('shareOwner')->will($this->returnSelf());
		$this->rootFolder->method('getById')->with(42)->willReturn([$file]);

		$shares = $this->provider->getSharesBy('shareOwner', \OCP\Share::SHARE_TYPE_USER, null, true, -1, 0);
		$this->assertCount(2, $shares);

		$share = $shares[0];
		$this->assertEquals($id1, $share->getId());
		$this->assertSame('sharedWith', $share->getSharedWith());
		$this->assertSame('shareOwner', $share->getShareOwner());
		$this->assertSame('shareOwner', $share->getSharedBy());
		$this->assertEquals(\OCP\Share::SHARE_TYPE_USER, $share->getShareType());
		$this->assertEquals(13, $share->getPermissions());
		$this->assertEquals('myTarget', $share->getTarget());

		$share = $shares[1];
		$this->assertEquals($id2, $share->getId());
		$this->assertSame('sharedWith', $share->getSharedWith());
		$this->assertSame('shareOwner', $share->getShareOwner());
		$this->assertSame('sharedBy', $share->getSharedBy());
		$this->assertEquals(\OCP\Share::SHARE_TYPE_USER, $share->getShareType());
		$this->assertEquals(0, $share->getPermissions());
		$this->assertEquals('userTarget', $share->getTarget());
	}

	public function testDeleteFromSelfGroupNoCustomShare() {
		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->insert('share')
			->values([
				'share_type'    => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_GROUP),
				'share_with'    => $qb->expr()->literal('group'),
				'uid_owner'     => $qb->expr()->literal('user1'),
				'uid_initiator' => $qb->expr()->literal('user1'),
				'item_type'     => $qb->expr()->literal('file'),
				'file_source'   => $qb->expr()->literal(1),
				'file_target'   => $qb->expr()->literal('myTarget1'),
				'permissions'   => $qb->expr()->literal(2)
			])->execute();
		$this->assertEquals(1, $stmt);
		$id = $qb->getLastInsertId();

		$user1 = $this->getMock('\OCP\IUser');
		$user1->method('getUID')->willReturn('user1');
		$user2 = $this->getMock('\OCP\IUser');
		$user2->method('getUID')->willReturn('user2');
		$this->userManager->method('get')->will($this->returnValueMap([
			['user1', $user1],
			['user2', $user2],
		]));

		$group = $this->getMock('\OCP\IGroup');
		$group->method('getGID')->willReturn('group');
		$group->method('inGroup')->with($user2)->willReturn(true);
		$this->groupManager->method('get')->with('group')->willReturn($group);

		$file = $this->getMock('\OCP\Files\File');
		$file->method('getId')->willReturn(1);

		$this->rootFolder->method('getUserFolder')->with('user1')->will($this->returnSelf());
		$this->rootFolder->method('getById')->with(1)->willReturn([$file]);

		$share = $this->provider->getShareById($id);

		$this->provider->deleteFromSelf($share, 'user2');

		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->select('*')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(2)))
			->execute();

		$shares = $stmt->fetchAll();
		$stmt->closeCursor();

		$this->assertCount(1, $shares);
		$share2 = $shares[0];
		$this->assertEquals($id, $share2['parent']);
		$this->assertEquals(0, $share2['permissions']);
		$this->assertEquals('user2', $share2['share_with']);
	}

	public function testDeleteFromSelfGroupAlreadyCustomShare() {
		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->insert('share')
			->values([
				'share_type'    => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_GROUP),
				'share_with'    => $qb->expr()->literal('group'),
				'uid_owner'     => $qb->expr()->literal('user1'),
				'uid_initiator' => $qb->expr()->literal('user1'),
				'item_type'     => $qb->expr()->literal('file'),
				'file_source'   => $qb->expr()->literal(1),
				'file_target'   => $qb->expr()->literal('myTarget1'),
				'permissions'   => $qb->expr()->literal(2)
			])->execute();
		$this->assertEquals(1, $stmt);
		$id = $qb->getLastInsertId();

		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->insert('share')
			->values([
				'share_type'    => $qb->expr()->literal(2),
				'share_with'    => $qb->expr()->literal('user2'),
				'uid_owner'     => $qb->expr()->literal('user1'),
				'uid_initiator' => $qb->expr()->literal('user1'),
				'item_type'     => $qb->expr()->literal('file'),
				'file_source'   => $qb->expr()->literal(1),
				'file_target'   => $qb->expr()->literal('myTarget1'),
				'permissions'   => $qb->expr()->literal(2),
				'parent'        => $qb->expr()->literal($id),
			])->execute();
		$this->assertEquals(1, $stmt);

		$user1 = $this->getMock('\OCP\IUser');
		$user1->method('getUID')->willReturn('user1');
		$user2 = $this->getMock('\OCP\IUser');
		$user2->method('getUID')->willReturn('user2');
		$this->userManager->method('get')->will($this->returnValueMap([
			['user1', $user1],
			['user2', $user2],
		]));

		$group = $this->getMock('\OCP\IGroup');
		$group->method('getGID')->willReturn('group');
		$group->method('inGroup')->with($user2)->willReturn(true);
		$this->groupManager->method('get')->with('group')->willReturn($group);

		$file = $this->getMock('\OCP\Files\File');
		$file->method('getId')->willReturn(1);

		$this->rootFolder->method('getUserFolder')->with('user1')->will($this->returnSelf());
		$this->rootFolder->method('getById')->with(1)->willReturn([$file]);

		$share = $this->provider->getShareById($id);

		$this->provider->deleteFromSelf($share, 'user2');

		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->select('*')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(2)))
			->execute();

		$shares = $stmt->fetchAll();
		$stmt->closeCursor();

		$this->assertCount(1, $shares);
		$share2 = $shares[0];
		$this->assertEquals($id, $share2['parent']);
		$this->assertEquals(0, $share2['permissions']);
		$this->assertEquals('user2', $share2['share_with']);
	}

	/**
	 * @expectedException \OC\Share20\Exception\ProviderException
	 * @expectedExceptionMessage  Recipient not in receiving group
	 */
	public function testDeleteFromSelfGroupUserNotInGroup() {
		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->insert('share')
			->values([
				'share_type'    => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_GROUP),
				'share_with'    => $qb->expr()->literal('group'),
				'uid_owner'     => $qb->expr()->literal('user1'),
				'uid_initiator' => $qb->expr()->literal('user1'),
				'item_type'     => $qb->expr()->literal('file'),
				'file_source'   => $qb->expr()->literal(1),
				'file_target'   => $qb->expr()->literal('myTarget1'),
				'permissions'   => $qb->expr()->literal(2)
			])->execute();
		$this->assertEquals(1, $stmt);
		$id = $qb->getLastInsertId();

		$user1 = $this->getMock('\OCP\IUser');
		$user1->method('getUID')->willReturn('user1');
		$user2 = $this->getMock('\OCP\IUser');
		$user2->method('getUID')->willReturn('user2');
		$this->userManager->method('get')->will($this->returnValueMap([
			['user1', $user1],
			['user2', $user2],
		]));

		$group = $this->getMock('\OCP\IGroup');
		$group->method('getGID')->willReturn('group');
		$group->method('inGroup')->with($user2)->willReturn(false);
		$this->groupManager->method('get')->with('group')->willReturn($group);

		$file = $this->getMock('\OCP\Files\File');
		$file->method('getId')->willReturn(1);

		$this->rootFolder->method('getUserFolder')->with('user1')->will($this->returnSelf());
		$this->rootFolder->method('getById')->with(1)->willReturn([$file]);

		$share = $this->provider->getShareById($id);

		$this->provider->deleteFromSelf($share, 'user2');
	}

	public function testDeleteFromSelfUser() {
		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->insert('share')
			->values([
				'share_type'    => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_USER),
				'share_with'    => $qb->expr()->literal('user2'),
				'uid_owner'     => $qb->expr()->literal('user1'),
				'uid_initiator' => $qb->expr()->literal('user1'),
				'item_type'     => $qb->expr()->literal('file'),
				'file_source'   => $qb->expr()->literal(1),
				'file_target'   => $qb->expr()->literal('myTarget1'),
				'permissions'   => $qb->expr()->literal(2)
			])->execute();
		$this->assertEquals(1, $stmt);
		$id = $qb->getLastInsertId();

		$user1 = $this->getMock('\OCP\IUser');
		$user1->method('getUID')->willReturn('user1');
		$user2 = $this->getMock('\OCP\IUser');
		$user2->method('getUID')->willReturn('user2');
		$this->userManager->method('get')->will($this->returnValueMap([
			['user1', $user1],
			['user2', $user2],
		]));

		$file = $this->getMock('\OCP\Files\File');
		$file->method('getId')->willReturn(1);

		$this->rootFolder->method('getUserFolder')->with('user1')->will($this->returnSelf());
		$this->rootFolder->method('getById')->with(1)->willReturn([$file]);

		$share = $this->provider->getShareById($id);

		$this->provider->deleteFromSelf($share, 'user2');

		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->execute();

		$shares = $stmt->fetchAll();
		$stmt->closeCursor();

		$this->assertCount(0, $shares);
	}

	/**
	 * @expectedException \OC\Share20\Exception\ProviderException
	 * @expectedExceptionMessage Recipient does not match
	 */
	public function testDeleteFromSelfUserNotRecipient() {
		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->insert('share')
			->values([
				'share_type'    => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_USER),
				'share_with'    => $qb->expr()->literal('user2'),
				'uid_owner'     => $qb->expr()->literal('user1'),
				'uid_initiator' => $qb->expr()->literal('user1'),
				'item_type'     => $qb->expr()->literal('file'),
				'file_source'   => $qb->expr()->literal(1),
				'file_target'   => $qb->expr()->literal('myTarget1'),
				'permissions'   => $qb->expr()->literal(2)
			])->execute();
		$this->assertEquals(1, $stmt);
		$id = $qb->getLastInsertId();

		$user1 = $this->getMock('\OCP\IUser');
		$user1->method('getUID')->willReturn('user1');
		$user2 = $this->getMock('\OCP\IUser');
		$user2->method('getUID')->willReturn('user2');
		$user3 = $this->getMock('\OCP\IUser');
		$this->userManager->method('get')->will($this->returnValueMap([
			['user1', $user1],
			['user2', $user2],
		]));

		$file = $this->getMock('\OCP\Files\File');
		$file->method('getId')->willReturn(1);

		$this->rootFolder->method('getUserFolder')->with('user1')->will($this->returnSelf());
		$this->rootFolder->method('getById')->with(1)->willReturn([$file]);

		$share = $this->provider->getShareById($id);

		$this->provider->deleteFromSelf($share, $user3);
	}

	/**
	 * @expectedException \OC\Share20\Exception\ProviderException
	 * @expectedExceptionMessage Invalid shareType
	 */
	public function testDeleteFromSelfLink() {
		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(\OCP\Share::SHARE_TYPE_LINK),
				'uid_owner' => $qb->expr()->literal('user1'),
				'uid_initiator' => $qb->expr()->literal('user1'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(1),
				'file_target' => $qb->expr()->literal('myTarget1'),
				'permissions' => $qb->expr()->literal(2),
				'token' => $qb->expr()->literal('token'),
			])->execute();
		$this->assertEquals(1, $stmt);
		$id = $qb->getLastInsertId();

		$user1 = $this->getMock('\OCP\IUser');
		$user1->method('getUID')->willReturn('user1');
		$this->userManager->method('get')->will($this->returnValueMap([
			['user1', $user1],
		]));

		$file = $this->getMock('\OCP\Files\File');
		$file->method('getId')->willReturn(1);

		$this->rootFolder->method('getUserFolder')->with('user1')->will($this->returnSelf());
		$this->rootFolder->method('getById')->with(1)->willReturn([$file]);

		$share = $this->provider->getShareById($id);

		$this->provider->deleteFromSelf($share, $user1);
	}

	public function testUpdateUser() {
		$id = $this->addShareToDB(\OCP\Share::SHARE_TYPE_USER, 'user0', 'user1', 'user2',
			'file', 42, 'target', 31, null, null);

		$users = [];
		for($i = 0; $i < 6; $i++) {
			$user = $this->getMock('\OCP\IUser');
			$user->method('getUID')->willReturn('user'.$i);
			$users['user'.$i] = $user;
		}

		$this->userManager->method('get')->will(
			$this->returnCallback(function($userId) use ($users) {
				return $users[$userId];
			})
		);

		$file1 = $this->getMock('\OCP\Files\File');
		$file1->method('getId')->willReturn(42);
		$file2 = $this->getMock('\OCP\Files\File');
		$file2->method('getId')->willReturn(43);

		$folder1 = $this->getMock('\OCP\Files\Folder');
		$folder1->method('getById')->with(42)->willReturn([$file1]);
		$folder2 = $this->getMock('\OCP\Files\Folder');
		$folder2->method('getById')->with(43)->willReturn([$file2]);

		$this->rootFolder->method('getUserFolder')->will($this->returnValueMap([
			['user2', $folder1],
			['user5', $folder2],
		]));

		$share = $this->provider->getShareById($id);

		$share->setSharedWith('user3');
		$share->setSharedBy('user4');
		$share->setShareOwner('user5');
		$share->setNode($file2);
		$share->setPermissions(1);

		$share2 = $this->provider->update($share);

		$this->assertEquals($id, $share2->getId());
		$this->assertSame('user3', $share2->getSharedWith());
		$this->assertSame('user4', $share2->getSharedBy());
		$this->assertSame('user5', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());
	}

	public function testUpdateLink() {
		$id = $this->addShareToDB(\OCP\Share::SHARE_TYPE_LINK, null, 'user1', 'user2',
			'file', 42, 'target', 31, null, null);

		$users = [];
		for($i = 0; $i < 6; $i++) {
			$user = $this->getMock('\OCP\IUser');
			$user->method('getUID')->willReturn('user'.$i);
			$users['user'.$i] = $user;
		}

		$this->userManager->method('get')->will(
			$this->returnCallback(function($userId) use ($users) {
				return $users[$userId];
			})
		);

		$file1 = $this->getMock('\OCP\Files\File');
		$file1->method('getId')->willReturn(42);
		$file2 = $this->getMock('\OCP\Files\File');
		$file2->method('getId')->willReturn(43);

		$folder1 = $this->getMock('\OCP\Files\Folder');
		$folder1->method('getById')->with(42)->willReturn([$file1]);
		$folder2 = $this->getMock('\OCP\Files\Folder');
		$folder2->method('getById')->with(43)->willReturn([$file2]);

		$this->rootFolder->method('getUserFolder')->will($this->returnValueMap([
			['user2', $folder1],
			['user5', $folder2],
		]));

		$share = $this->provider->getShareById($id);

		$share->setPassword('password');
		$share->setSharedBy('user4');
		$share->setShareOwner('user5');
		$share->setNode($file2);
		$share->setPermissions(1);

		$share2 = $this->provider->update($share);

		$this->assertEquals($id, $share2->getId());
		$this->assertEquals('password', $share->getPassword());
		$this->assertSame('user4', $share2->getSharedBy());
		$this->assertSame('user5', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());
	}

	public function testUpdateLinkRemovePassword() {
		$id = $this->addShareToDB(\OCP\Share::SHARE_TYPE_LINK, 'foo', 'user1', 'user2',
			'file', 42, 'target', 31, null, null);

		$users = [];
		for($i = 0; $i < 6; $i++) {
			$user = $this->getMock('\OCP\IUser');
			$user->method('getUID')->willReturn('user'.$i);
			$users['user'.$i] = $user;
		}

		$this->userManager->method('get')->will(
			$this->returnCallback(function($userId) use ($users) {
				return $users[$userId];
			})
		);

		$file1 = $this->getMock('\OCP\Files\File');
		$file1->method('getId')->willReturn(42);
		$file2 = $this->getMock('\OCP\Files\File');
		$file2->method('getId')->willReturn(43);

		$folder1 = $this->getMock('\OCP\Files\Folder');
		$folder1->method('getById')->with(42)->willReturn([$file1]);
		$folder2 = $this->getMock('\OCP\Files\Folder');
		$folder2->method('getById')->with(43)->willReturn([$file2]);

		$this->rootFolder->method('getUserFolder')->will($this->returnValueMap([
			['user2', $folder1],
			['user5', $folder2],
		]));

		$share = $this->provider->getShareById($id);

		$share->setPassword(null);
		$share->setSharedBy('user4');
		$share->setShareOwner('user5');
		$share->setNode($file2);
		$share->setPermissions(1);

		$share2 = $this->provider->update($share);

		$this->assertEquals($id, $share2->getId());
		$this->assertEquals(null, $share->getPassword());
		$this->assertSame('user4', $share2->getSharedBy());
		$this->assertSame('user5', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());
	}

	public function testUpdateGroupNoSub() {
		$id = $this->addShareToDB(\OCP\Share::SHARE_TYPE_GROUP, 'group0', 'user1', 'user2',
			'file', 42, 'target', 31, null, null);

		$users = [];
		for($i = 0; $i < 6; $i++) {
			$user = $this->getMock('\OCP\IUser');
			$user->method('getUID')->willReturn('user'.$i);
			$users['user'.$i] = $user;
		}

		$this->userManager->method('get')->will(
			$this->returnCallback(function($userId) use ($users) {
				return $users[$userId];
			})
		);

		$groups = [];
		for($i = 0; $i < 2; $i++) {
			$group = $this->getMock('\OCP\IGroup');
			$group->method('getGID')->willReturn('group'.$i);
			$groups['group'.$i] = $group;
		}

		$this->groupManager->method('get')->will(
			$this->returnCallback(function($groupId) use ($groups) {
				return $groups[$groupId];
			})
		);

		$file1 = $this->getMock('\OCP\Files\File');
		$file1->method('getId')->willReturn(42);
		$file2 = $this->getMock('\OCP\Files\File');
		$file2->method('getId')->willReturn(43);

		$folder1 = $this->getMock('\OCP\Files\Folder');
		$folder1->method('getById')->with(42)->willReturn([$file1]);
		$folder2 = $this->getMock('\OCP\Files\Folder');
		$folder2->method('getById')->with(43)->willReturn([$file2]);

		$this->rootFolder->method('getUserFolder')->will($this->returnValueMap([
			['user2', $folder1],
			['user5', $folder2],
		]));

		$share = $this->provider->getShareById($id);

		$share->setSharedWith('group0');
		$share->setSharedBy('user4');
		$share->setShareOwner('user5');
		$share->setNode($file2);
		$share->setPermissions(1);

		$share2 = $this->provider->update($share);

		$this->assertEquals($id, $share2->getId());
		// Group shares do not allow updating the recipient
		$this->assertSame('group0', $share2->getSharedWith());
		$this->assertSame('user4', $share2->getSharedBy());
		$this->assertSame('user5', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());
	}

	public function testUpdateGroupSubShares() {
		$id = $this->addShareToDB(\OCP\Share::SHARE_TYPE_GROUP, 'group0', 'user1', 'user2',
			'file', 42, 'target', 31, null, null);

		$id2 = $this->addShareToDB(2, 'user0', 'user1', 'user2',
			'file', 42, 'mytarget', 31, null, null, $id);

		$id3 = $this->addShareToDB(2, 'user3', 'user1', 'user2',
			'file', 42, 'mytarget2', 0, null, null, $id);

		$users = [];
		for($i = 0; $i < 6; $i++) {
			$user = $this->getMock('\OCP\IUser');
			$user->method('getUID')->willReturn('user'.$i);
			$users['user'.$i] = $user;
		}

		$this->userManager->method('get')->will(
			$this->returnCallback(function($userId) use ($users) {
				return $users[$userId];
			})
		);

		$groups = [];
		for($i = 0; $i < 2; $i++) {
			$group = $this->getMock('\OCP\IGroup');
			$group->method('getGID')->willReturn('group'.$i);
			$groups['group'.$i] = $group;
		}

		$this->groupManager->method('get')->will(
			$this->returnCallback(function($groupId) use ($groups) {
				return $groups[$groupId];
			})
		);

		$file1 = $this->getMock('\OCP\Files\File');
		$file1->method('getId')->willReturn(42);
		$file2 = $this->getMock('\OCP\Files\File');
		$file2->method('getId')->willReturn(43);

		$folder1 = $this->getMock('\OCP\Files\Folder');
		$folder1->method('getById')->with(42)->willReturn([$file1]);
		$folder2 = $this->getMock('\OCP\Files\Folder');
		$folder2->method('getById')->with(43)->willReturn([$file2]);

		$this->rootFolder->method('getUserFolder')->will($this->returnValueMap([
			['user2', $folder1],
			['user5', $folder2],
		]));

		$share = $this->provider->getShareById($id);

		$share->setSharedWith('group0');
		$share->setSharedBy('user4');
		$share->setShareOwner('user5');
		$share->setNode($file2);
		$share->setPermissions(1);

		$share2 = $this->provider->update($share);

		$this->assertEquals($id, $share2->getId());
		// Group shares do not allow updating the recipient
		$this->assertSame('group0', $share2->getSharedWith());
		$this->assertSame('user4', $share2->getSharedBy());
		$this->assertSame('user5', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());

		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->select('*')
			->from('share')
			->where($qb->expr()->eq('parent', $qb->createNamedParameter($id)))
			->orderBy('id')
			->execute();

		$shares = $stmt->fetchAll();

		$this->assertSame('user0', $shares[0]['share_with']);
		$this->assertSame('user4', $shares[0]['uid_initiator']);
		$this->assertSame('user5', $shares[0]['uid_owner']);
		$this->assertSame(1, (int)$shares[0]['permissions']);

		$this->assertSame('user3', $shares[1]['share_with']);
		$this->assertSame('user4', $shares[1]['uid_initiator']);
		$this->assertSame('user5', $shares[1]['uid_owner']);
		$this->assertSame(0, (int)$shares[1]['permissions']);


		$stmt->closeCursor();
	}

	public function testMoveUserShare() {
		$id = $this->addShareToDB(\OCP\Share::SHARE_TYPE_USER, 'user0', 'user1', 'user1', 'file',
			42, 'mytaret', 31, null, null);

		$user0 = $this->getMock('\OCP\IUser');
		$user0->method('getUID')->willReturn('user0');
		$user1 = $this->getMock('\OCP\IUser');
		$user1->method('getUID')->willReturn('user1');

		$this->userManager->method('get')->will($this->returnValueMap([
			['user0', $user0],
			['user1', $user1],
		]));

		$file = $this->getMock('\OCP\Files\File');
		$file->method('getId')->willReturn(42);

		$this->rootFolder->method('getUserFolder')->with('user1')->will($this->returnSelf());
		$this->rootFolder->method('getById')->willReturn([$file]);

		$share = $this->provider->getShareById($id, null);

		$share->setTarget('/newTarget');
		$this->provider->move($share, $user0);

		$share = $this->provider->getShareById($id, null);
		$this->assertSame('/newTarget', $share->getTarget());
	}

	public function testMoveGroupShare() {
		$id = $this->addShareToDB(\OCP\Share::SHARE_TYPE_GROUP, 'group0', 'user1', 'user1', 'file',
			42, 'mytaret', 31, null, null);

		$user0 = $this->getMock('\OCP\IUser');
		$user0->method('getUID')->willReturn('user0');
		$user1 = $this->getMock('\OCP\IUser');
		$user1->method('getUID')->willReturn('user1');

		$group0 = $this->getMock('\OCP\IGroup');
		$group0->method('getGID')->willReturn('group0');
		$group0->method('inGroup')->with($user0)->willReturn(true);

		$this->groupManager->method('get')->with('group0')->willReturn($group0);

		$this->userManager->method('get')->will($this->returnValueMap([
			['user0', $user0],
			['user1', $user1],
		]));

		$folder = $this->getMock('\OCP\Files\Folder');
		$folder->method('getId')->willReturn(42);

		$this->rootFolder->method('getUserFolder')->with('user1')->will($this->returnSelf());
		$this->rootFolder->method('getById')->willReturn([$folder]);

		$share = $this->provider->getShareById($id, 'user0');

		$share->setTarget('/newTarget');
		$this->provider->move($share, 'user0');

		$share = $this->provider->getShareById($id, 'user0');
		$this->assertSame('/newTarget', $share->getTarget());

		$share->setTarget('/ultraNewTarget');
		$this->provider->move($share, 'user0');

		$share = $this->provider->getShareById($id, 'user0');
		$this->assertSame('/ultraNewTarget', $share->getTarget());
	}
}
