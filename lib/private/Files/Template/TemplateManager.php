<?php
/**
 * @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);


namespace OC\Files\Template;

use OC\User\NoUserException;
use OCP\Files\Folder;
use OCP\Files\File;
use OCP\Files\GenericFileException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Template\ITemplateManager;
use OCP\Files\Template\TemplateType;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

class TemplateManager implements ITemplateManager {

	private $types = [];

	private $rootFolder;
	private $previewManager;
	private $config;
	private $l10n;
	private $logger;
	private $userId;

	public function __construct(
		IRootFolder $rootFolder,
		IUserSession $userSession,
		IPreview $previewManager,
		IConfig $config,
		IFactory $l10n,
		LoggerInterface $logger
	) {
		$this->rootFolder = $rootFolder;
		$this->previewManager = $previewManager;
		$this->config = $config;
		$this->l10n = $l10n->get('lib');
		$this->logger = $logger;
		$user = $userSession->getUser();
		$this->userId = $user ? $user->getUID() : null;
	}

	public function registerTemplateType(TemplateType $templateType): void {
		$this->types[] = $templateType;
	}

	public function listMimetypes(): array {
		return array_map(function (TemplateType $entry) {
			return array_merge($entry->jsonSerialize(), [
				'templates' => array_map(function (File $file) {
					return $this->formatFile($file);
				}, $this->getTemplateFiles($entry->getMimetypes()))
			]);
		}, $this->types);
	}

	/**
	 * @param string $filePath
	 * @param string $templatePath
	 * @return array
	 * @throws GenericFileException
	 */
	public function createFromTemplate(string $filePath, string $templatePath = ''): array {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		try {
			$userFolder->get($filePath);
			throw new GenericFileException('File already exists');
		} catch (NotFoundException $e) {}
		try {
			$targetFile = $userFolder->newFile($filePath);
			if ($templatePath !== '') {
				$template = $userFolder->get($templatePath);
				$template->copy($targetFile->getPath());
				// FIXME in order to support custom template creation handling like for Collabora
				// we should check if there is a TemplateType that supports custom handling here and trigger it
			}
			return $this->formatFile($userFolder->get($filePath));
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new GenericFileException('Failed to create file from template');
		}

	}

	/**
	 * @return Folder
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	private function getTemplateFolder(): Node {
		return $this->rootFolder->getUserFolder($this->userId)->get($this->getTemplatePath());
	}

	private function getTemplateFiles(array $mimetypes): array {
		try {
			$userTemplateFolder = $this->getTemplateFolder();
		} catch (\Exception $e) {
			return [];
		}
		$templates = [];
		foreach ($mimetypes as $mimetype) {
			foreach ($userTemplateFolder->searchByMime($mimetype) as $template) {
				$templates[] = $template;
			}
		}
		return $templates;
	}

	/**
	 * @param Node|File $file
	 * @return array
	 * @throws NotFoundException
	 * @throws \OCP\Files\InvalidPathException
	 */
	private function formatFile(Node $file): array {
		return [
			'basename' => $file->getName(),
			'etag' => $file->getEtag(),
			'fileid' => $file->getId(),
			'filename' => $this->rootFolder->getUserFolder($this->userId)->getRelativePath($file->getPath()),
			'lastmod' => $file->getMTime(),
			'mime' => $file->getMimetype(),
			'size' => $file->getSize(),
			'type' => $file->getType(),
			'hasPreview' => $this->previewManager->isAvailable($file)
		];
	}

	public function hasTemplateDirectory(): bool {
		try {
			$this->getTemplateFolder();
			return true;
		} catch (\Exception $e) {}
		return false;
	}

	public function setTemplatePath(string $path): void {
		$this->config->setUserValue($this->userId, 'core', 'templateDirectory', $path);
	}

	public function getTemplatePath(): string {
		return $this->config->getUserValue($this->userId, 'core', 'templateDirectory', $this->l10n->t('Templates') . '/');
	}

	public function initializeTemplateDirectory(string $path = null, string $userId = null): void {
		if ($userId !== null) {
			$this->userId = $userId;
		}
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$templateDirectoryPath = $path ?? $this->l10n->t('Templates') . '/';
		try {
			$userFolder->get($templateDirectoryPath);
		} catch (NotFoundException $e) {
			$folder = $userFolder->newFolder($templateDirectoryPath);
			$folder->newFile('Testtemplate.txt');
		}
		$this->setTemplatePath($templateDirectoryPath);
	}
}
