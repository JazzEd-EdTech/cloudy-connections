<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2017, ownCloud GmbH
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


namespace OCA\DAV\Tests\unit\Upload;


use OCA\DAV\Upload\ChunkingPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Test\TestCase;
use OCA\DAV\Upload\FutureFile;
use OCA\DAV\Connector\Sabre\Directory;

class ChunkingPluginTest extends TestCase {


	/**
	 * @var \Sabre\DAV\Server | \PHPUnit_Framework_MockObject_MockObject
	 */
	private $server;

	/**
	 * @var \Sabre\DAV\Tree | \PHPUnit_Framework_MockObject_MockObject
	 */
	private $tree;

	/**
	 * @var ChunkingPlugin
	 */
	private $plugin;
	/** @var RequestInterface | \PHPUnit_Framework_MockObject_MockObject */
	private $request;
	/** @var ResponseInterface | \PHPUnit_Framework_MockObject_MockObject */
	private $response;

	public function setUp() {
		parent::setUp();

		$this->server = $this->getMockBuilder('\Sabre\DAV\Server')
			->disableOriginalConstructor()
			->getMock();
		$this->tree = $this->getMockBuilder('\Sabre\DAV\Tree')
			->disableOriginalConstructor()
			->getMock();

		$this->server->tree = $this->tree;
		$this->plugin = new ChunkingPlugin();

		$this->request = $this->createMock(RequestInterface::class);
		$this->response = $this->createMock(ResponseInterface::class);
		$this->server->httpRequest = $this->request;
		$this->server->httpResponse = $this->response;

		$this->plugin->initialize($this->server);
	}

	public function testBeforeMoveFutureFileSkip() {
		$node = $this->createMock(Directory::class);

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->with('source')
			->will($this->returnValue($node));
		$this->response->expects($this->never())
			->method('setStatus');

		$this->assertNull($this->plugin->beforeMove('source', 'target'));
	}

	public function testBeforeMoveFutureFileSkipNonExisting() {
		$sourceNode = $this->createMock(FutureFile::class);
		$sourceNode->expects($this->once())
			->method('getSize')
			->willReturn(4);

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->with('source')
			->will($this->returnValue($sourceNode));
		$this->tree->expects($this->any())
			->method('nodeExists')
			->with('target')
			->will($this->returnValue(false));
		$this->response->expects($this->never())
			->method('setStatus');
		$this->request->expects($this->once())
			->method('getHeader')
			->with('OC-Total-Length')
			->willReturn(4);

		$this->assertNull($this->plugin->beforeMove('source', 'target'));
	}

	public function testBeforeMoveFutureFileMoveIt() {
		$sourceNode = $this->createMock(FutureFile::class);
		$sourceNode->expects($this->once())
			->method('getSize')
			->willReturn(4);

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->with('source')
			->will($this->returnValue($sourceNode));
		$this->tree->expects($this->any())
			->method('nodeExists')
			->with('target')
			->will($this->returnValue(true));
		$this->tree->expects($this->once())
			->method('move')
			->with('source', 'target');

		$this->response->expects($this->once())
			->method('setHeader')
			->with('Content-Length', '0');
		$this->response->expects($this->once())
			->method('setStatus')
			->with(204);
		$this->request->expects($this->once())
			->method('getHeader')
			->with('OC-Total-Length')
			->willReturn('4');

		$this->assertFalse($this->plugin->beforeMove('source', 'target'));
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\BadRequest
	 * @expectedExceptionMessage Chunks on server do not sum up to 4 but to 3 bytes
	 */
	public function testBeforeMoveSizeIsWrong() {
		$sourceNode = $this->createMock(FutureFile::class);
		$sourceNode->expects($this->once())
			->method('getSize')
			->willReturn(3);

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->with('source')
			->will($this->returnValue($sourceNode));
		$this->request->expects($this->once())
			->method('getHeader')
			->with('OC-Total-Length')
			->willReturn('4');

		$this->assertFalse($this->plugin->beforeMove('source', 'target'));
	}

}
