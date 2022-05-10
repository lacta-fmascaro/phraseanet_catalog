<?php

namespace App\Controller;

use App\Service\Api;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use App\Export\ExportFile;
use Symfony\Component\Validator\Constraints\Json;

/**
 * Class ExportController
 * @package App\Controller
 */
class ExportController extends AbstractController
{
    private $_templateDirectory;
    private $_exportDirectory;
    private $_tmpDirectory;

    public function __construct(string $template_directory, string $export_directory, string $tmp_directory)
    {
        $this->_templateDirectory = $template_directory;
        $this->_exportDirectory = $export_directory;
        $this->_tmpDirectory = $tmp_directory;
    }

    /**
     * @Route("/export", name="app_export", methods={"POST"})
     */
    public function exportAction(Request $request, LoggerInterface $logger, Api $api)
    {
        $items = $request->get('items');

        if (is_null($items)) {
            return new JsonResponse(array('error' => 'No file selected'));
        }

        $requestContent = base64_decode($items);

        if ($api->isJson($requestContent)) {

            $objects = json_decode($requestContent, true, JSON_INVALID_UTF8_IGNORE);

            $exportFile = new ExportFile($logger);

            if ($exportFile->load($this->_templateDirectory, $this->_tmpDirectory, $this->_exportDirectory, 'DAM_Catalog2')) {
                //Ajout des fiches une par une
                if ($exportFile != null) {
                    foreach ($objects as $object) {

                        $record = $api->getRecordData($object['record_id'], $object['databox_id']);

                        $exportFile->addSheet($record);
                    }

                    $fileName = $exportFile->save();
                }

                return $this->file($this->_exportDirectory . $fileName);
            }
        } else {
            // Return error
            return new JsonResponse(array('error' => 'Not valid JSON data'));
        }

    }

}
