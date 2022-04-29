<?php

declare(strict_types=1);


/**
 * Circles - Bring cloud-users closer together.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2017
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


namespace OCA\FileAge\Command;

use Exception;
use OC\Core\Command\Base;
use OCA\FileAge\Service\FileAgeService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CirclesCheck
 *
 * @package OCA\Circles\Command
 */
class AgeCheck extends Base {
    /**
     * @var FileAgeService
     */
    private FileAgeService $fileAgeService;

    public function __construct($name = null, FileAgeService $fileAgeService) {
        parent::__construct($name);
        $this->fileAgeService = $fileAgeService;
    }

    protected function configure() {
        parent::configure();
        $this->setName('age:check')
            ->setDescription('Checking your configuration');
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $this->fileAgeService->removeExpired();
        $output->write("Files Successfully Deleted");
        exit();
    }
}
