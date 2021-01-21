<?php
/**
 * @copyright 2012 Anthon Pang
 * @license MIT
 */

namespace VIPSoft\Unzip;

/**
 * Unzip class
 *
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 */
class Unzip
{
    /**
     * @var array
     */
    private static $statusStrings = array(
        \ZipArchive::ER_OK => 'No error',
        \ZipArchive::ER_MULTIDISK => 'Multi-disk zip archives not supported',
        \ZipArchive::ER_RENAME => 'Renaming temporary file failed',
        \ZipArchive::ER_CLOSE => 'Closing zip archive failed',
        \ZipArchive::ER_SEEK => 'Seek error',
        \ZipArchive::ER_READ => 'Read error',
        \ZipArchive::ER_WRITE => 'Write error',
        \ZipArchive::ER_CRC => 'CRC error',
        \ZipArchive::ER_ZIPCLOSED => 'Containing zip archive was closed',
        \ZipArchive::ER_NOENT => 'No such file',
        \ZipArchive::ER_EXISTS => 'File already exists',
        \ZipArchive::ER_OPEN => 'Can\'t open file',
        \ZipArchive::ER_TMPOPEN => 'Failure to create temporary file',
        \ZipArchive::ER_ZLIB => 'Zlib error',
        \ZipArchive::ER_MEMORY => 'Malloc failure',
        \ZipArchive::ER_CHANGED => 'Entry has been changed',
        \ZipArchive::ER_COMPNOTSUPP => 'Compression method not supported',
        \ZipArchive::ER_EOF => 'Premature EOF',
        \ZipArchive::ER_INVAL => 'Invalid argument',
        \ZipArchive::ER_NOZIP => 'Not a zip archive',
        \ZipArchive::ER_INTERNAL => 'Internal error',
        \ZipArchive::ER_INCONS => 'Zip archive inconsistent',
        \ZipArchive::ER_REMOVE => 'Can\'t remove file',
        \ZipArchive::ER_DELETED => 'Entry has been deleted',
    );

    /**
     * @var boolean
     */
    private $continueOnError;

    /**
     * Extract zip file to target path
     *
     * @param string  $zipFile         Path of .zip file
     * @param string  $targetPath      Extract to this target (destination) path
     * @param boolean $continueOnError Continue extracting files on error
     *
     * @return mixed Array of filenames corresponding to the extracted files
     *
     * @throw \Exception
     */
    public function extract($zipFile, $targetPath, $continueOnError = false)
    {
        $this->continueOnError = $continueOnError;

        $zipArchive = $this->openZipFile($zipFile);
        $targetPath = $this->fixPath($targetPath);
        $filenames  = $this->extractFilenames($zipArchive);

        if ($zipArchive->extractTo($targetPath, $filenames) === false) {
            throw new \Exception($this->getStatusAsText($zipArchive->status));
        }

        $zipArchive->close();

        return $filenames;
    }

    /**
     * Make sure target path ends in '/'
     *
     * @param string $path
     *
     * @return string
     */
    private function fixPath($path)
    {
        if (substr($path, -1) === '/') {
            $path .= '/';
        }

        return $path;
    }

    /**
     * Open .zip archive
     *
     * @param string $zipFile
     *
     * @return \ZipArchive
     *
     * @throw \Exception
     */
    private function openZipFile($zipFile)
    {
        $zipArchive = new \ZipArchive;
        $status = $zipArchive->open($zipFile);

        if ($status !== true) {
            throw new \Exception($this->getStatusAsText($status) . ": $zipFile");
        }

        return $zipArchive;
    }

    /**
     * Extract list of filenames from .zip
     *
     * @param \ZipArchive $zipArchive
     *
     * @return array
     */
    private function extractFilenames(\ZipArchive $zipArchive)
    {
        $filenames = array();
        $fileCount = $zipArchive->numFiles;

        for ($i = 0; $i < $fileCount; $i++) {
            $filename = $this->extractFilename($zipArchive, $i);

            if ($filename !== false) {
                $filenames[] = $filename;
            }
        }

        return $filenames;
    }

    /**
     * Test for valid filename path
     *
     * The .zip file is untrusted input. We check for absolute path (i.e., leading slash),
     * possible directory traversal attack (i.e., '..'), and use of PHP wrappers (i.e., ':').
     * Subclass and override this method at your own risk!
     *
     * @param string $path
     *
     * @return boolean
     */
    protected function isValidPath($path)
    {
        $pathParts = explode('/', $path);

        if (strncmp($path, '/', 1) === 0 ||
            array_search('..', $pathParts) !== false ||
            strpos($path, ':') !== false
        ) {
            return false;
        }

        return true;
    }

    /**
     * Extract filename from .zip
     *
     * @param \ZipArchive $zipArchive Zip file
     * @param integer     $fileIndex  File index
     *
     * @return string
     *
     * @throw \Exception
     */
    private function extractFilename(\ZipArchive $zipArchive, $fileIndex)
    {
        $entry = $zipArchive->statIndex($fileIndex);

        // convert Windows directory separator to Unix style
        $filename = str_replace('\\', '/', $entry['name']);

        if ($this->isValidPath($filename)) {
            return $filename;
        }

        $statusText = "Invalid filename path in zip archive: $filename";

        if ($this->continueOnError) {
            trigger_error($statusText);

            return false;
        }

        throw new \Exception($statusText);
    }

    /**
     * Get status as text string
     *
     * @param integer $status ZipArchive status
     *
     * @return string
     */
    private function getStatusAsText($status)
    {
        $statusString = isset($this->statusStrings[$status])
            ? $this->statusStrings[$status]
            : 'Unknown status';

        return $statusString . '(' . $status . ')';
    }
}
