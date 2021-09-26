<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Http\Client;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IResponse;
use OCP\ICertificateManager;
use OCP\IConfig;

/**
 * Class Client
 *
 * @package OC\Http
 */
class Client implements IClient {
	/** @var GuzzleClient */
	private $client;
	/** @var IConfig */
	private $config;
	/** @var ICertificateManager */
	private $certificateManager;

	/**
	 * @param IConfig $config
	 * @param ICertificateManager $certificateManager
	 * @param GuzzleClient $client
	 */
	public function __construct(
		IConfig $config,
		ICertificateManager $certificateManager,
		GuzzleClient $client
	) {
		$this->config = $config;
		$this->client = $client;
		$this->certificateManager = $certificateManager;
	}

	private function buildRequestOptions(array $options): array {
		$defaults = [
			RequestOptions::PROXY => $this->getProxyUri(),
			RequestOptions::VERIFY => $this->getCertBundle(),
		];

		$options = array_merge($defaults, $options);

		if (!isset($options[RequestOptions::HEADERS]['User-Agent'])) {
			$options[RequestOptions::HEADERS]['User-Agent'] = 'Nextcloud Server Crawler';
		}

		return $options;
	}

	private function getCertBundle(): string {
		if ($this->certificateManager->listCertificates() !== []) {
			return $this->certificateManager->getAbsoluteBundlePath();
		}

		// If the instance is not yet setup we need to use the static path as
		// $this->certificateManager->getAbsoluteBundlePath() tries to instantiiate
		// a view
		if ($this->config->getSystemValue('installed', false)) {
			return $this->certificateManager->getAbsoluteBundlePath(null);
		}

		return \OC::$SERVERROOT . '/resources/config/ca-bundle.crt';
	}

	/**
	 * Get the proxy URI
	 *
	 * @return string|null
	 */
	private function getProxyUri(): ?string {
		$proxyHost = $this->config->getSystemValue('proxy', null);

		if ($proxyHost === null) {
			return null;
		}

		$proxyUserPwd = $this->config->getSystemValue('proxyuserpwd', null);

		if ($proxyUserPwd === null) {
			return $proxyHost;
		}

		return $proxyUserPwd . '@' . $proxyHost;
	}

	/**
	 * Sends a GET request
	 *
	 * @param string $uri
	 * @param array $options Array such as
	 *              'query' => [
	 *                  'field' => 'abc',
	 *                  'other_field' => '123',
	 *                  'file_name' => fopen('/path/to/file', 'r'),
	 *              ],
	 *              'headers' => [
	 *                  'foo' => 'bar',
	 *              ],
	 *              'cookies' => ['
	 *                  'foo' => 'bar',
	 *              ],
	 *              'allow_redirects' => [
	 *                   'max'       => 10,  // allow at most 10 redirects.
	 *                   'strict'    => true,     // use "strict" RFC compliant redirects.
	 *                   'referer'   => true,     // add a Referer header
	 *                   'protocols' => ['https'] // only allow https URLs
	 *              ],
	 *              'save_to' => '/path/to/file', // save to a file or a stream
	 *              'verify' => true, // bool or string to CA file
	 *              'debug' => true,
	 *              'timeout' => 5,
	 * @return IResponse
	 * @throws \Exception If the request could not get completed
	 */
	public function get(string $uri, array $options = []): IResponse {
		$response = $this->client->request('get', $uri, $this->buildRequestOptions($options));
		$isStream = isset($options['stream']) && $options['stream'];
		return new Response($response, $isStream);
	}

	/**
	 * Sends a HEAD request
	 *
	 * @param string $uri
	 * @param array $options Array such as
	 *              'headers' => [
	 *                  'foo' => 'bar',
	 *              ],
	 *              'cookies' => ['
	 *                  'foo' => 'bar',
	 *              ],
	 *              'allow_redirects' => [
	 *                   'max'       => 10,  // allow at most 10 redirects.
	 *                   'strict'    => true,     // use "strict" RFC compliant redirects.
	 *                   'referer'   => true,     // add a Referer header
	 *                   'protocols' => ['https'] // only allow https URLs
	 *              ],
	 *              'save_to' => '/path/to/file', // save to a file or a stream
	 *              'verify' => true, // bool or string to CA file
	 *              'debug' => true,
	 *              'timeout' => 5,
	 * @return IResponse
	 * @throws \Exception If the request could not get completed
	 */
	public function head(string $uri, array $options = []): IResponse {
		$response = $this->client->request('head', $uri, $this->buildRequestOptions($options));
		return new Response($response);
	}

