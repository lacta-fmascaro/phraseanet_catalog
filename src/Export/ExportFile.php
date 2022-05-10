<?php

namespace App\Export;

use \ZipArchive;
use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;
use \FilesystemIterator;
use \DomDocument;
use \DOMXPath;
use \DOMElement;

class ExportFile
{
    #region Variables
    private $fileName;
    private $tempPath;
    private $exportPath;
    private $logger;
    private $rework;
    private $reworkColors;
    private $manifestPath;
    private $manifestDoc;
    private $contentPath;
    private $contentDoc;
    private $contentXPath;
    private $stylesNode;
    private $presentationNode;
    private $settingsNode;
    private $templatePageNode;
    private $pageNode;
    private $pageCount;
    private $nodesTrashbin;
    private $_object;
    private $_tmpPath;

    public function __construct($logger)
    {
        $this->logger = $logger;
        $this->rework = array('Go', 'Rework', 'No go');
        $this->reworkColors = array('#00b050', '#ff7f00', '#ff0000');
        $this->pageCount = 0;
    }

    function load($templatePath, $tempPath, $exportPath, $filename)
    {
        // Génération du nom de fichier
        $templateFile = $templatePath . $filename . '.odp';
        $this->fileName = strtolower(str_replace(' ', '_', $filename)) . '-' . date('YmdHis');
        // Chemin d'export
        if (!file_exists($exportPath)) {
            mkdir($exportPath);
        }
        // Chemin temporaire
        if (!file_exists($tempPath)) {
            mkdir($tempPath);
        }
        $this->_tmpPath = $tempPath;
        $this->exportPath = $exportPath . $this->fileName . '.odp';
        // Chemin d'import et dossier de travail
        $this->tempPath = $tempPath . $this->fileName;
        if ($this->unzip($templateFile, $this->tempPath)) {
            $this->manifestPath = $this->tempPath . '/META-INF/manifest.xml';
            $this->manifestDoc = $this->loadXMLFile($this->manifestPath);
            $this->contentPath = $this->tempPath . '/content.xml';
            $this->contentDoc = $this->loadXMLFile($this->contentPath);
            $this->contentXPath = $this->getXPath($this->contentDoc);
            $this->stylesNode = $this->contentDoc->getElementsByTagName('automatic-styles')->item(0);
            $this->presentationNode = $this->contentDoc->getElementsByTagName('presentation')->item(0);
            $this->settingsNode = $this->contentDoc->getElementsByTagName('settings')->item(0);
            $this->templatePageNode = $this->contentDoc->getElementsByTagName('page')->item(0);
            return true;
        }
        return false;
    }

    function addSheet($object)
    {
        $this->_object = $object;

        // Création de la page courante
        $this->createPage();
        // Remplacement des tags
        $this->replaceTextTags();
        // Remplacement de l'image
        $this->replaceImage();
        // Sauvegarde de la page
        $this->savePage();
    }

    function replaceTextTags()
    {
        $spans = $this->contentXPath->query(".//text:span[contains(., '[')]", $this->pageNode);

        $this->initNodesTrashbin();
        foreach ($spans as $span) {
            $text = $span->textContent;

            $textToSearch = str_replace(array('[', ']'), '', $text);

            if (isset($this->_object[$textToSearch])) {

                if (strpos($this->_object[$textToSearch], "\r\n") !== false) {
                    $this->replaceLineBreak($span, $this->_object[$textToSearch]);
                } else {
                    $span->textContent = $this->_object[$textToSearch];
                }
            } else {
                $span->textContent = '';
            }
        }
        $this->emptyNodesTrashbin();
    }

    function replaceLineBreak($span, $text)
    {
        $p = $span->parentNode;
        if (!empty($p)) {
            $shape = $p->parentNode;
            $next = $p->nextSibling;
            $lines = explode("\r\n", $text);
            foreach ($lines as $key => $line) {
                $newP = $p->cloneNode(true);
                $newSpan = $newP->lastChild;
                $newSpan->textContent = $line;
                if ($key !== 0) {
                    $newP->firstChild->textContent = null;
                }
                $shape->insertBefore($newP, $next);
            }
            $this->addNodeToTrashbin($p);
        }
    }

    function initNodesTrashbin()
    {
        $this->nodesTrashbin = array();
    }

    function addNodeToTrashbin($node)
    {
        array_push($this->nodesTrashbin, $node);
    }

    function emptyNodesTrashbin()
    {
        foreach ($this->nodesTrashbin as $node) {
            $parent = $node->parentNode;
            $parent->removeChild($node);
        }
    }

