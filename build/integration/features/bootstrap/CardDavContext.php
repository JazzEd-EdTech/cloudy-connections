<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

require __DIR__ . '/../../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Message\ResponseInterface;

class CardDavContext implements \Behat\Behat\Context\Context {
	/** @var string  */
	private $baseUrl;
	/** @var Client */
	private $client;
	/** @var ResponseInterface */
	private $response;
	/** @var string */
	private $responseXml = '';

	/**
	 * @param string $baseUrl
	 */
	public function __construct($baseUrl) {
		$this->baseUrl = $baseUrl;

		// in case of ci deployment we take the server url from the environment
		$testServerUrl = getenv('TEST_SERVER_URL');
		if ($testServerUrl !== false) {
			$this->baseUrl = substr($testServerUrl, 0, -5);
		}
	}

	/** @BeforeScenario */
	public function tearUpScenario() {
		$this->client = new Client();
		$this->responseXml = '';
	}


	/** @AfterScenario */
	public function afterScenario() {
		$davUrl = $this->baseUrl . '/remote.php/dav/addressbooks/users/admin/MyAddressbook';
		try {
			$this->client->delete(
				$davUrl,
				[
					'auth' => [
						'admin',
						'admin',
					],
				]
			);
		} catch (\GuzzleHttp\Exception\ClientException $e) {}
	}


	/**
	 * @When :user requests addressbook :addressBook with statuscode :statusCode
	 */
	public function requestsAddressbookWithStatuscode($user, $addressBook, $statusCode) {
		$davUrl = $this->baseUrl . '/remote.php/dav/addressbooks/users/'.$addressBook;

		$password = ($user === 'admin') ? 'admin' : '123456';
		try {
			$this->response = $this->client->get(
				$davUrl,
				[
					'auth' => [
						$user,
						$password,
					],
				]
			);
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$this->response = $e->getResponse();
		}

		if((int)$statusCode !== $this->response->getStatusCode()) {
			throw new \Exception(
				sprintf(
					'Expected %s got %s',
					(int)$statusCode,
					$this->response->getStatusCode()
				)
			);
		}

		$body = $this->response->getBody()->getContents();
		if(substr($body, 0, 1) === '<') {
			$reader = new Sabre\Xml\Reader();
			$reader->xml($body);
			$this->responseXml = $reader->parse();
		}
	}

	/**
	 * @Given :user creates an addressbook named :addressBook with statuscode :statusCode
	 */
	public function createsAnAddressbookNamedWithStatuscode($user, $addressBook, $statusCode) {
		$davUrl = $this->baseUrl . '/remote.php/dav/addressbooks/users/'.$user.'/'.$addressBook;
		$password = ($user === 'admin') ? 'admin' : '123456';

		$request = $this->client->createRequest(
			'MKCOL',
			$davUrl,
			[
				'body' => '<d:mkcol xmlns:c="urn:ietf:params:xml:ns:caldav"
              xmlns:card="urn:ietf:params:xml:ns:carddav"
              xmlns:cs="http://calendarserver.org/ns/"
              xmlns:d="DAV:">
    <d:set>
      <d:prop>
        <d:resourcetype>
            <d:collection />,<card:addressbook />
          </d:resourcetype>,<d:displayname>'.$addressBook.'</d:displayname>
      </d:prop>
    </d:set>
  </d:mkcol>',
				'auth' => [
					$user,
					$password,
				],
				'headers' => [
					'Content-Type' => 'application/xml;charset=UTF-8',
				],
			]
		);

		$this->response = $this->client->send($request);

		if($this->response->getStatusCode() !== (int)$statusCode) {
			throw new \Exception(
				sprintf(
					'Expected %s got %s',
					(int)$statusCode,
					$this->response->getStatusCode()
				)
			);
		}
	}

	/**
	 * @When The CardDAV exception is :message
	 */
	public function theCarddavExceptionIs($message) {
		$result = $this->responseXml['value'][0]['value'];

		if($message !== $result) {
			throw new \Exception(
				sprintf(
					'Expected %s got %s',
					$message,
					$result
				)
			);
		}
	}

	/**
	 * @When The CardDAV error message is :arg1
	 */
	public function theCarddavErrorMessageIs($message) {
		$result = $this->responseXml['value'][1]['value'];

		if($message !== $result) {
			throw new \Exception(
				sprintf(
					'Expected %s got %s',
					$message,
					$result
				)
			);
		}
	}

}
