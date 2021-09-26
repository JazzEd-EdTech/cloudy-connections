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
namespace OCA\DAV\Tests\Unit\Migration;

use OCA\DAV\CalDAV\CalDavBackend;
use OCA\Dav\Migration\CalendarAdapter;
use OCP\ILogger;
use Test\TestCase;

class MigrateCalendarTest extends TestCase {

	public function testMigration() {
		/** @var CalendarAdapter | \PHPUnit_Framework_MockObject_MockObject $adapter */
		$adapter = $this->mockAdapter([
			['share_type' => '1', 'share_with' => 'users', 'permissions' => '31'],
			['share_type' => '2', 'share_with' => 'adam', 'permissions' => '1'],
		]);

		/** @var CalDavBackend | \PHPUnit_Framework_MockObject_MockObject $cardDav */
		$cardDav = $this->getMockBuilder('\OCA\Dav\CalDAV\CalDAVBackend')->disableOriginalConstructor()->getMock();
		$cardDav->expects($this->any())->method('createCalendar')->willReturn(666);
		$cardDav->expects($this->once())->method('createCalendar')->with('principals/users/test01', 'test_contacts');
		$cardDav->expects($this->once())->method('createCalendarObject')->with(666, '63f0dd6c-39d5-44be-9d34-34e7a7441fc2.ics', 'BEGIN:VCARD');
		$cardDav->expects($this->once())->method('updateShares')->with($this->anything(), [
			['href' => 'principal:principals/groups/users', 'readOnly' => false],
			['href' => 'principal:principals/users/adam', 'readOnly' => true]
		]);
		/** @var ILogger $logger */
		$logger = $this->getMockBuilder('\OCP\ILogger')->disableOriginalConstructor()->getMock();

		$m = new \OCA\Dav\Migration\MigrateCalendars($adapter, $cardDav, $logger, null);
		$m->migrateForUser('test01');
	}

	/**
	 * @return \PHPUnit_Framework_MockObject_MockObject
	 */
	private function mockAdapter($shares = [], $calData = 'BEGIN:VCARD') {
		$adapter = $this->getMockBuilder('\OCA\Dav\Migration\CalendarAdapter')
			->disableOriginalConstructor()
			->getMock();
		$adapter->expects($this->any())->method('foreachCalendar')->willReturnCallback(function ($user, \Closure $callBack) {
			$callBack([
				// calendarorder | calendarcolor | timezone | components
				'id' => 0,
				'userid' => $user,
				'displayname' => 'Test Contacts',
				'uri' => 'test_contacts',
				'ctag' => 1234567890,
				'active' => 1,
				'calendarorder' => '0',
				'calendarcolor' => '#b3dc6c',
				'timezone' => null,
				'components' => 'VEVENT,VTODO,VJOURNAL'
			]);
		});
		$adapter->expects($this->any())->method('foreachCalendarObject')->willReturnCallback(function ($addressBookId, \Closure $callBack) use ($calData) {
			$callBack([
				'userid' => $addressBookId,
				'uri' => '63f0dd6c-39d5-44be-9d34-34e7a7441fc2.ics',
				'calendardata' => $calData
			]);
		});
		$adapter->expects($this->any())->method('getShares')->willReturn($shares);
		return $adapter;
	}
}
