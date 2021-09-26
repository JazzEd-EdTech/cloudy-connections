<?php declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Robin Appelman <robin@icewind.nl>
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

namespace OC\Files\ObjectStore;

use OCP\Files\ObjectStore\IObjectStore;
use OCP\IDBConnection;

/**
 * Close an reopen the database connection when reading and writing from the object store
 *
 * this prevents php keeping the database connection open and idle for a long time
 */
class DBReOpenWrapper implements IObjectStore {
	/** @var string */
	private $innerClass;
	/** @var IObjectStore */
	private $inner;
	/** @var IDBConnection */
	private $database;

	public function __construct(array $parameters) {
		$class = $parameters['class'];
		$this->innerClass = $class;
		$this->inner = new $class($parameters);
		$this->database = \OC::$server->getDatabaseConnection();
	}

	public function getStorageId() {
		return $this->inner->getStorageId();
	}

	public function readObject($urn) {
		$this->database->close();
		$result = $this->inner->readObject($urn);
		$this->database->connect();
		return $result;
	}

	public function writeObject($urn, $stream) {
		$this->database->close();
		$result = $this->inner->writeObject($urn, $stream);
		$this->database->connect();
		return $result;
	}

	public function deleteObject($urn) {
		$this->inner->deleteObject($urn);
	}

	public function objectExists($urn) {
		return $this->inner->objectExists($urn);
	}


}
