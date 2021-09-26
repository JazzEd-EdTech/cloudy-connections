<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCP\Push;

/**
 * @since 20.0.0
 */
interface IPushApp {

	/**
	 * Is this push serverice configured and (most likely) available
	 *
	 * @return bool
	 *
	 * @since 20.0.0
	 */
	public function isAvailable(): bool;

	/**
	 * Push the $paylod for $appId to the topic $topic
	 *
	 * @param string $appId
	 * @param string $topic
	 * @param \JsonSerializable $payload
	 *
	 * @since 20.0.0
	 */
	public function push(string $appId, string $topic, \JsonSerializable $payload): void;

	/**
	 * Generate the JWT for $appId and $topic
	 *
	 * @param string $appId
	 * @param string $topic
	 * @return string|null The JWT if the user has access or null if they do not
	 *
	 * @since 20.0.0
	 */
	public function generateJWT(string $appId, string $topic): string;

	/**
	 * Get the endpoint to connect to connect to
	 *
	 * @param string $appId
	 * @param string $topic
	 * @return string
	 *
	 * @since 20.0.0
	 */
	public function getEndpoint(string $appId, string $topic): string;
}
