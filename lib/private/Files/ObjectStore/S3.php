<?php
/**
 * @copyright Copyright (c) 2016 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Files\ObjectStore;

use Icewind\Streams\CallbackWrapper;
use OCP\Files\ObjectStore\IObjectStore;
use OCP\Files\ObjectStore\IObjectStoreMultiPartUpload;

class S3 implements IObjectStore, IObjectStoreMultiPartUpload {
	use S3ConnectionTrait;
	use S3ObjectTrait;

	public function __construct($parameters) {
		$this->parseParams($parameters);
	}

	/**
	 * @return string the container or bucket name where objects are stored
	 * @since 7.0.0
	 */
	public function getStorageId() {
		return $this->id;
	}

	public function initiateMultipartUpload(string $urn): string {
		$upload = $this->getConnection()->createMultipartUpload([
			'Bucket' => $this->bucket,
			'Key' => $urn,
		]);
		$uploadId = $upload->get('UploadId');
		\OC::$server->getMemCacheFactory()->createDistributed('s3')->set('uploadId-' . $urn, $uploadId);
		return $uploadId;
	}

	public function uploadMultipartPart(string $urn, string $uploadId, int $partId, $stream, $size) {
		$result = $this->getConnection()->uploadPart([
			'Body' => $stream,
			'Bucket' => $this->bucket,
			'Key' => $urn,
			'ContentLength' => $size,
			'PartNumber' => $partId,
			'UploadId' => $uploadId,
		]);
		$uploads = \OC::$server->getMemCacheFactory()->createDistributed('s3')->get('uploads-' . $urn);
		$uploads[$partId] = [
			'ETag' => trim($result->get('ETag'), '"'),
			'PartNumber' => $partId,
		];
		\OC::$server->getMemCacheFactory()->createDistributed('s3')->set('uploads-' . $urn, $uploads);
	}

	public function completeMultipartUpload(string $urn, string $uploadId, array $result) {
		$this->getConnection()->completeMultipartUpload([
			'Bucket' => $this->bucket,
			'Key' => $urn,
			'UploadId' => $uploadId,
			'MultipartUpload' => [ 'Parts' => $result ],
		]);
		return $this->getConnection()->headObject([
			'Bucket' => $this->bucket,
			'Key' => $urn,
		]);
	}
}
