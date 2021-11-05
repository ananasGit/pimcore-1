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

namespace Pimcore\Bundle\CoreBundle\Command;

use Pimcore\Console\AbstractCommand;
use Pimcore\Logger;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element\Service;
use Pimcore\Model\Search;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SearchBackendReindexCommand extends AbstractCommand
{

    protected $db;

    protected $type;

    protected $types;

    protected $dir;

    protected function configure()
    {
        $this
            ->setName('pimcore:search-backend-reindex')
            ->setAliases(['search-backend-reindex'])
            ->setDescription('Re-indexes the backend search of pimcore')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Type of data to index: asset, document or object')
            ->addOption('dir', 'd', InputOption::VALUE_OPTIONAL, 'Directory within data is to be indexed');
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->db = \Pimcore\Db::get();
        $this->type = trim($input->getOption('type'));
        $this->dir = trim($input->getOption('dir'));

        $allSupportedTypes = ['asset', 'document', 'object'];
        if ($this->type && !in_array($this->type, $allSupportedTypes)) {
            throw new \Exception('Invalid type "'.$this->type.'". Supported types are: ' . implode(', ', $allSupportedTypes));
        }
        $types = $this->type ? [$this->type] : ['asset', 'document', 'object'];

        $this->clearIndex();

        $startTime = time();
        $elementsPerLoop = 100;

        foreach ($types as $type) {
            $baseClass = Service::getBaseClassNameForElement($type);
            $listClassName = '\\Pimcore\\Model\\' . $baseClass . '\\Listing';
            $list = new $listClassName();
            if (method_exists($list, 'setUnpublished')) {
                $list->setUnpublished(true);
            }

            if (method_exists($list, 'setObjectTypes')) {
                $list->setObjectTypes([
                    DataObject\AbstractObject::OBJECT_TYPE_OBJECT,
                    DataObject\AbstractObject::OBJECT_TYPE_FOLDER,
                    DataObject\AbstractObject::OBJECT_TYPE_VARIANT,
                ]);
            }

            $elementsTotal = $list->getTotalCount();

            for ($i = 0; $i < (ceil($elementsTotal / $elementsPerLoop)); $i++) {
                $list->setLimit($elementsPerLoop);
                $list->setOffset($i * $elementsPerLoop);

                $this->output->writeln('Processing ' .$type . ': ' . ($list->getOffset() + $elementsPerLoop) . '/' . $elementsTotal);

                $elements = $list->load();

                foreach ($elements as $element) {
                    try {
                        //process page count, if not exists
                        if ($element instanceof Asset\Document && $element->getCustomSetting('document_page_count')) {
                            $element->processPageCount();
                        }

                        if ($this->dir && !str_starts_with($element->getFullPath(), $this->dir)) {
                            continue;
                        }

                        $searchEntry = Search\Backend\Data::getForElement($element);
                        if ($searchEntry instanceof Search\Backend\Data and $searchEntry->getId() instanceof Search\Backend\Data\Id) {
                            $searchEntry->setDataFromElement($element);
                        } else {
                            $searchEntry = new Search\Backend\Data($element);
                        }

                        $searchEntry->save();
                    } catch (\Exception $e) {
                        Logger::err($e);
                    }
                }
                \Pimcore::collectGarbage();
            }
        }

        $this->db->query('OPTIMIZE TABLE search_backend_data;');

        $timeSpent = round(time() - $startTime);
        $humanReadableTimeSpent = sprintf('%02d:%02d:%02d', ($timeSpent/3600),($timeSpent/60%60), $timeSpent%60);

        echo PHP_EOL;
        $output->writeln(" <bg=green;options=bold> Done in $humanReadableTimeSpent </>");
        echo PHP_EOL;

        return 0;
    }

    private function clearIndex(): void
    {
        if (!$this->type && !$this->dir) {
            $this->db->query('TRUNCATE `search_backend_data`;');
            return;
        }

        $where = [];

        if ($this->type) {
            $where[] = "`type` = '{$this->type}'";
        }

        if ($this->dir) {
            $where[] = "`fullpath` LIKE '{$this->dir}%'";
        }

        $stringWhere = implode(' AND ', $where);
        $query = "DELETE FROM `search_backend_data` WHERE $stringWhere;";

        $this->db->query($query);
    }
}
