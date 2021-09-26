<?php
/**
 * @author Georg Ehrke <oc.list@georgehrke.com>
 *
 * @copyright Copyright (c) 2017 Georg Ehrke <oc.list@georgehrke.com>
 * @license GNU AGPL version 3 or any later version
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

namespace OCA\DAV\Tests\unit\CalDAV\Search\Xml\Request;

use OCA\DAV\CalDAV\Search\Xml\Request\CalendarSearchReport;
use Sabre\Xml\Reader;
use Test\TestCase;

class CalendarSearchReportTest extends TestCase {

	private $elementMap = [
		'{http://nextcloud.com/ns}calendar-search' =>
			'OCA\\DAV\\CalDAV\\Search\\Xml\\Request\\CalendarSearchReport',
	];

	public function testFoo() {
		$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nc:calendar-search xmlns:nc="http://nextcloud.com/ns" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:d="DAV:">
   <d:prop>
      <d:getetag />
      <c:calendar-data />
   </d:prop>
   <nc:filter>
      <nc:comp-filter name="VEVENT" />
      <nc:comp-filter name="VTODO" />
      <nc:prop-filter name="SUMMARY" />
      <nc:prop-filter name="LOCATION" />
      <nc:prop-filter name="ATTENDEE" />
      <nc:param-filter property="ATTENDEE" name="CN" />
      <nc:search-term>foo</nc:search-term>
   </nc:filter>
   <nc:limit>10</nc:limit>
   <nc:offset>5</nc:offset>
</nc:calendar-search>
XML;

		$result = $this->parse($xml);

		$calendarSearchReport = new CalendarSearchReport();
		$calendarSearchReport->properties = [
			'{DAV:}getetag',
			'{urn:ietf:params:xml:ns:caldav}calendar-data',
		];
		$calendarSearchReport->filters = [
			'comps' => [
				'VEVENT',
				'VTODO'
			],
			'props' => [
				'SUMMARY',
				'LOCATION',
				'ATTENDEE'
			],
			'params' => [
				[
					'property' => 'ATTENDEE',
					'parameter' => 'CN'
				]
			],
			'search-term' => 'foo'
		];
		$calendarSearchReport->limit = 10;
		$calendarSearchReport->offset = 5;

		$this->assertEquals(
			$calendarSearchReport,
			$result['value']
		);
	}

	public function testNoLimitOffset() {
		$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nc:calendar-search xmlns:nc="http://nextcloud.com/ns" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:d="DAV:">
   <d:prop>
      <d:getetag />
      <c:calendar-data />
   </d:prop>
   <nc:filter>
      <nc:comp-filter name="VEVENT" />
      <nc:prop-filter name="SUMMARY" />
      <nc:search-term>foo</nc:search-term>
   </nc:filter>
</nc:calendar-search>
XML;

		$result = $this->parse($xml);

		$calendarSearchReport = new CalendarSearchReport();
		$calendarSearchReport->properties = [
			'{DAV:}getetag',
			'{urn:ietf:params:xml:ns:caldav}calendar-data',
		];
		$calendarSearchReport->filters = [
			'comps' => [
				'VEVENT',
			],
			'props' => [
				'SUMMARY',
			],
			'search-term' => 'foo'
		];
		$calendarSearchReport->limit = null;
		$calendarSearchReport->offset = null;

		$this->assertEquals(
			$calendarSearchReport,
			$result['value']
		);
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\BadRequest
	 * @expectedExceptionMessage {http://nextcloud.com/ns}prop-filter or {http://nextcloud.com/ns}param-filter given without any {http://nextcloud.com/ns}comp-filter
	 */
	public function testRequiresCompFilter() {
		$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nc:calendar-search xmlns:nc="http://nextcloud.com/ns" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:d="DAV:">
   <d:prop>
      <d:getetag />
      <c:calendar-data />
   </d:prop>
   <nc:filter>
      <nc:prop-filter name="SUMMARY" />
      <nc:prop-filter name="LOCATION" />
      <nc:prop-filter name="ATTENDEE" />
      <nc:param-filter property="ATTENDEE" name="CN" />
      <nc:search-term>foo</nc:search-term>
   </nc:filter>
   <nc:limit>10</nc:limit>
   <nc:offset>5</nc:offset>
</nc:calendar-search>
XML;

		$this->parse($xml);
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\BadRequest
	 * @expectedExceptionMessage The {http://nextcloud.com/ns}filter element is required for this request
	 */
	public function testRequiresFilter() {
		$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nc:calendar-search xmlns:nc="http://nextcloud.com/ns" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:d="DAV:">
   <d:prop>
      <d:getetag />
      <c:calendar-data />
   </d:prop>
</nc:calendar-search>
XML;

		$this->parse($xml);
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\BadRequest
	 * @expectedExceptionMessage {http://nextcloud.com/ns}search-term is required for this request
	 */
	public function testNoSearchTerm() {
		$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nc:calendar-search xmlns:nc="http://nextcloud.com/ns" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:d="DAV:">
   <d:prop>
      <d:getetag />
      <c:calendar-data />
   </d:prop>
   <nc:filter>
      <nc:comp-filter name="VEVENT" />
      <nc:comp-filter name="VTODO" />
      <nc:prop-filter name="SUMMARY" />
      <nc:prop-filter name="LOCATION" />
      <nc:prop-filter name="ATTENDEE" />
      <nc:param-filter property="ATTENDEE" name="CN" />
   </nc:filter>
   <nc:limit>10</nc:limit>
   <nc:offset>5</nc:offset>
</nc:calendar-search>
XML;

		$result = $this->parse($xml);
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\BadRequest
	 * @expectedExceptionMessage At least one{http://nextcloud.com/ns}prop-filter or {http://nextcloud.com/ns}param-filter is required for this request
	 */
	public function testCompOnly() {
		$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nc:calendar-search xmlns:nc="http://nextcloud.com/ns" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:d="DAV:">
   <d:prop>
      <d:getetag />
      <c:calendar-data />
   </d:prop>
   <nc:filter>
      <nc:comp-filter name="VEVENT" />
      <nc:comp-filter name="VTODO" />
      <nc:search-term>foo</nc:search-term>
   </nc:filter>
</nc:calendar-search>
XML;

		$result = $this->parse($xml);

		$calendarSearchReport = new CalendarSearchReport();
		$calendarSearchReport->properties = [
			'{DAV:}getetag',
			'{urn:ietf:params:xml:ns:caldav}calendar-data',
		];
		$calendarSearchReport->filters = [
			'comps' => [
				'VEVENT',
				'VTODO'
			],
			'search-term' => 'foo'
		];
		$calendarSearchReport->limit = null;
		$calendarSearchReport->offset = null;

		$this->assertEquals(
			$calendarSearchReport,
			$result['value']
		);
	}

	public function testPropOnly() {
		$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nc:calendar-search xmlns:nc="http://nextcloud.com/ns" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:d="DAV:">
   <d:prop>
      <d:getetag />
      <c:calendar-data />
   </d:prop>
   <nc:filter>
      <nc:comp-filter name="VEVENT" />
      <nc:prop-filter name="SUMMARY" />
      <nc:search-term>foo</nc:search-term>
   </nc:filter>
</nc:calendar-search>
XML;

		$result = $this->parse($xml);

		$calendarSearchReport = new CalendarSearchReport();
		$calendarSearchReport->properties = [
			'{DAV:}getetag',
			'{urn:ietf:params:xml:ns:caldav}calendar-data',
		];
		$calendarSearchReport->filters = [
			'comps' => [
				'VEVENT',
			],
			'props' => [
				'SUMMARY',
			],
			'search-term' => 'foo'
		];
		$calendarSearchReport->limit = null;
		$calendarSearchReport->offset = null;

		$this->assertEquals(
			$calendarSearchReport,
			$result['value']
		);
	}

	public function testParamOnly() {
		$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nc:calendar-search xmlns:nc="http://nextcloud.com/ns" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:d="DAV:">
   <d:prop>
      <d:getetag />
      <c:calendar-data />
   </d:prop>
   <nc:filter>
      <nc:comp-filter name="VEVENT" />
      <nc:param-filter property="ATTENDEE" name="CN" />
      <nc:search-term>foo</nc:search-term>
   </nc:filter>
</nc:calendar-search>
XML;

		$result = $this->parse($xml);

		$calendarSearchReport = new CalendarSearchReport();
		$calendarSearchReport->properties = [
			'{DAV:}getetag',
			'{urn:ietf:params:xml:ns:caldav}calendar-data',
		];
		$calendarSearchReport->filters = [
			'comps' => [
				'VEVENT',
			],
			'params' => [
				[
					'property' => 'ATTENDEE',
					'parameter' => 'CN'
				]
			],
			'search-term' => 'foo'
		];
		$calendarSearchReport->limit = null;
		$calendarSearchReport->offset = null;

		$this->assertEquals(
			$calendarSearchReport,
			$result['value']
		);
	}

	private function parse($xml, array $elementMap = []) {
		$reader = new Reader();
		$reader->elementMap = array_merge($this->elementMap, $elementMap);
		$reader->xml($xml);
		return $reader->parse();
	}
}
