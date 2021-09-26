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

namespace OCA\Theming;

use OCP\AppFramework\Http\Inline\IInline;
use OCP\ICache;
use OCP\IConfig;

class InlineJS implements IInline {
	/** @var ThemingDefaults */
	private $themingDefaults;
	/** @var IConfig */
	private $config;
	/** @var Util */
	private $util;
	/** @var ICache */
	private $cache;

	public function __construct(IConfig $config, Util $util, ThemingDefaults $themingDefaults, ICache $cache) {
		$this->themingDefaults = $themingDefaults;
		$this->config = $config;
		$this->util = $util;
		$this->cache = $cache;
	}

	function getData(): string {
		$cacheBusterValue = $this->config->getAppValue('theming', 'cachebuster', '0');
		if ($this->cache->hasKey('theming-inline-' . $cacheBusterValue)) {
			return $this->cache->get('theming-inline-' . $cacheBusterValue);
		}
		$responseJS = 'document.addEventListener(\'DOMContentLoaded\', function() {
	window.OCA.Theming = {
		name: ' . json_encode($this->themingDefaults->getName()) . ',
		url: ' . json_encode($this->themingDefaults->getBaseUrl()) . ',
		slogan: ' . json_encode($this->themingDefaults->getSlogan()) . ',
		color: ' . json_encode($this->themingDefaults->getColorPrimary()) . ',
		imprintUrl: ' . json_encode($this->themingDefaults->getImprintUrl()) . ',
		privacyUrl: ' . json_encode($this->themingDefaults->getPrivacyUrl()) . ',
		inverted: ' . json_encode($this->util->invertTextColor($this->themingDefaults->getColorPrimary())) . ',
		cacheBuster: ' . json_encode($cacheBusterValue) . '
	};
});';
		$this->cache->clear('theming-inline-');
		$this->cache->set('theming-inline-' . $cacheBusterValue, $responseJS);
		return $responseJS;
	}
}
