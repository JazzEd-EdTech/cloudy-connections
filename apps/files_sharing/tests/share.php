<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
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

use OCA\Files\Share;

/**
 * Class Test_Files_Sharing
 *
 * @group DB
 */
class Test_Files_Sharing extends OCA\Files_sharing\Tests\TestCase {

	const TEST_FOLDER_NAME = '/folder_share_api_test';

	private static $tempStorage;

	protected function setUp() {
		parent::setUp();

		$this->folder = self::TEST_FOLDER_NAME;
		$this->subfolder  = '/subfolder_share_api_test';
		$this->subsubfolder = '/subsubfolder_share_api_test';

		$this->filename = '/share-api-test.txt';

		// save file with content
		$this->view->file_put_contents($this->filename, $this->data);
		$this->view->mkdir($this->folder);
		$this->view->mkdir($this->folder . $this->subfolder);
		$this->view->mkdir($this->folder . $this->subfolder . $this->subsubfolder);
		$this->view->file_put_contents($this->folder.$this->filename, $this->data);
		$this->view->file_put_contents($this->folder . $this->subfolder . $this->filename, $this->data);
	}

	protected function tearDown() {
		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);
		$this->view->unlink($this->filename);
		$this->view->deleteAll($this->folder);

		self::$tempStorage = null;

		// clear database table
		$query = \OCP\DB::prepare('DELETE FROM `*PREFIX*share`');
		$query->execute();

