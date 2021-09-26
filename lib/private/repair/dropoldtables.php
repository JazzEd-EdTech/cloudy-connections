<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Repair;


use OC\Hooks\BasicEmitter;
use OC\RepairStep;
use OCP\IDBConnection;

class DropOldTables extends BasicEmitter implements RepairStep {

	/** @var IDBConnection */
	protected $connection;

	/**
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * Returns the step's name
	 *
	 * @return string
	 */
	public function getName() {
		return 'Drop old database tables';
	}

	/**
	 * Run repair step.
	 * Must throw exception on error.
	 *
	 * @throws \Exception in case of failure
	 */
	public function run() {
		foreach ($this->oldDatabaseTables() as $tableName) {
			if ($this->connection->tableExists($tableName)){
				$this->emit('\OC\Repair', 'info', [
					sprintf('Table %s has been deleted', $tableName)
				]);
				$this->connection->dropTable($tableName);
			}
		}
	}

	/**
	 * Returns a list of outdated tables which are not used anymore
	 * @return array
	 */
	protected function oldDatabaseTables() {
		return [
			'authtoken',
			'calendar_calendars',
			'calendar_objects',
			'calendar_share_calendar',
			'calendar_share_event',
			'file_map',
			'foldersize',
			'fscache',
			'gallery_sharing',
			'locks',
			'log',
			'media_albums',
			'media_artists',
			'media_sessions',
			'media_songs',
			'media_users',
			'permissions',
			'pictures_images_cache',
			'principalgroups',
			'principals',
			'queuedtasks',
			'sharing',
		];
	}
}
