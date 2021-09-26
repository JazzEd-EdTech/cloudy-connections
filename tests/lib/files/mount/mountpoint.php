<?php
/**
 * Copyright (c) 2015 Vincent Petry <pvince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Mount;

class MountPoint extends \Test\TestCase {

	public function testGetStorage() {
		$storage = $this->getMock('\OCP\Files\Storage');
		$storage->expects($this->once())
			->method('getId')
			->will($this->returnValue(123));

		$loader = $this->getMock('\OCP\Files\Storage\IStorageFactory');
		$loader->expects($this->once())
			->method('getInstance')
			->will($this->returnValue($storage));

		$mountPoint = new \OC\Files\Mount\MountPoint(
			// just use this because a real class is needed
			'\Test\Files\Mount\MountPoint',
			'/mountpoint',
			null,
			$loader
		);

		$this->assertEquals($storage, $mountPoint->getStorage());
		$this->assertEquals(123, $mountPoint->getStorageId());
		$this->assertEquals('/mountpoint/', $mountPoint->getMountPoint());

		$mountPoint->setMountPoint('another');
		$this->assertEquals('/another/', $mountPoint->getMountPoint());
	}

	public function testInvalidStorage() {
		$loader = $this->getMock('\OCP\Files\Storage\IStorageFactory');
		$loader->expects($this->once())
			->method('getInstance')
			->will($this->throwException(new \Exception('Test storage init exception')));

		$called = false;
		$wrapper = function($mountPoint, $storage) use ($called) {
			$called = true;
		};

		$mountPoint = new \OC\Files\Mount\MountPoint(
			// just use this because a real class is needed
			'\Test\Files\Mount\MountPoint',
			'/mountpoint',
			null,
			$loader
		);

		$this->assertNull($mountPoint->getStorage());
		// call it again to make sure the init code only ran once
		$this->assertNull($mountPoint->getStorage());

		$this->assertNull($mountPoint->getStorageId());

		// wrapping doesn't fail
		$mountPoint->wrapStorage($wrapper);

		$this->assertNull($mountPoint->getStorage());

		// storage wrapper never called
		$this->assertFalse($called);
	}
}