		parent::tearDown();
	}

	public function testUnshareFromSelf() {

		\OC_Group::createGroup('testGroup');
		\OC_Group::addToGroup(self::TEST_FILES_SHARING_API_USER2, 'testGroup');
		\OC_Group::addToGroup(self::TEST_FILES_SHARING_API_USER3, 'testGroup');

		$fileinfo = $this->view->getFileInfo($this->filename);

		$result = \OCP\Share::shareItem('file', $fileinfo['fileid'], \OCP\Share::SHARE_TYPE_USER,
				\Test_Files_Sharing::TEST_FILES_SHARING_API_USER2, 31);

		$this->assertTrue($result);

		$result = \OCP\Share::shareItem('file', $fileinfo['fileid'], \OCP\Share::SHARE_TYPE_GROUP,
				'testGroup', 31);

		$this->assertTrue($result);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$this->assertTrue(\OC\Files\Filesystem::file_exists($this->filename));

		self::loginHelper(self::TEST_FILES_SHARING_API_USER3);
		$this->assertTrue(\OC\Files\Filesystem::file_exists($this->filename));

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		\OC\Files\Filesystem::unlink($this->filename);
		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		// both group share and user share should be gone
		$this->assertFalse(\OC\Files\Filesystem::file_exists($this->filename));

		// for user3 nothing should change
		self::loginHelper(self::TEST_FILES_SHARING_API_USER3);
		$this->assertTrue(\OC\Files\Filesystem::file_exists($this->filename));
	}

	/**
	 * if a file was shared as group share and as individual share they should be grouped
	 */
	public function testGroupingOfShares() {

		$fileinfo = $this->view->getFileInfo($this->filename);

		$result = \OCP\Share::shareItem('file', $fileinfo['fileid'], \OCP\Share::SHARE_TYPE_GROUP,
				\Test_Files_Sharing::TEST_FILES_SHARING_API_GROUP1, \OCP\Constants::PERMISSION_READ);

		$this->assertTrue($result);

		$result = \OCP\Share::shareItem('file', $fileinfo['fileid'], \OCP\Share::SHARE_TYPE_USER,
				\Test_Files_Sharing::TEST_FILES_SHARING_API_USER2, \OCP\Constants::PERMISSION_UPDATE);

		$this->assertTrue($result);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);

		$result = \OCP\Share::getItemSharedWith('file', null);

		$this->assertTrue(is_array($result));

		// test should return exactly one shares created from testCreateShare()
		$this->assertSame(1, count($result));

		$share = reset($result);
		$this->assertSame(\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_UPDATE, $share['permissions']);

		\OC\Files\Filesystem::rename($this->filename, $this->filename . '-renamed');

		$result = \OCP\Share::getItemSharedWith('file', null);

		$this->assertTrue(is_array($result));

		// test should return exactly one shares created from testCreateShare()
		$this->assertSame(1, count($result));

		$share = reset($result);
		$this->assertSame(\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_UPDATE, $share['permissions']);
		$this->assertSame($this->filename . '-renamed', $share['file_target']);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		// unshare user share
		$result = \OCP\Share::unshare('file', $fileinfo['fileid'], \OCP\Share::SHARE_TYPE_USER,
				\Test_Files_Sharing::TEST_FILES_SHARING_API_USER2);
		$this->assertTrue($result);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);

		$result = \OCP\Share::getItemSharedWith('file', null);

		$this->assertTrue(is_array($result));

		// test should return the remaining group share
		$this->assertSame(1, count($result));

		$share = reset($result);
		// only the group share permissions should be available now
		$this->assertSame(\OCP\Constants::PERMISSION_READ, $share['permissions']);
		$this->assertSame($this->filename . '-renamed', $share['file_target']);

	}

	/**
	 * user1 share file to a group and to a user2 in the same group. Then user2
	 * unshares the file from self. Afterwards user1 should no longer see the
	 * single user share to user2. If he re-shares the file to user2 the same target
	 * then the group share should be used to group the item
	 */
	public function testShareAndUnshareFromSelf() {
		$fileinfo = $this->view->getFileInfo($this->filename);

		// share the file to group1 (user2 is a member of this group) and explicitely to user2
		\OCP\Share::shareItem('file', $fileinfo['fileid'], \OCP\Share::SHARE_TYPE_GROUP, self::TEST_FILES_SHARING_API_GROUP1, \OCP\Constants::PERMISSION_ALL);
		\OCP\Share::shareItem('file', $fileinfo['fileid'], \OCP\Share::SHARE_TYPE_USER, self::TEST_FILES_SHARING_API_USER2, \OCP\Constants::PERMISSION_ALL);

		// user1 should have to shared files
		$shares = \OCP\Share::getItemsShared('file');
		$this->assertSame(2, count($shares));

		// user2 should have two files "welcome.txt" and the shared file,
		// both the group share and the single share of the same file should be
		// grouped to one file
		\Test_Files_Sharing::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$dirContent = \OC\Files\Filesystem::getDirectoryContent('/');
		$this->assertSame(2, count($dirContent));
		$this->verifyDirContent($dirContent, array('welcome.txt', ltrim($this->filename, '/')));

		// now user2 deletes the share (= unshare from self)
		\OC\Files\Filesystem::unlink($this->filename);

		// only welcome.txt should exists
		$dirContent = \OC\Files\Filesystem::getDirectoryContent('/');
		$this->assertSame(1, count($dirContent));
		$this->verifyDirContent($dirContent, array('welcome.txt'));

		// login as user1...
		\Test_Files_Sharing::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		// ... now user1 should have only one shared file, the group share
		$shares = \OCP\Share::getItemsShared('file');
		$this->assertSame(1, count($shares));

		// user1 shares a gain the file directly to user2
		\OCP\Share::shareItem('file', $fileinfo['fileid'], \OCP\Share::SHARE_TYPE_USER, self::TEST_FILES_SHARING_API_USER2, \OCP\Constants::PERMISSION_ALL);

		// user2 should see again welcome.txt and the shared file
		\Test_Files_Sharing::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$dirContent = \OC\Files\Filesystem::getDirectoryContent('/');
		$this->assertSame(2, count($dirContent));
		$this->verifyDirContent($dirContent, array('welcome.txt', ltrim($this->filename, '/')));


	}

	/**
	 * @param OC\Files\FileInfo[] $content
	 * @param string[] $expected
	 */
	public function verifyDirContent($content, $expected) {
		foreach ($content as $c) {
			if (!in_array($c['name'], $expected)) {
				$this->assertTrue(false, "folder should only contain '" . implode(',', $expected) . "', found: " .$c['name']);
			}
		}
	}

	public function testShareWithDifferentShareFolder() {

		$fileinfo = $this->view->getFileInfo($this->filename);
		$folderinfo = $this->view->getFileInfo($this->folder);

		$fileShare = \OCP\Share::shareItem('file', $fileinfo['fileid'], \OCP\Share::SHARE_TYPE_USER,
				self::TEST_FILES_SHARING_API_USER2, 31);
		$this->assertTrue($fileShare);

		\OCA\Files_Sharing\Helper::setShareFolder('/Shared/subfolder');

		$folderShare = \OCP\Share::shareItem('folder', $folderinfo['fileid'], \OCP\Share::SHARE_TYPE_USER,
				self::TEST_FILES_SHARING_API_USER2, 31);
		$this->assertTrue($folderShare);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);

		$this->assertTrue(\OC\Files\Filesystem::file_exists($this->filename));
		$this->assertTrue(\OC\Files\Filesystem::file_exists('/Shared/subfolder/' . $this->folder));

		//cleanup
		\OC::$server->getConfig()->deleteSystemValue('share_folder');
	}

	public function testShareWithGroupUniqueName() {
		$this->loginHelper(self::TEST_FILES_SHARING_API_USER1);
		\OC\Files\Filesystem::file_put_contents('test.txt', 'test');

		$fileInfo = \OC\Files\Filesystem::getFileInfo('test.txt');

		$this->assertTrue(
				\OCP\Share::shareItem('file', $fileInfo['fileid'], \OCP\Share::SHARE_TYPE_GROUP, self::TEST_FILES_SHARING_API_GROUP1, 23)
		);

		$this->loginHelper(self::TEST_FILES_SHARING_API_USER2);

		$items = \OCP\Share::getItemsSharedWith('file');
		$this->assertSame('/test.txt' ,$items[0]['file_target']);
		$this->assertSame(23, $items[0]['permissions']);
		
		\OC\Files\Filesystem::rename('test.txt', 'new test.txt');

		$items = \OCP\Share::getItemsSharedWith('file');
		$this->assertSame('/new test.txt' ,$items[0]['file_target']);
		$this->assertSame(23, $items[0]['permissions']);
		
		$this->loginHelper(self::TEST_FILES_SHARING_API_USER1);
		\OCP\Share::setPermissions('file', $items[0]['item_source'], $items[0]['share_type'], $items[0]['share_with'], 3);

		$this->loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$items = \OCP\Share::getItemsSharedWith('file');

		$this->assertSame('/new test.txt' ,$items[0]['file_target']);
		$this->assertSame(3, $items[0]['permissions']);
	}

	/**
	 * shared files should never have delete permissions
	 * @dataProvider dataProviderTestFileSharePermissions
	 */
	public function testFileSharePermissions($permission, $expectedPermissions) {

		$fileinfo = $this->view->getFileInfo($this->filename);

		$result = \OCP\Share::shareItem('file', $fileinfo['fileid'], \OCP\Share::SHARE_TYPE_USER,
				\Test_Files_Sharing::TEST_FILES_SHARING_API_USER2, $permission);

		$this->assertTrue($result);

		$result = \OCP\Share::getItemShared('file', null);

		$this->assertTrue(is_array($result));

		// test should return exactly one shares created from testCreateShare()
		$this->assertSame(1, count($result), 'more then one share found');

		$share = reset($result);
		$this->assertSame($expectedPermissions, $share['permissions']);
	}

	public function dataProviderTestFileSharePermissions() {
		$permission1 = \OCP\Constants::PERMISSION_ALL;
		$permission3 = \OCP\Constants::PERMISSION_READ;
		$permission4 = \OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_UPDATE;
		$permission5 = \OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_DELETE;
		$permission6 = \OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_DELETE;

		return array(
			array($permission1, \OCP\Constants::PERMISSION_ALL & ~\OCP\Constants::PERMISSION_DELETE),
			array($permission3, $permission3),
			array($permission4, $permission4),
			array($permission5, $permission3),
			array($permission6, $permission4),
		);
	}

	public function testFileOwner() {

		$fileinfo = $this->view->getFileInfo($this->filename);

		$result = \OCP\Share::shareItem('file', $fileinfo['fileid'], \OCP\Share::SHARE_TYPE_USER,
				\Test_Files_Sharing::TEST_FILES_SHARING_API_USER2, \OCP\Constants::PERMISSION_ALL);

		$this->assertTrue($result);

		$this->loginHelper(\Test_Files_Sharing::TEST_FILES_SHARING_API_USER2);

		$info = \OC\Files\Filesystem::getFileInfo($this->filename);

		$this->assertSame(\Test_Files_Sharing::TEST_FILES_SHARING_API_USER1, $info->getOwner()->getUID());
	}

	/**
	 * @dataProvider dataProviderGetUsersSharingFile
	 *
	 * @param string $groupName name of group to share with
	 * @param bool $includeOwner whether to include the owner in the result
	 * @param bool $includePaths whether to include paths in the result
	 * @param array $expectedResult expected result of the API call
	 */
	public function testGetUsersSharingFile($groupName, $includeOwner, $includePaths, $expectedResult) {

		$fileinfo = $this->view->getFileInfo($this->folder);

		$result = \OCP\Share::shareItem('folder', $fileinfo['fileid'], \OCP\Share::SHARE_TYPE_GROUP,
				$groupName, \OCP\Constants::PERMISSION_READ);
		$this->assertTrue($result);

		// public share
		$result = \OCP\Share::shareItem('folder', $fileinfo['fileid'], \OCP\Share::SHARE_TYPE_LINK,
				null, \OCP\Constants::PERMISSION_READ);
		$this->assertNotNull($result); // returns the token!

		// owner renames after sharing
		$this->view->rename($this->folder, $this->folder . '_owner_renamed');

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);

		$user2View = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER2 . '/files');
		$user2View->rename($this->folder, $this->folder . '_renamed');

		$ownerPath = $this->folder . '_owner_renamed';
		$owner = self::TEST_FILES_SHARING_API_USER1;

		$result = \OCP\Share::getUsersSharingFile($ownerPath, $owner, $includeOwner, $includePaths);

		// sort users to make sure it matches
		if ($includePaths) {
			ksort($result);
		} else {
			sort($result['users']);
		}
		
		$this->assertEquals(
			$expectedResult,
			$result
		);
	}

	public function dataProviderGetUsersSharingFile() {
		// note: "group" contains user1 (the owner), user2 and user3
		// and self::TEST_FILES_SHARING_API_GROUP1 contains only user2
		return [
			// share with group that contains owner
			[
				'group',
				false,
				false,
				[
					'users' =>
					[
						// because user1 was in group
						self::TEST_FILES_SHARING_API_USER1,
						self::TEST_FILES_SHARING_API_USER2,
						self::TEST_FILES_SHARING_API_USER3,
					],
					'public' => true,
					'remote' => false,
				],
			],
			// share with group that does not contain owner
			[
				self::TEST_FILES_SHARING_API_GROUP1,
				false,
				false,
				[
					'users' =>
					[
						self::TEST_FILES_SHARING_API_USER2,
					],
					'public' => true,
					'remote' => false,
				],
			],
			// share with group that does not contain owner, include owner
			[
				self::TEST_FILES_SHARING_API_GROUP1,
				true,
				false,
				[
					'users' =>
					[
						self::TEST_FILES_SHARING_API_USER1,
						self::TEST_FILES_SHARING_API_USER2,
					],
					'public' => true,
					'remote' => false,
				],
			],
			// include paths, with owner
			[
				'group',
				true,
				true,
				[
					self::TEST_FILES_SHARING_API_USER1 => self::TEST_FOLDER_NAME . '_owner_renamed',
					self::TEST_FILES_SHARING_API_USER2 => self::TEST_FOLDER_NAME . '_renamed',
					self::TEST_FILES_SHARING_API_USER3 => self::TEST_FOLDER_NAME,
				],
			],
			// include paths, group without owner
			[
				self::TEST_FILES_SHARING_API_GROUP1,
				false,
				true,
				[
					self::TEST_FILES_SHARING_API_USER2 => self::TEST_FOLDER_NAME. '_renamed',
				],
			],
			// include paths, include owner, group without owner
			[
				self::TEST_FILES_SHARING_API_GROUP1,
				true,
				true,
				[
					self::TEST_FILES_SHARING_API_USER1 => self::TEST_FOLDER_NAME . '_owner_renamed',
					self::TEST_FILES_SHARING_API_USER2 => self::TEST_FOLDER_NAME . '_renamed',
				],
			],
		];
	}

}
