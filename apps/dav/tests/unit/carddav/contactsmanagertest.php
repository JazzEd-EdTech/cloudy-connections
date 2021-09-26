<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
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

namespace OCA\DAV\Tests\Unit\CardDAV;

use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\ContactsManager;
use OCP\Contacts\IManager;
use Test\TestCase;

class ContactsManagerTest extends TestCase {
	public function test() {
		/** @var IManager | \PHPUnit_Framework_MockObject_MockObject $cm */
		$cm = $this->getMockBuilder('OCP\Contacts\IManager')->disableOriginalConstructor()->getMock();
		$cm->expects($this->exactly(2))->method('registerAddressBook');
		/** @var CardDavBackend | \PHPUnit_Framework_MockObject_MockObject $backEnd */
		$backEnd = $this->getMockBuilder('OCA\DAV\CardDAV\CardDavBackend')->disableOriginalConstructor()->getMock();
		$backEnd->method('getAddressBooksForUser')->willReturn([
				[]
			]);

		$app = new ContactsManager($backEnd);
		$app->setupContactsProvider($cm, 'user01');
	}
}
