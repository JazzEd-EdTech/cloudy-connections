<?php
declare(strict_types=1);

namespace OC\Console;

/**
 * @copyright Copyright (c) 2020 Daniel Kesselberg <mail@danielkesselberg.de>
 *
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

use OCP\AppFramework\QueryException;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;

class ConsoleCommandLoader implements CommandLoaderInterface {

	private $nameToClass;

	/**
	 * @param string[] $nameToClass Indexed by command names
	 */
	public function __construct(array $nameToClass) {
		$this->nameToClass = $nameToClass;
	}

	/**
	 * {@inheritdoc}
	 */
	public function has($name) {
		return isset($this->nameToClass[$name]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get($name) {
		if (!isset($this->nameToClass[$name])) {
			throw new CommandNotFoundException(sprintf('Command "%s" does not exist.', $name));
		}

		try {
			return \OC::$server->query($this->nameToClass[$name]);
		} catch (QueryException $e) {
			throw new CommandNotFoundException(sprintf('Command "%s" does not exist.', $name));
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function getNames() {
		return array_keys($this->nameToClass);
	}
}
