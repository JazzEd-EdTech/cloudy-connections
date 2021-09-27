<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Alejandro Varela <epma01@gmail.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OC;

class RedisFactory {
	public const REDIS_MINIMAL_VERSION = '2.2.5';
	public const REDIS_EXTRA_PARAMETERS_MINIMAL_VERSION = '5.3.0';

	/** @var  \Redis|\RedisCluster */
	private $instance;

	/** @var  SystemConfig */
	private $config;

	/**
	 * RedisFactory constructor.
	 *
	 * @param SystemConfig $config
	 */
	public function __construct(SystemConfig $config) {
		$this->config = $config;
	}

	private function create() {
		$isCluster = in_array('redis.cluster', $this->config->getKeys());
		$config = $isCluster
			? $this->config->getValue('redis.cluster', [])
			: $this->config->getValue('redis', []);

		if (empty($config)) {
			throw new \Exception('Redis config is empty');
		}

		if ($isCluster && !class_exists('RedisCluster')) {
			throw new \Exception('Redis Cluster support is not available');
		}

		if (isset($config['timeout'])) {
			$timeout = $config['timeout'];
		} else {
			$timeout = 0.0;
		}

		if (isset($config['read_timeout'])) {
			$readTimeout = $config['read_timeout'];
		} else {
			$readTimeout = 0.0;
		}

		$auth = null;
		if (isset($config['password']) && $config['password'] !== '') {
			if (isset($config['user']) && $config['user'] !== '') {
				$auth = [$config['user'], $config['password']];
			} else {
				$auth = $config['password'];
			}
		}

		// # TLS support
		// # https://github.com/phpredis/phpredis/issues/1600
		$connectionParameters = $this->getSslContext($config);

		// cluster config
		if ($isCluster) {
			// Support for older phpredis versions not supporting connectionParameters
			if ($connectionParameters !== null) {
				$this->instance = new \RedisCluster(null, $config['seeds'], $timeout, $readTimeout, false, $auth, $connectionParameters);
			} else {
				$this->instance = new \RedisCluster(null, $config['seeds'], $timeout, $readTimeout, false, $auth);
			}

			if (isset($config['failover_mode'])) {
				$this->instance->setOption(\RedisCluster::OPT_SLAVE_FAILOVER, $config['failover_mode']);
			}
		} else {
			$this->instance = new \Redis();

			if (isset($config['host'])) {
				$host = $config['host'];
			} else {
				$host = '127.0.0.1';
			}

			if (isset($config['port'])) {
				$port = $config['port'];
			} elseif ($host[0] !== '/') {
				$port = 6379;
			} else {
				$port = null;
			}

			// Support for older phpredis versions not supporting connectionParameters
			if ($connectionParameters !== null) {
				// Non-clustered redis requires connection parameters to be wrapped inside `stream`
				$connectionParameters = [
					'stream' => $this->getSslContext($config)
				];
				$this->instance->connect($host, $port, $timeout, null, 0, $readTimeout, $connectionParameters);
			} else {
				$this->instance->connect($host, $port, $timeout, null, 0, $readTimeout);
			}


			// Auth if configured
			if ($auth !== null) {
				$this->instance->auth($auth);
			}

			if (isset($config['dbindex'])) {
				$this->instance->select($config['dbindex']);
			}
		}
	}

	/**
	 * Get the ssl context config
	 *
	 * @param Array $config the current config
	 * @return Array|null
	 * @throws \UnexpectedValueException
	 */
	private function getSslContext($config) {
		if (isset($config['ssl_context'])) {
			if (!$this->isConnectionParametersSupported()) {
				throw new \UnexpectedValueException(\sprintf(
					'php-redis extension must be version %s or higher to support ssl context',
					self::REDIS_EXTRA_PARAMETERS_MINIMAL_VERSION
				));
			}
			return $config['ssl_context'];
		}
		return null;
	}

	public function getInstance() {
		if (!$this->isAvailable()) {
			throw new \Exception('Redis support is not available');
		}
		if (!$this->instance instanceof \Redis) {
			$this->create();
		}

		return $this->instance;
	}

	public function isAvailable() {
		return extension_loaded('redis')
		&& version_compare(phpversion('redis'), '2.2.5', '>=');
	}

	/**
	 * Php redis does support configurable extra parameters since version 5.3.0, see: https://github.com/phpredis/phpredis#connect-open.
	 * We need to check if the current version supports extra connection parameters, otherwise the connect method will throw an exception
	 *
	 * @return boolean
	 */
	private function isConnectionParametersSupported(): bool {
		return \extension_loaded('redis') &&
			\version_compare(\phpversion('redis'), self::REDIS_EXTRA_PARAMETERS_MINIMAL_VERSION, '>=');
	}
}
