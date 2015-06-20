<?php namespace October\Rain\Database\Attach;

use Storage;
use File as FileHelper;
use October\Rain\Database\Model;
use October\Rain\Database\Attach\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\File as FileObj;
use Exception;

/**
 * File attachment model
 *
 * @package october\database
 * @author Alexey Bobkov, Samuel Georges
 */
class File extends Model
{
    use \October\Rain\Database\Traits\Sortable;

    /**
     * @var string The table associated with the model.
     */
    protected $table = 'files';

    /**
     * Relations
     */
    public $morphTo = ['attachment'];

    /**
     * @var array The attributes that aren't mass assignable.
     */
    protected $guarded = ['disk_name'];

    /**
     * @var array Known image extensions.
     */
    public static $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];

    /**
     * @var array Hidden fields from array/json access
     */
    protected $hidden = ['attachment_type', 'attachment_id', 'is_public'];

    /**
     * @var array Add fields to array/json access
     */
    protected $appends = ['path', 'extension'];

    /**
     * @var array Mime types
     */
    protected $autoMimeTypes = [
        'docx' => 'application/msword',
        'xlsx' => 'application/excel',
        'gif'  => 'image/gif',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'pdf'  => 'application/pdf'
    ];

    //
    // Constructors
    //

    /**
     * Creates a file object from a file an uploaded file.
     * @param Symfony\Component\HttpFoundation\File\UploadedFile $uploadedFile
     */
    public function fromPost($uploadedFile)
    {
        if ($uploadedFile === null)
            return;

        $this->file_name = $uploadedFile->getClientOriginalName();
        $this->file_size = $uploadedFile->getClientSize();
        $this->content_type = $uploadedFile->getMimeType();
        $this->disk_name = $this->getDiskName();

        $this->putFile($uploadedFile->getRealPath(), $this->disk_name);

        return $this;
    }

    /**
     * Creates a file object from a file on the disk.
     */
    public function fromFile($filePath)
    {
        if ($filePath === null)
            return;

        $file = new FileObj($filePath);
        $this->file_name = $file->getFilename();
        $this->file_size = $file->getSize();
        $this->content_type = $file->getMimeType();
        $this->disk_name = $this->getDiskName();

        $this->putFile($file->getRealPath(), $this->disk_name);

        return $this;
    }

    //
    // Attribute mutators
    //

    /**
     * Helper attribute for getPath.
     * @return string
     */
    public function getPathAttribute()
    {
        return $this->getPath();
    }

    /**
     * Helper attribute for getExtension.
     * @return string
     */
    public function getExtensionAttribute()
    {
        return $this->getExtension();
    }

    //
    // Raw output
    //

    /**
     * Outputs the raw file contents.
     * @return void
     */
    public function output($disposition = 'inline')
    {
        header("Content-type: ".$this->getContentType());
        header('Content-Disposition: '.$disposition.'; filename="'.$this->file_name.'"');
        header('Cache-Control: private');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: pre-check=0, post-check=0, max-age=0');
        header('Accept-Ranges: bytes');
        header('Content-Length: '.$this->file_size);
        echo $this->getContents();
    }

    /**
     * Outputs the raw thumbfile contents.
     * @return void
     */
    public function outputThumb($width, $height, $options = [])
    {
        $disposition = array_get($options, 'disposition', 'inline');
        $this->getThumb($width, $height, $options);
        $options = $this->getDefaultThumbOptions($options);
        $thumbFile = $this->getThumbFilename($width, $height, $options);
        $contents = $this->getContents($thumbFile);

        header("Content-type: ".$this->getContentType());
        header('Content-Disposition: '.$disposition.'; filename="'.basename($thumbFile).'"');
        header('Cache-Control: private');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: pre-check=0, post-check=0, max-age=0');
        header('Accept-Ranges: bytes');
        header('Content-Length: '.mb_strlen($contents, '8bit'));
        echo $contents;
    }

    //
    // Getters
    //

    /**
     * Generates a disk name from the supplied file name.
     */
    protected function getDiskName()
    {
        if ($this->disk_name !== null)
            return $this->disk_name;

        $ext = strtolower($this->getExtension());
        $name = str_replace('.', '', uniqid(null, true));

        return $this->disk_name = $ext !== null ? $name.'.'.$ext : $name;
    }

    /**
     * Returns the file name without path
     */
    public function getFilename()
    {
        return $this->file_name;
    }

    /**
     * Returns the file extension.
     */
    public function getExtension()
    {
        return FileHelper::extension($this->file_name);
    }

    /**
     * Returns the file content type.
     */
    protected function getContentType()
    {
        if ($this->content_type !== null) {
            return $this->content_type;
        }

        $ext = $this->getExtension();
        if (isset($this->autoMimeTypes[$ext])) {
            return $this->content_type = $this->autoMimeTypes[$ext];
        }

        return null;
    }

    /**
     * Get file contents from storage device.
     */
    public function getContents($fileName = null)
    {
        if (!$fileName) {
            $fileName = $this->disk_name;
        }

        return Storage::get($this->getStorageDirectory() . $this->getPartitionDirectory() . $fileName);
    }

    /**
     * Returns the public address to access the file.
     */
    public function getPath()
    {
        return $this->getPublicPath() . $this->getPartitionDirectory() . $this->disk_name;
    }

    /**
     * Returns the local path to the file.
     */
    public function getDiskPath()
    {
        return $this->getStorageDirectory() . $this->getPartitionDirectory() . $this->disk_name;
    }

    /**
     * Determines if the file is flagged "public" or not.
     */
    public function isPublic()
    {
        if (array_key_exists('is_public', $this->attributes))
            return $this->attributes['is_public'];

        if (isset($this->is_public))
            return $this->is_public;

        return true;
    }

    /**
     * Returns the file size as string.
     * @return string Returns the size as string.
     */
    public function sizeToString()
    {
        return FileHelper::sizeToString($this->file_size);
    }

    //
    // Events
    //

    /**
     * Before the model is saved
     * - check if new file data has been supplied, eg: $model->data = Input::file('something');
     */
    public function beforeSave()
    {
        /*
         * Process and purge the data attribute
         */
        if ($this->isDirty('data')) {
            if ($this->data instanceof UploadedFile) {
                $this->fromPost($this->data);
            }
            else {
                $this->fromFile($this->data);
            }

            unset($this->data);
        }
    }

    /**
     * After model is deleted
     * - clean up it's thumbnails
     */
    public function afterDelete()
    {
        try {
            $this->deleteThumbs();
            $this->deleteFile();
        }
        catch (Exception $ex) {}
    }

    //
    // Image handling
    //

    /**
     * Checks if the file extension is an image and returns true or false.
     */
    public function isImage()
    {
        return in_array(strtolower($this->getExtension()), static::$imageExtensions);
    }

    /**
     * Generates and returns a thumbnail path.
     */
    public function getThumb($width, $height, $options = [])
    {
        if (!$this->isImage()) {
            return $this->getPath();
        }

        $width = (int) $width;
        $height = (int) $height;

        $options = $this->getDefaultThumbOptions($options);

        $thumbFile = $this->getThumbFilename($width, $height, $options);
        $thumbPath = $this->getStorageDirectory() . $this->getPartitionDirectory() . $thumbFile;
        $thumbPublic = $this->getPublicPath() . $this->getPartitionDirectory() . $thumbFile;

        if (!$this->hasFile($thumbFile)) {

            if ($this->isLocalStorage()) {
                $this->makeThumbLocal($thumbFile, $thumbPath, $width, $height, $options);
            }
            else {
                $this->makeThumbStorage($thumbFile, $thumbPath, $width, $height, $options);
            }

        }

        return $thumbPublic;
    }

    /**
     * Generates a thumbnail filename.
     * @return string
     */
    protected function getThumbFilename($width, $height, $options)
    {
        return 'thumb_' . $this->id . '_' . $width . 'x' . $height . '_' . $options['offset'][0] . '_' . $options['offset'][1] . '_' . $options['mode'] . '.' . $options['extension'];
    }

    /**
     * Returns the default thumbnail options.
     * @return array
     */
    protected function getDefaultThumbOptions($overrideOptions = [])
    {
        $defaultOptions = [
            'mode'      => 'auto',
            'offset'    => [0, 0],
            'quality'   => 95,
            'extension' => 'jpg',
        ];

        if (!is_array($overrideOptions)) {
            $overrideOptions = ['mode' => $overrideOptions];
        }

        $options = array_merge($defaultOptions, $overrideOptions);

        $options['mode'] = strtolower($options['mode']);

        if ((strtolower($options['extension'])) == 'auto') {
            $options['extension'] = $this->getExtension();
        }

        return $options;
    }

    /**
     * Generate the thumbnail based on the local file system. This step is necessary
     * to simplify things and ensure the correct file permissions are given
     * to the local files.
     */
    protected function makeThumbLocal($thumbFile, $thumbPath, $width, $height, $options)
    {
        $rootPath = $this->getLocalRootPath();
        $filePath = $rootPath.'/'.$this->getDiskPath();
        $thumbPath = $rootPath.'/'.$thumbPath;

        /*
         * Handle a broken source image
         */
        if (!$this->hasFile($this->disk_name)) {
            BrokenImage::copyTo($thumbPath);
        }
        /*
         * Generate thumbnail
         */
        else {
            $resizer = Resizer::open($filePath);
            $resizer->resize($width, $height, $options['mode'], $options['offset']);
            $resizer->save($thumbPath, $options['quality']);
        }

        FileHelper::chmod($thumbPath);
    }

    /**
     * Generate the thumbnail based on a remote storage engine.
     */
    protected function makeThumbStorage($thumbFile, $thumbPath, $width, $height, $options)
    {
        $tempFile = $this->getLocalTempPath();
        $tempThumb = $this->getLocalTempPath($thumbFile);

        /*
         * Handle a broken source image
         */
        if (!$this->hasFile($this->disk_name)) {
            BrokenImage::copyTo($tempThumb);
        }
        /*
         * Generate thumbnail
         */
        else {
            $this->copyStorageToLocal($this->getDiskPath(), $tempFile);
            $resizer = Resizer::open($tempFile);
            $resizer->resize($width, $height, $options['mode'], $options['offset']);
            $resizer->save($tempThumb, $options['quality']);
            FileHelper::delete($tempFile);
        }

        /*
         * Publish to storage and clean up
         */
        $this->copyLocalToStorage($tempThumb, $thumbPath);
        FileHelper::delete($tempThumb);
    }

    /*
     * Delete all thumbnails for this file.
     */
    protected function deleteThumbs()
    {
        $pattern = 'thumb_'.$this->id.'_';

        $directory = $this->getStorageDirectory() . $this->getPartitionDirectory();
        $allFiles = Storage::files($directory);
        $collection = [];
        foreach ($allFiles as $file) {
            if (starts_with(basename($file), $pattern)) {
                $collection[] = $file;
            }
        }

        if (!empty($collection)) {
            Storage::delete($collection);
        }
    }

    //
    // File handling
    //

    /**
     * Returns a temporary local path to work from.
     */
    protected function getLocalTempPath($path = null)
    {
        if (!$path) {
            return $this->getTempPath() . '/' . md5($this->getDiskPath()) . '.' . $this->getExtension();
        }

        return $this->getTempPath() . '/' . $path;
    }

    /**
     * Copy the Storage to local file
     */
    protected function copyStorageToLocal($storagePath, $localPath)
    {
        return FileHelper::put($localPath, Storage::get($storagePath));
    }

    /**
     * Copy the local file to Storage
     */
    protected function copyLocalToStorage($localPath, $storagePath)
    {
        return Storage::put($storagePath, FileHelper::get($localPath), ($this->isPublic()) ? 'public' : null);
    }

    /**
     * Saves a file
     * @param string $sourcePath An absolute local path to a file name to read from.
     * @param string $destinationFileName A storage file name to save to.
     */
    protected function putFile($sourcePath, $destinationFileName = null)
    {
        if (!$destinationFileName) {
            $destinationFileName = $this->disk_name;
        }

        $destinationPath = $this->getStorageDirectory() . $this->getPartitionDirectory();

        if (!$this->isLocalStorage()) {
            return $this->copyLocalToStorage($sourcePath, $destinationPath . $destinationFileName);
        }

        /*
         * Using local storage, tack on the root path and work locally
         * this will ensure the correct permissions are used.
         */
        $destinationPath = $this->getLocalRootPath() . '/' . $destinationPath;

        if (!FileHelper::isDirectory($destinationPath)) {
            FileHelper::makeDirectory($destinationPath, 0777, true);
        }

        return FileHelper::copy($sourcePath, $destinationPath . $destinationFileName);
    }

    /**
     * Delete file contents from storage device.
     */
    protected function deleteFile($fileName = null)
    {
        if (!$fileName)
            $fileName = $this->disk_name;

        $directory = $this->getStorageDirectory() . $this->getPartitionDirectory();
        $filePath = $directory . $fileName;

        if (Storage::exists($filePath)) {
            Storage::delete($filePath);
        }

        $this->deleteEmptyDirectory($directory);
    }

    /**
     * Check file exists on storage device.
     */
    protected function hasFile($fileName = null)
    {
        $filePath = $this->getStorageDirectory() . $this->getPartitionDirectory() . $fileName;
        return Storage::exists($filePath);
    }

    /**
     * Checks if directory is empty then deletes it,
     * three levels up to match the partition directory.
     */
    protected function deleteEmptyDirectory($dir = null)
    {
        if (!$this->isDirectoryEmpty($dir))
            return;

        Storage::deleteDirectory($dir);

        $dir = dirname($dir);
        if (!$this->isDirectoryEmpty($dir))
            return;

        Storage::deleteDirectory($dir);

        $dir = dirname($dir);
        if (!$this->isDirectoryEmpty($dir))
            return;

        Storage::deleteDirectory($dir);
    }

    /**
     * Returns true if a directory contains no files.
     */
    protected function isDirectoryEmpty($dir)
    {
        if (!$dir) return null;

        return count(Storage::allFiles($dir)) === 0;
    }

    //
    // Configuration
    //

    /**
     * Returns the maximum size of an uploaded file as configured in php.ini
     * @return int The maximum size of an uploaded file in kilobytes
     */
    public static function getMaxFilesize()
    {
        return round(UploadedFile::getMaxFilesize() / 1024);
    }

    /**
     * Returns the default common file extensions.
     * @return array
     */
    public static function getDefaultFileTypes($isImage = false)
    {
        if ($isImage) {
            return [
                'jpg',
                'jpeg',
                'bmp',
                'png',
                'gif',
                'svg'
            ];
        }
        else {
            return [
                'jpg',
                'jpeg',
                'bmp',
                'png',
                'gif',
                'svg',
                'js',
                'map',
                'ico',
                'css',
                'less',
                'scss',
                'pdf',
                'swf',
                'txt',
                'xml',
                'xls',
                'eot',
                'woff',
                'woff2',
                'ttf',
                'flv',
                'wmv',
                'mp3',
                'ogg',
                'wav',
                'avi',
                'mov',
                'mp4',
                'mpeg',
                'webm',
                'mkv'
            ];
        }
    }

    /**
     * Define the internal storage path, override this method to define.
     */
    public function getStorageDirectory()
    {
        if ($this->isPublic()) {
            return 'uploads/public/';
        }
        else {
            return 'uploads/protected/';
        }
    }

    /**
     * Define the public address for the storage path.
     */
    public function getPublicPath()
    {
        if ($this->isPublic()) {
            return 'http://localhost/uploads/public/';
        }
        else {
            return 'http://localhost/uploads/protected/';
        }
    }

    /**
     * Define the internal working path, override this method to define.
     */
    public function getTempPath()
    {
        $path = temp_path() . '/uploads';

        if (!FileHelper::isDirectory($path)) {
            FileHelper::makeDirectory($path, 0777, true, true);
        }

        return $path;
    }

    /**
     * Returns true if the storage engine is local.
     * @return bool
     */
    protected function isLocalStorage()
    {
        return Storage::getDefaultDriver() == 'local';
    }

    /**
    * Generates a partition for the file.
    * return /ABC/DE1/234 for an name of ABCDE1234.
    * @param Attachment $attachment
    * @param string $styleName
    * @return mixed
    */
    protected function getPartitionDirectory()
    {
        return implode('/', array_slice(str_split($this->disk_name, 3), 0, 3)) . '/';
    }

    /**
     * If working with local storage, determine the absolute local path.
     * @return string
     */
    protected function getLocalRootPath()
    {
        return storage_path().'/app';
    }
}
