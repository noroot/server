<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\UserStatus\Controller;

use OCA\UserStatus\Exception\InvalidClearAtException;
use OCA\UserStatus\Exception\InvalidStatusIconException;
use OCA\UserStatus\Exception\InvalidStatusTypeException;
use OCA\UserStatus\Exception\StatusMessageTooLongException;
use OCA\UserStatus\Service\StatusService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\ILogger;
use OCP\IRequest;

class UserStatusController extends OCSController {

	/** @var string */
	private $userId;

	/** @var ILogger */
	private $logger;

	/** @var StatusService */
	private $service;

	/**
	 * StatusesController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param string $userId
	 * @param ILogger $logger;
	 * @param StatusService $service
	 */
	public function __construct(string $appName,
								IRequest $request,
								string $userId,
								ILogger $logger,
								StatusService $service) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->logger = $logger;
		$this->service = $service;
	}

	/**
	 * @return DataResponse
	 * @throws OCSNotFoundException
	 */
	public function getStatus(): DataResponse {
		try {
			$userStatus = $this->service->findByUserId($this->userId);
		} catch (DoesNotExistException $ex) {
			throw new OCSNotFoundException('No status for the current user');
		}

		return new DataResponse(\get_object_vars($userStatus));
	}

	/**
	 * @param string $statusType
	 * @param string|null $statusIcon
	 * @param string|null $message
	 * @param int|null $clearAt
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 */
	public function setStatus(string $statusType,
							  ?string $statusIcon,
							  ?string $message,
							  ?int $clearAt): DataResponse {
		try {
			return new DataResponse(\get_object_vars($this->service->setStatus($this->userId,
				$statusType,
				$statusIcon,
				$message,
				$clearAt)));
		} catch (InvalidClearAtException $ex) {
			$this->logger->debug('New user-status for "' . $this->userId . '" was rejected due to an invalid clearAt value "' . $clearAt . '"');
			throw new OCSBadRequestException($ex->getMessage(), $ex);
		} catch (InvalidStatusIconException $ex) {
			$this->logger->debug('New user-status for "' . $this->userId . '" was rejected due to an invalid icon value "' . $statusIcon . '"');
			throw new OCSBadRequestException($ex->getMessage(), $ex);
		} catch (InvalidStatusTypeException $ex) {
			$this->logger->debug('New user-status for "' . $this->userId . '" was rejected due to an invalid status type "' . $statusType . '"');
			throw new OCSBadRequestException($ex->getMessage(), $ex);
		} catch (StatusMessageTooLongException $ex) {
			$this->logger->debug('New user-status for "' . $this->userId . '" was rejected due to a too long status message.');
			throw new OCSBadRequestException($ex->getMessage(), $ex);
		}
	}

	/**
	 * @return DataResponse
	 */
	public function clearStatus(): DataResponse {
		$this->service->removeUserStatus($this->userId);
		return new DataResponse([]);
	}
}