    function replaceImage()
    {
        $image = $this->contentXPath->query(".//draw:frame[./svg:title='sheet_image']/draw:image", $this->pageNode)->item(0);

        $imageUrl = '';

        if (!strpos($this->_object['mime_type'], 'image/')) {
            $imageUrl = $this->_object['thumbnail']['permalink']['url'];
        } else {
            if (isset($this->_object['GdsnPermalinkUrl'])) {
                $imageUrl = $this->_object['GdsnPermalinkUrl'];
            }
        }

        if ($imageUrl != "") {

            $content = false;

            try {
                $content = file_get_contents($imageUrl);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }

            if ($content) {
                $file = $this->_tmpPath . 'image_' . date('YmdHis') . '.' . pathinfo($imageUrl, PATHINFO_EXTENSION);

                file_put_contents($file, $content);

                if (file_exists($file)) {
                    // Image path
                    $path_parts = pathinfo($file);
                    $mediaPath = 'media/' . $path_parts['basename'];
                    copy($file, $this->tempPath . '/' . $mediaPath);
                    $image->setAttribute('xlink:href', $mediaPath);
                    // Keep ratio
                    $frame = $image->parentNode;
                    $frameWidth = floatval(str_replace('in', '', $frame->getAttribute('svg:width')));
                    $frameHeight = floatval(str_replace('in', '', $frame->getAttribute('svg:height')));
                    $frameRatio = $frameWidth / $frameHeight;
                    list($imageWidth, $imageHeight) = getimagesize($file);
                    $imageRatio = $imageWidth / $imageHeight;

                    $keepWidth = ($imageRatio >= 1 && $imageRatio >= $frameRatio) || ($imageRatio <= 1 && $imageRatio >= $frameRatio);
                    if ($keepWidth) {
                        // Resize height
                        $newHeight = $frameWidth / $imageRatio;
                        $frame->setAttribute('svg:height', strval(round($newHeight, 5)) . 'in');
                    } else {
                        // Resize width
                        $newWidth = $frameHeight * $imageRatio;
                        $frame->setAttribute('svg:width', strval(round($newWidth, 5)) . 'in');
                    }
                    // Positionning
                    $frameX = floatval(str_replace('in', '', $frame->getAttribute('svg:x')));
                    $frameY = floatval(str_replace('in', '', $frame->getAttribute('svg:y')));
                    if ($keepWidth) {
                        // Center Y
                        $newY = $frameY + ($frameHeight - $newHeight) / 2;
                        $frame->setAttribute('svg:y', strval(round($newY, 5)) . 'in');
                    } else {
                        // Center X
                        $newX = $frameX + ($frameWidth - $newWidth) / 2;
                        $frame->setAttribute('svg:x', strval(round($newX, 5)) . 'in');
                    }

                    unlink($file);
                }
            }
        }
    }

    function unzip($source, $destination)
    {
        $zip = new ZipArchive;
        $res = $zip->open($source);
        if ($res === TRUE) {
            if ($zip->extractTo($destination) === TRUE) {
            } else {
                $err = error_get_last();
                $this->logger->error($destination);
                $this->logger->error('ODPService:UnZip -> extractTo failed : ' . $err["message"]);
            }
            return $zip->close();
        } else {
            $this->logger->error($source);
            $this->logger->error('ODPService:UnZip -> Failed open ODP Archive, code:' . $res);
        }
        return false;
    }

    function loadXMLFile($path)
    {
        $filePath = $path;
        if (file_exists($filePath)) {
            $doc = new DomDocument();
            $doc->load($filePath);
            return $doc;
        }
        return null;
    }

    function getXPath($doc)
    {
        return new DOMXPath($doc);
    }

    function zip($source, $destination)
    {
        if (!extension_loaded('zip') || !file_exists($source)) {
            return false;
        }
        $zip = new ZipArchive();
        if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
            return false;
        }
        $source = str_replace('\\', '/', realpath($source));
        if (is_dir($source) === true) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file = str_replace('\\', '/', $file);
                // Ignore "." and ".." folders
                if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..'))) {
                    continue;
                }
                $fileReal = realpath($file);
                if (is_dir($fileReal) === true) {
                    $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                } else if (is_file($fileReal) === true) {
                    $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                }
            }
        } else if (is_file($source) === true) {
            $zip->addFromString(basename($source), file_get_contents($source));
        }
        return $zip->close();
    }

    function rmdir($dir)
    {
        $it = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $it = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            if ($file->isDir()) rmdir($file->getPathname());
            else unlink($file->getPathname());
        }
        rmdir($dir);
    }

    function save()
    {
        // Suppression de la page template
        $this->presentationNode->removeChild($this->templatePageNode);
        // Sauvegarde des fichiers XML
        $this->contentDoc->save($this->contentPath);
        $this->manifestDoc->save($this->manifestPath);
        // Zip du dossier de travail
        if ($this->zip($this->tempPath, $this->exportPath)) {
            $this->rmdir($this->tempPath);
            return $this->fileName . '.odp';
        }
        return null;
    }

    function createPage()
    {
        $this->pageNode = $this->templatePageNode->cloneNode(true);
        $this->pageNode->setAttribute('draw:name', 'page' . $this->pageCount);
        $this->pageNode->setAttribute('draw:id', 'page' . $this->pageCount);
        $this->pageCount++;
    }

    function savePage()
    {
        $this->presentationNode->insertBefore($this->pageNode, $this->settingsNode);
    }
}