	/**
	 * Sends a POST request
	 *
	 * @param string $uri
	 * @param array $options Array such as
	 *              'body' => [
	 *                  'field' => 'abc',
	 *                  'other_field' => '123',
	 *                  'file_name' => fopen('/path/to/file', 'r'),
	 *              ],
	 *              'headers' => [
	 *                  'foo' => 'bar',
	 *              ],
	 *              'cookies' => ['
	 *                  'foo' => 'bar',
	 *              ],
	 *              'allow_redirects' => [
	 *                   'max'       => 10,  // allow at most 10 redirects.
	 *                   'strict'    => true,     // use "strict" RFC compliant redirects.
	 *                   'referer'   => true,     // add a Referer header
	 *                   'protocols' => ['https'] // only allow https URLs
	 *              ],
	 *              'save_to' => '/path/to/file', // save to a file or a stream
	 *              'verify' => true, // bool or string to CA file
	 *              'debug' => true,
	 *              'timeout' => 5,
	 * @return IResponse
	 * @throws \Exception If the request could not get completed
	 */
	public function post(string $uri, array $options = []): IResponse {
		if (isset($options['body']) && is_array($options['body'])) {
			$options['form_params'] = $options['body'];
			unset($options['body']);
		}
		$response = $this->client->request('post', $uri, $this->buildRequestOptions($options));
		return new Response($response);
	}

	/**
	 * Sends a PUT request
	 *
	 * @param string $uri
	 * @param array $options Array such as
	 *              'body' => [
	 *                  'field' => 'abc',
	 *                  'other_field' => '123',
	 *                  'file_name' => fopen('/path/to/file', 'r'),
	 *              ],
	 *              'headers' => [
	 *                  'foo' => 'bar',
	 *              ],
	 *              'cookies' => ['
	 *                  'foo' => 'bar',
	 *              ],
	 *              'allow_redirects' => [
	 *                   'max'       => 10,  // allow at most 10 redirects.
	 *                   'strict'    => true,     // use "strict" RFC compliant redirects.
	 *                   'referer'   => true,     // add a Referer header
	 *                   'protocols' => ['https'] // only allow https URLs
	 *              ],
	 *              'save_to' => '/path/to/file', // save to a file or a stream
	 *              'verify' => true, // bool or string to CA file
	 *              'debug' => true,
	 *              'timeout' => 5,
	 * @return IResponse
	 * @throws \Exception If the request could not get completed
	 */
	public function put(string $uri, array $options = []): IResponse {
		$response = $this->client->request('put', $uri, $this->buildRequestOptions($options));
		return new Response($response);
	}

	/**
	 * Sends a DELETE request
	 *
	 * @param string $uri
	 * @param array $options Array such as
	 *              'body' => [
	 *                  'field' => 'abc',
	 *                  'other_field' => '123',
	 *                  'file_name' => fopen('/path/to/file', 'r'),
	 *              ],
	 *              'headers' => [
	 *                  'foo' => 'bar',
	 *              ],
	 *              'cookies' => ['
	 *                  'foo' => 'bar',
	 *              ],
	 *              'allow_redirects' => [
	 *                   'max'       => 10,  // allow at most 10 redirects.
	 *                   'strict'    => true,     // use "strict" RFC compliant redirects.
	 *                   'referer'   => true,     // add a Referer header
	 *                   'protocols' => ['https'] // only allow https URLs
	 *              ],
	 *              'save_to' => '/path/to/file', // save to a file or a stream
	 *              'verify' => true, // bool or string to CA file
	 *              'debug' => true,
	 *              'timeout' => 5,
	 * @return IResponse
	 * @throws \Exception If the request could not get completed
	 */
	public function delete(string $uri, array $options = []): IResponse {
		$response = $this->client->request('delete', $uri, $this->buildRequestOptions($options));
		return new Response($response);
	}

	/**
	 * Sends a options request
	 *
	 * @param string $uri
	 * @param array $options Array such as
	 *              'body' => [
	 *                  'field' => 'abc',
	 *                  'other_field' => '123',
	 *                  'file_name' => fopen('/path/to/file', 'r'),
	 *              ],
	 *              'headers' => [
	 *                  'foo' => 'bar',
	 *              ],
	 *              'cookies' => ['
	 *                  'foo' => 'bar',
	 *              ],
	 *              'allow_redirects' => [
	 *                   'max'       => 10,  // allow at most 10 redirects.
	 *                   'strict'    => true,     // use "strict" RFC compliant redirects.
	 *                   'referer'   => true,     // add a Referer header
	 *                   'protocols' => ['https'] // only allow https URLs
	 *              ],
	 *              'save_to' => '/path/to/file', // save to a file or a stream
	 *              'verify' => true, // bool or string to CA file
	 *              'debug' => true,
	 *              'timeout' => 5,
	 * @return IResponse
	 * @throws \Exception If the request could not get completed
	 */
	public function options(string $uri, array $options = []): IResponse {
		$response = $this->client->request('options', $uri, $this->buildRequestOptions($options));
		return new Response($response);
	}
}
