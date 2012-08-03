<?php
namespace Etch\PluploadBundle\PluploadFile;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * PlUploadFile class
 *
 * @author Kevin Dew <kev@redbullet.co.uk>
 */
class PluploadFile
{
    /**
     * @var int
     */
    private $chunk = 0;

    /**
     * @var int
     */
    private $chunks = 0;

    /**
     * @var string
     */
    private $filename = '';

    /**
     * @var string
     */
    private $targetDirectory;

    /**
     * @var bool
     */
    private $uploadProcessed = false;

    /**
     * Constructor
     *
     * @param int $chunk
     * @param int $chunks
     * @param string $filename
     * @param string $targetDirectory
     */
    public function __construct(
        $chunk,
        $chunks,
        $filename,
        $targetDirectory
    )
    {
        $this->setChunk($chunk);
        $this->setChunks($chunks);
        $this->setFilename($filename);
        $this->setTargetDirectory($targetDirectory);
    }

    /**
     * @return bool
     */
    public function isUploadProcessed()
    {
        return $this->uploadProcessed;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\File\UploadedFile $file
     * @throws \RuntimeException
     */
    public function processMultipartUpload(UploadedFile $file)
    {
        $this->prepareForProcess();

        if (!$file->isValid()) {
            throw new \RuntimeException('Uploaded file is invalid');
        }

        $outputFile = fopen(
            $this->getFilePath(),
            ($this->getChunk() == 0 ? 'wb' : 'ab')
        );

        if (!$outputFile) {
            throw new \RuntimeException('Could not open/create output file');
        }

        $inputFile = fopen($file->getPathname(), 'rb');

        if (!$inputFile) {
            throw new \RuntimeException('Could not open temporary file');
        }

        while ($buff = fread($inputFile, 4096))
        {
            fwrite($outputFile, $buff);
        }

        $this->setUploadProcessed(true);
    }

    /**
     * @throws \RuntimeException
     */
    public function processStreamUpload()
    {
        $this->prepareForProcess();

        $outputFile = fopen(
            $this->getFilePath(),
            ($this->getChunk() == 0 ? 'wb' : 'ab')
        );

        if (!$outputFile) {
            throw new \RuntimeException('Could not open/create output file');
        }

        $inputStream = fopen('php://input', 'rb');

        if (!$inputStream) {
            throw new \RuntimeException('Could not open input stream');
        }

        while ($buff = fread($inputStream, 4096)) {
            fwrite($outputFile, $buff);
        }

        $this->setUploadProcessed(true);
    }

    /**
     * @throws \RuntimeException
     */
    protected function prepareForProcess()
    {
        if ($this->isUploadProcessed()) {
            throw new \RuntimeException('Upload has already been processed');
        }

        if ($this->getChunks() < 2) {
            $this->setFilename(uniqid('pl', true));
        }

        $directory = $this->getTargetDirectory();

        if (!is_dir($directory)) {
            if (false === @mkdir($directory, 0777, true)) {
                throw new \RuntimeException(
                    sprintf('Unable to create the "%s" directory', $directory)
                );
            }
        } else if (!is_writable($directory)) {
            throw new \RuntimeException(
                sprintf('Unable to write in the "%s" directory', $directory)
            );
        }
    }

    /**
     * @return bool
     * @throws \RuntimeException
     */
    public function isComplete()
    {
        if (!$this->isUploadProcessed()) {
            throw new \RuntimeException('Upload hasn\'t been processed');
        }

        if ($this->chunks < 2) {
            return true;
        }

        if ($this->chunks == ($this->chunk + 1)) {
            return true;
        }

        return false;
    }

    /**
     * Set Chunk
     *
     * @param int $chunk
     * @return void
     */
    protected function setChunk($chunk)
    {
        $this->chunk = (int) $chunk;
    }

    /**
     * Get Chunk
     *
     * @return int
     */
    public function getChunk()
    {
        return $this->chunk;
    }

    /**
     * Set Chunks
     *
     * @param int $chunks
     * @return void
     */
    protected function setChunks($chunks)
    {
        $this->chunks = (int) $chunks;
    }

    /**
     * Get Chunks
     *
     * @return int
     */
    public function getChunks()
    {
        return $this->chunks;
    }

    /**
     * Set Filename
     *
     * @param string $filename
     * @return void
     */
    protected function setFilename($filename)
    {
        // some sanitising
        $filename = preg_replace('/[^\w\._]+/', '', (string) $filename);
        if (!$filename) {
            throw new \RuntimeException('Filename can\'t be empty');
        }
        $this->filename = $filename;
    }

    /**
     * Get Filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Set TargetDirectory
     *
     * @param string $targetDirectory
     * @return void
     */
    protected function setTargetDirectory($targetDirectory)
    {
        $this->targetDirectory = $targetDirectory;
    }

    /**
     * Get TargetDirectory
     *
     * @return string
     */
    public function getTargetDirectory()
    {
        return $this->targetDirectory;
    }

    /**
     * Set UploadProcessed
     *
     * @param boolean $uploadProcessed
     * @return void
     */
    protected function setUploadProcessed($uploadProcessed)
    {
        $this->uploadProcessed = (bool) $uploadProcessed;
    }

    /**
     * Get the path to the file being uploaded
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->getTargetDirectory() . '/' . $this->getFilename();
    }

    /**
     * When process is complete get the file
     *
     * @return \Symfony\Component\HttpFoundation\File\File
     * @throws \RuntimeException
     */
    public function getCompletedFile()
    {
       if (!$this->isComplete()) {
           throw new \RuntimeException('File is not complete');
       }

       return new File($this->getFilePath());
    }
}
