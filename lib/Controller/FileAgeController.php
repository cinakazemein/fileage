<?php


namespace OCA\FileAge\Controller;


use OCA\FileAge\Service\FileAgeService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IUserSession;

class FileAgeController extends Controller {
    /**
     * @var IUserSession
     */
    private $userSession;
    /**
     * @var FileAgeService
     */
    private FileAgeService $fileAgeService;

    public function __construct(IUserSession $userSession, FileAgeService $fileAgeService) {
        $this->userSession = $userSession;
        $this->fileAgeService = $fileAgeService;
    }

    /**
     * @param $age
     * @param $fileInfo
     * @return JSONResponse
     * @NoAdminRequired
     */
    public function submit($age, $fileInfo): JSONResponse {
        $selfCreatedFile = $this->fileAgeService->getSelfCreatedFile($this->userSession->getUser()->getUID(), $this->generateFileName($fileInfo));
        if ($selfCreatedFile) {
            $this->fileAgeService->setExpiredAt($selfCreatedFile, $this->userSession->getUser()->getUID(), $this->generateFileName($fileInfo), $age);
            return new JSONResponse(
                [
                    'result' => "folder or file will be removed at specified date",
                ]
            );
        }
        return new JSONResponse(
            [
                'error' => "not found",
            ]
        );
    }

    private function generateFileName($fileInfo): string {
        return !$fileInfo['dir'] ? "/{$fileInfo['name']}" : $fileInfo['dir'] . "/{$fileInfo['name']}";
    }
}
