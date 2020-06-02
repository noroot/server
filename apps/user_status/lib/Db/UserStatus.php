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

namespace OCA\UserStatus\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class UserStatus
 *
 * @package OCA\UserStatus\Db
 *
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getStatusType()
 * @method void setStatusType(string $statusType)
 * @method string|null getStatusIcon()
 * @method void setStatusIcon(string|null $statusIcon)
 * @method string|null getMessage()
 * @method void setMessage(string|null $message)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int|null $createdAt)
 * @method int|null getClearAt()
 * @method void setClearAt(int $clearAt)
 */
class UserStatus extends Entity {

	/** @var string */
	public $userId;

	/** @var string */
	public $statusType;

	/** @var string */
	public $statusIcon;

	/** @var string */
	public $message;

	/** @var int */
	public $createdAt;

	/** @var int */
	public $clearAt;

	public function __construct() {
		$this->addType('userId', 'string');
		$this->addType('statusType', 'string');
		$this->addType('statusIcon', 'string');
		$this->addType('message', 'string');
		$this->addType('createdAt', 'int');
		$this->addType('clearAt', 'int');
	}
}
