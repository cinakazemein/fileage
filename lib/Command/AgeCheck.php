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
use OC;
use OC\AppConfig;
use OC\Core\Command\Base;
use OC\Core\Command\InterruptedException;
use OC\DB\Connection;
use OC\DB\ConnectionAdapter;
use OC\ForbiddenException;
use OCA\Files\Command\Scan;
use OCP\Command\ICommand;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class CirclesCheck
 *
 * @package OCA\Circles\Command
 */
class AgeCheck extends Base
{
    /**
     * @var IDBConnection
     */
    private $db;
    /**
     * @var IConfig
     */
    private $config;
    private $dataDirectory;
    /**
     * @var ICommand
     */
    private $command;
    /**
     * @var IUserManager
     */
    private $userManager;

    public function __construct($name = null, IDBConnection $db, IConfig $config, IUserManager $userManager)
    {
        parent::__construct($name);
        $this->db = $db;
        $this->dataDirectory = $config->getSystemValue('datadirectory');
        $this->userManager = $userManager;
    }

    protected function configure()
    {
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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = ['files' => [], 'folders' => []];
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('*')
            ->from('activity')
            ->where('expired_at < ' . time())
            ->andWhere('removed_at IS NULL')
            ->execute();
        $rows = $result->fetchAll();

        foreach ($rows as $key => $row) {
            $explodedFiles = explode('/', $row['file']);
            $ext = pathinfo(end($explodedFiles), PATHINFO_EXTENSION);
            if ($ext) {
                $data['files'][] = ['user' => $row['user'], 'relativePath' => $row['file'], 'absolutePath' => $this->generateAbsolutePath($row['user'], $row['file'])];
            } else {
                $data['folders'][] = ['user' => $row['user'], 'relativePath' => $row['file'], 'absolutePath' => $this->generateAbsolutePath($row['user'], $row['file'])];
            }
        }
        foreach ($data['files'] as $file) {
            if (file_exists($file['absolutePath'])) {
                unlink($file['absolutePath']);
                $qb->update('activity')
                    ->set('removed_at', $qb->createNamedParameter(time()))
                    ->where($qb->expr()->eq('user', $qb->createNamedParameter($file['user'])))
                    ->andWhere($qb->expr()->eq('type', $qb->createNamedParameter('file_created')))
                    ->andWhere($qb->expr()->eq('file', $qb->createNamedParameter($file['relativePath'])));
                $qb->execute();
            }
        }
        foreach ($data['folders'] as $folder) {
            $files = glob($folder['path'] . '/*'); // get all file names
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $fileRelativePath = $this->getRelativeFilePathFromFolder($folder['relativePath'], $file);
                    $qb->update('activity')
                        ->set('removed_at', $qb->createNamedParameter(time()))
                        ->where($qb->expr()->eq('user', $qb->createNamedParameter($folder['user'])))
                        ->andWhere($qb->expr()->eq('type', $qb->createNamedParameter('file_created')))
                        ->andWhere($qb->expr()->eq('file', $qb->createNamedParameter($fileRelativePath)));
                    $qb->execute();
                }
            }
        }
		$users =$this->userManager->search('');
		foreach ($users as $user){
			$user = $user->getUID();
			$scanner = new \OC\Files\Utils\Scanner(
				$user,
				new ConnectionAdapter($this->getConnection()),
				\OC::$server->query(IEventDispatcher::class),
				\OC::$server->get(LoggerInterface::class)
			);
			try {
				$scanner->scan("/{$user}",true, null);
			} catch (ForbiddenException $e) {
				$output->writeln("<error>Home storage for user $user not writable</error>");
				$output->writeln('Make sure you\'re running the scan command only as the user the web server runs as');
			} catch (InterruptedException $e) {
				# exit the function if ctrl-c has been pressed
				$output->writeln('Interrupted by user');
			} catch (NotFoundException $e) {
				$output->writeln('<error>Path not found: ' . $e->getMessage() . '</error>');
			} catch (\Exception $e) {
				$output->writeln('<error>Exception during scan: ' . $e->getMessage() . '</error>');
				$output->writeln('<error>' . $e->getTraceAsString() . '</error>');
			}
		}

        exit();

        /* todo use occ command*/
    }

    private function generateAbsolutePath($user, $fileOrFolderPath)
    {
        return $this->dataDirectory . '/' . $user . '/files' . $fileOrFolderPath;
    }

    private function getRelativeFilePathFromFolder($folderRelativePath, $absolutePath)
    {
        $explodedAbsolutePath = explode("/", $absolutePath);
        $filename = end($explodedAbsolutePath);
        return $folderRelativePath . '/' . $filename;
    }

    private function getConnection()
    {
        $connection = \OC::$server->get(Connection::class);
        try {
            $connection->close();
        } catch (\Exception $ex) {
        }
        while (!$connection->isConnected()) {
            try {
                $connection->connect();
            } catch (\Exception $ex) {
                sleep(60);
            }
        }
        return $connection;
    }
}
