<?php


namespace OCA\FileAge\Controller;


use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IUserSession;

class FileAgeController extends Controller
{
    /** @var IRootFolder */
    private $storage;
    /**
     * @var IUserSession
     */
    private $userSession;
    /**
     * @var IDBConnection
     */
    private $db;

    public function __construct(IRootFolder $storage, IUserSession $userSession, IDBConnection $db)
    {
        $this->storage = $storage;
        $this->userSession = $userSession;
        $this->db = $db;
    }

    /**
     * @param $age
     * @param $fileInfo
     * @return JSONResponse
     * @throws \OCP\DB\Exception
     * @throws \OCP\Files\NotPermittedException
     * @throws \OC\User\NoUserException
     * @NoAdminRequired
     */
    public function submit($age, $fileInfo)
    {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('*')
            ->from('activity')
            ->where($qb->expr()->eq('user', $qb->createNamedParameter($this->userSession->getUser()->getUID())))
            ->andWhere($qb->expr()->eq('type', $qb->createNamedParameter('file_created')))
            ->andWhere($qb->expr()->eq('file', $qb->createNamedParameter($this->generateFileName($fileInfo))))
            ->execute();
        $row = $result->fetch();
        if ($row) {
            $numberOfDays = '+' . $age . ' days';
            $qb->update('activity')
                ->set('expired_at', $qb->createNamedParameter(strtotime($numberOfDays, $row['timestamp'])))
                ->where($qb->expr()->eq('user', $qb->createNamedParameter($this->userSession->getUser()->getUID())))
                ->andWhere($qb->expr()->eq('type', $qb->createNamedParameter('file_created')))
                ->andWhere($qb->expr()->eq('file', $qb->createNamedParameter($this->generateFileName($fileInfo))));
            $qb->execute();
            return new JSONResponse(
                [
                    'result' => "folder or file will be removed at specified date",
                ]
            );
        }
        return new JSONResponse(
            [
                'data' => $fileInfo['name'],
                'error' => "not found",
            ]
        );
    }

    private function generateFileName($fileInfo)
    {
        return !$fileInfo['dir'] ? "/{$fileInfo['name']}" : $fileInfo['dir'] . "/{$fileInfo['name']}";
    }
}
