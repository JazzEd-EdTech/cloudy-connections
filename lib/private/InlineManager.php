<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC;

use OCP\AppFramework\Http\Inline\IInline;
use OCP\AppFramework\QueryException;
use OCP\ILogger;

class InlineManager {
	/** @var ILogger */
	private $logger;

	/** @var array */
	private $js;

	/** @var array */
	private $css;

	public function __construct(ILogger $logger) {
		$this->logger = $logger;
	}

	public function registerJS(string $appName, \Closure $callable) {
		$this->js[$appName] = $callable;
	}

	public function getInlineJS(): array {
		$result = [];
		foreach ($this->js as $js) {
			try {
				$c = $js();
			} catch (QueryException $e) {
				$this->logger->logException($e, [
					'message' => 'InlineManager',
					'level' => ILogger::ERROR,
					'app' => 'core',
				]);
				continue;
			}

			if ($c instanceof IInline) {
				$result[] = $c->getData();
			} else {
				$this->logger->error(get_class($c) . ' is not and instance of IInline', [
					'app' => 'core',
				]);
			}
		}
		return $result;
	}

	public function getInlineJSApps(): array {
		return array_keys($this->js);
	}

	public function registerCSS(string $appName, \Closure $callable) {
		$this->css[$appName] = $callable;
	}

	public function getInlineCSS(): array {

	}

	public function getInlineCSSApps(): array {
		return array_keys($this->css);
	}


}
