<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Dao;

trait DaoTrait
{
    /**
     * @var \Pimcore\Model\AbstractModel
     */
    protected $model;

    /**
     * @param \Pimcore\Model\AbstractModel $model
     *
     * @return $this
     */
    public function setModel($model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * @param array $data
     */
    protected function assignVariablesToModel($data)
    {
        $this->model->setValues($data);
    }
}
