<?php

/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

use Behat\Behat\Context\Context;

class FilesSharingAppContext implements Context, ActorAwareInterface {

	use ActorAware;

	/**
	 * @return Locator
	 */
	public static function passwordField() {
		return Locator::forThe()->field("password")->
				describedAs("Password field in Authenticate page");
	}

	/**
	 * @return Locator
	 */
	public static function authenticateButton() {
		return Locator::forThe()->id("password-submit")->
				describedAs("Authenticate button in Authenticate page");
	}

	/**
	 * @return Locator
	 */
	public static function wrongPasswordMessage() {
		return Locator::forThe()->xpath("//*[@class = 'warning' and normalize-space() = 'The password is wrong. Try again.']")->
				describedAs("Wrong password message in Authenticate page");
	}

	/**
	 * @return Locator
	 */
	public static function shareMenuButton() {
		return Locator::forThe()->id("share-menutoggle")->
				describedAs("Share menu button in Shared file page");
	}

	/**
	 * @return Locator
	 */
	public static function shareMenu() {
		return Locator::forThe()->id("share-menu")->
				describedAs("Share menu in Shared file page");
	}

	/**
	 * @return Locator
	 */
	public static function downloadItemInShareMenu() {
		return Locator::forThe()->id("download")->
				descendantOf(self::shareMenu())->
				describedAs("Download item in Share menu in Shared file page");
	}

	/**
	 * @return Locator
	 */
	public static function directLinkItemInShareMenu() {
		return Locator::forThe()->id("directLink-container")->
				descendantOf(self::shareMenu())->
				describedAs("Direct link item in Share menu in Shared file page");
	}

	/**
	 * @return Locator
	 */
	public static function saveItemInShareMenu() {
		return Locator::forThe()->id("save")->
				descendantOf(self::shareMenu())->
				describedAs("Save item in Share menu in Shared file page");
	}

	/**
	 * @return Locator
	 */
	public static function textPreview() {
		return Locator::forThe()->css(".text-preview")->
				describedAs("Text preview in Shared file page");
	}

	/**
	 * @When I visit the shared link I wrote down
	 */
	public function iVisitTheSharedLinkIWroteDown() {
		$this->actor->getSession()->visit($this->actor->getSharedNotebook()["shared link"]);
	}

	/**
	 * @When I authenticate with password :password
	 */
	public function iAuthenticateWithPassword($password) {
		$this->actor->find(self::passwordField(), 10)->setValue($password);
		$this->actor->find(self::authenticateButton())->click();
	}

	/**
	 * @When I open the Share menu
	 */
	public function iOpenTheShareMenu() {
		$this->actor->find(self::shareMenuButton(), 10)->click();
	}

	/**
	 * @Then I see that the current page is the Authenticate page for the shared link I wrote down
	 */
	public function iSeeThatTheCurrentPageIsTheAuthenticatePageForTheSharedLinkIWroteDown() {
		PHPUnit_Framework_Assert::assertEquals(
				$this->actor->getSharedNotebook()["shared link"] . "/authenticate",
				$this->actor->getSession()->getCurrentUrl());
	}

	/**
	 * @Then I see that the current page is the shared link I wrote down
	 */
	public function iSeeThatTheCurrentPageIsTheSharedLinkIWroteDown() {
		PHPUnit_Framework_Assert::assertEquals(
				$this->actor->getSharedNotebook()["shared link"],
				$this->actor->getSession()->getCurrentUrl());
	}

	/**
	 * @Then I see that a wrong password for the shared file message is shown
	 */
	public function iSeeThatAWrongPasswordForTheSharedFileMessageIsShown() {
		PHPUnit_Framework_Assert::assertTrue(
				$this->actor->find(self::wrongPasswordMessage(), 10)->isVisible());
	}

	/**
	 * @Then I see that the Share menu is shown
	 */
	public function iSeeThatTheShareMenuIsShown() {
		// Unlike other menus, the Share menu is always present in the DOM, so
		// the element could be found when it was no made visible yet due to the
		// command not having been processed by the browser.
		if (!$this->waitForElementToBeEventuallyShown(
				self::shareMenu(), $timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The Share menu is not visible yet after $timeout seconds");
		}

		PHPUnit_Framework_Assert::assertTrue(
				$this->actor->find(self::downloadItemInShareMenu())->isVisible());
		PHPUnit_Framework_Assert::assertTrue(
				$this->actor->find(self::directLinkItemInShareMenu())->isVisible());
		PHPUnit_Framework_Assert::assertTrue(
				$this->actor->find(self::saveItemInShareMenu())->isVisible());
	}

	/**
	 * @Then I see that the shared file preview shows the text :text
	 */
	public function iSeeThatTheSharedFilePreviewShowsTheText($text) {
		PHPUnit_Framework_Assert::assertContains($text, $this->actor->find(self::textPreview(), 10)->getText());
	}

	private function waitForElementToBeEventuallyShown($elementLocator, $timeout = 10, $timeoutStep = 1) {
		$actor = $this->actor;

		$elementShownCallback = function() use ($actor, $elementLocator) {
			try {
				return $actor->find($elementLocator)->isVisible();
			} catch (NoSuchElementException $exception) {
				return false;
			}
		};

		return Utils::waitFor($elementShownCallback, $timeout, $timeoutStep);
	}

}
