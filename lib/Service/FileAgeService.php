<?php

namespace OCA\FileAge\Service;

use OC\DB\Connection;
use OC\DB\ConnectionAdapter;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class FileAgeService {
    private $qb;
    private $dataDirectory;
    private \OCP\IUserManager $userManager;

    public function __construct(IDBConnection $db, Iconfig $config, \OCP\IUserManager $userManager) {
        $this->qb = $db->getQueryBuilder();
        $this->dataDirectory = $config->getSystemValue('datadirectory');
        $this->userManager = $userManager;
    }

    public function getExpiredFilesAndFolders() {
        $result = $this->qb->select('*')
            ->from('activity')
            ->where('expired_at < ' . time())
            ->andWhere('removed_at IS NULL')
            ->execute();
        return $result->fetchAll();
    }

    public function collectFilesAndFolders($expiredFilesAndFolders) {
        $data = ['files' => [], 'folders' => []];
        foreach ($expiredFilesAndFolders as $key => $row) {
            $explodedFiles = explode('/', $row['file']);
            $ext = pathinfo(end($explodedFiles), PATHINFO_EXTENSION);
            if ($ext) {
                $data['files'][] = ['user' => $row['user'], 'relativePath' => $row['file'], 'absolutePath' => $this->generateAbsolutePath($row['user'], $row['file'])];
            } else {
                $data['folders'][] = ['user' => $row['user'], 'relativePath' => $row['file'], 'absolutePath' => $this->generateAbsolutePath($row['user'], $row['file'])];
            }
        }
        return $data;
    }

    public function extractFilesFromFolders($folders) {
        $data = [];
        foreach ($folders as $folder) {
            $files = glob($folder['absolutePath'] . '/*'); // get all file names
            foreach ($files as $key => $file) {
                array_push($data,
                    [
                        'relativePath' => $this->getRelativeFilePathFromFolder($folder['relativePath'], $file),
                        'absolutePath' => $file
                    ]
                );
            }
        }
        return $data;
    }

    public function remove($files) {
        foreach ($files as $file) {
            if (is_file($file['absolutePath'])) {
                unlink($file['absolutePath']);
                $this->qb->update('activity')
                    ->set('removed_at', $this->qb->createNamedParameter(time()))
                    ->where($this->qb->expr()->eq('user', $this->qb->createNamedParameter($file['user'])))
                    ->andWhere($this->qb->expr()->eq('type', $this->qb->createNamedParameter('file_created')))
                    ->andWhere($this->qb->expr()->eq('file', $this->qb->createNamedParameter($file['relativePath'])));
                $this->qb->execute();
            }
        }
    }

    public function removeExpired() {
        $filesAndFoldersCollection = $this->collectFilesAndFolders($this->getExpiredFilesAndFolders());
        $extractedFilesFromFolders = $this->extractFilesFromFolders($filesAndFoldersCollection['folders']);
        $files = array_merge($filesAndFoldersCollection['files'], $extractedFilesFromFolders);
        $this->remove($files);
        $this->scan();
    }

    private function generateAbsolutePath($user, $fileOrFolderPath) {
        return $this->dataDirectory . '/' . $user . '/files' . $fileOrFolderPath;
    }

    private function getRelativeFilePathFromFolder($folderRelativePath, $absolutePath) {
        $explodedAbsolutePath = explode("/", $absolutePath);
        $filename = end($explodedAbsolutePath);
        return $folderRelativePath . '/' . $filename;
    }

    private function scan() {
        $users = $this->userManager->search('');
        foreach ($users as $user) {
            $user = $user->getUID();
            $scanner = new \OC\Files\Utils\Scanner(
                $user,
                new ConnectionAdapter($this->getConnection()),
                \OC::$server->query(IEventDispatcher::class),
                \OC::$server->get(LoggerInterface::class)
            );
            $scanner->scan("/{$user}", true, null);
        }
    }

    private function getConnection() {
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
