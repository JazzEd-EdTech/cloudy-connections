<?php
/**
 * @copyright Copyright (c) 2017 Morris Jobke <hey@morrisjobke.de>
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

$expectedFiles = [
	'.',
	'..',
	'.bowerrc',
	'.codecov.yml',
	'.drone.yml',
	'.git',
	'.gitignore',
	'.gitmodules',
	'.htaccess',
	'.idea',
	'.jshintrc',
	'.lgtm',
	'.mailmap',
	'.mention-bot',
	'.scrutinizer.yml',
	'.tag',
	'.user.ini',
	'3rdparty',
	'apps',
	'AUTHORS',
	'autotest-external.sh',
	'autotest-hhvm.sh',
	'autotest-js.sh',
	'autotest.sh',
	'bower.json',
	'build',
	'buildjsdocs.sh',
	'CHANGELOG.md',
	'composer.json',
	'config',
	'console.php',
	'contribute',
	'CONTRIBUTING.md',
	'COPYING',
	'COPYING-README',
	'core',
	'cron.php',
	'db_structure.xml',
	'index.html',
	'index.php',
	'issue_template.md',
	'l10n',
	'lib',
	'occ',
	'ocs',
	'ocs-provider',
	'public.php',
	'README.md',
	'remote.php',
	'resources',
	'robots.txt',
	'settings',
	'status.php',
	'tests',
	'themes',
	'version.php',
];
$actualFiles = [];

$files = new \DirectoryIterator(__DIR__ . '/..');
foreach ($files as $file) {
	$actualFiles[] = $file->getFilename();
}

$additionalFiles = array_diff($actualFiles, $expectedFiles);
$missingFiles = array_diff($expectedFiles, $actualFiles);

$failed = false;
if (count($additionalFiles) > 0) {
	echo sprintf('ERROR: There were %d additional files:', count($additionalFiles)) . PHP_EOL;
	echo implode(PHP_EOL, $additionalFiles) . PHP_EOL;
	$failed = true;
}
if (count($missingFiles) > 0) {
	echo sprintf('ERROR: There were %d missing files:', count($missingFiles)) . PHP_EOL;
	echo implode(PHP_EOL, $missingFiles) . PHP_EOL;
	$failed = true;
}

if ($failed) {
	echo 'ERROR: Please remove or add those files again or inform the release team about those now files to be included or excluded from the release tar ball.' . PHP_EOL;
	exit(1);
}

echo 'OK: all expected files are present and no additional files are there.' . PHP_EOL;
exit(0);
