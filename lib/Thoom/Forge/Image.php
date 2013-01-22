<?php

/**
 * Manipulate images
 *
 * New actions should accept $image_path and $save_path.
 * $image_path should be passed to class::_loadImage
 * $save_path should be passed to class::_saveImage
 *
 * Jun 21, 10 - zdp: added actions to crop and rotate. Updated to support multiple actions in one request
 * Jun 8, 10 - zdp: initial creation
 */

namespace Thoom\Forge;

class Image
{

    /**
     * The name of the method argument that receives input
     * @var string
     */
    private $input = 'image_path';

    /**
     * The name of the method argument that saves the output
     * @var string
     */
    private $output = 'save_path';

    private $imagick;

    private $save_quality = 80;

    private $error = 'An unknown error has occurred';
    
    public function __construct($image_path)
    {
    	$this->imagick = $this->_loadImage($image_path);
    }

    public function crop($crop_top = 0, $crop_right = 0, $crop_bottom = 0, $crop_left = 0)
    {
        $crop_height = $this->imagick->getImageHeight() - $crop_top - $crop_bottom;
        $crop_width = $this->imagick->getImageWidth() - $crop_right - $crop_left;

        $this->imagick->cropImage($crop_width, $crop_height, $crop_left, $crop_top);

        return $this;
    }

    public function cropTo($crop_height = 0, $crop_width = 0)
    {
        $left = ($this->imagick->getImageWidth() - $crop_width) / 2;
        $top = ($this->imagick->getImageHeight() - $crop_height) / 2;

        $this->imagick->cropImage($crop_width, $crop_height, $left, $top);

        return $this;
    }

    public function cropSquare()
    {
        $h = $this->imagick->getImageHeight(); //350
        $w = $this->imagick->getImageWidth(); //450

        $crop_height = ($h > $w) ? $w : $h; //350
        $crop_width = ($h > $w) ? $w : $h; //350

        $left = floor(abs($w - $crop_width) / 2); //450 - 350 / 2 = 50
        //$top = ($h > $w) ? 0 : floor(abs($h - $crop_height) / 2); //350 - 350 / 2 = 0
	$top = 0;

        $this->imagick->cropImage($crop_width, $crop_height, $left, $top);

        return $this;
    }

    public function modulate($brightness = 100, $saturation = 100, $hue = 300)
    {
		$this->imagick->modulateImage($brightness, $saturation, $hue);
        return $this;
    }

    public function resize($resize_height = 0, $resize_width = 0, $resize_filter = 'lanczos', $resize_blur = 1)
    {
        $resize_filter = @constant('\imagick::FILTER_' . strtoupper($resize_filter));
        if (!$resize_filter) {
            $resize_filter = \imagick::FILTER_POINT;
        }

        $dimensions = $this->_resizeDimensions($resize_height, $resize_width);
        $this->imagick->resizeImage($dimensions['width'], $dimensions['height'], $resize_filter, $resize_blur);

        return $this;
    }

    public function resizeAdaptive($resize_height = 0, $resize_width = 0, $save_path = null)
    {
        $ratio = $this->imagick->getImageHeight() / $this->imagick->getImageWidth();

        $w = ($ratio < 1) ? $resize_width : floor($ratio * $resize_height);
        $h = ($ratio < 1) ? floor($ratio * $resize_width) : $resize_height;

        $this->imagick->adaptiveResizeImage($w, $h);

        return $this;
    }

    public function resizeSample($resize_height = 0, $resize_width = 0)
    {
        $ratio = $this->imagick->getImageHeight() / $this->imagick->getImageWidth();

        $w = ($ratio < 1) ? $resize_width : floor($ratio * $resize_height);
        $h = ($ratio < 1) ? floor($ratio * $resize_width) : $resize_height;

        $this->imagick->sampleImage($w, $h);

        return $this;
    }

    public function resizeScale($resize_height = 0, $resize_width = 0)
    {
        $ratio = $this->imagick->getImageHeight() / $this->imagick->getImageWidth();

        $w = ($ratio < 1) ? $resize_width : 0;
        $h = ($ratio < 1) ? 0 : $resize_height;

        $this->imagick->scaleImage($w, $h);

        return $this;
    }

    public function resizeThumbnail($resize_height = 0, $resize_width = 0)
    {
        $dimensions = $this->_resizeDimensions($resize_height, $resize_width);
        $this->imagick->thumbnailImage($dimensions['width'], $dimensions['height']);

        return $this;
    }

    /**
     * Rotate the image passed in image_path.
     * If no save_path, the rotated image will be passed back in a string
     *
     * @param int $rotate_degrees
     * @return bool|image
     */
    public function rotate($rotate_degrees)
    {
        $this->imagick->rotateImage(new \ImagickPixel(), $rotate_degrees);

        return $this;
    }

    public function save($save_quality, $save_path = null)
    {
        $this->save_quality = $save_quality;
        return $this->_saveImage($save_path);
    }

    public function unsharp($unsharp_radius, $unsharp_sigma, $unsharp_amount, $unsharp_threshold)
    {
        $this->imagick->unsharpMaskImage($unsharp_radius, $unsharp_sigma, $unsharp_amount, $unsharp_threshold);
        return $this;
    }

    /**
     * Returns a string representation of the image passed
     *
     * @param string $image_path
     * @return string
     */
    public function view()
    {
         return $this->_saveImage(null);
    }

    public function getImagick()
    {
        return $this->imagick;
    }


    /**
     * Get the error
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->error;
    }

    /**
     * Tries to read the image_path into a new \Imagick object.
     *
     * @param string $image_path
     * @return \Imagick
     */
    private function _loadImage($image_path)
    {
        if ($image_path instanceof \Imagick) {
            return $image_path;
        }

        $this->imagick = new \Imagick();
        try {
            $this->imagick->readImage($image_path);
        } catch (\ImagickException $e) {
            try {
                $this->imagick->readImageBlob($image_path);
            } catch (\ImagickException $e) {
                $this->error = "Could not load image '$image_path' for editing";
                return;
            }
        }
        return $this->imagick;
    }

    private function _resizeDimensions($height, $width)
    {
        if ($height != 0 && $width != 0) {
            $ratio = $this->imagick->getImageHeight() / $this->imagick->getImageWidth();

            $width = ($ratio < 1) ? $width : 0;
            $height = ($ratio < 1) ? 0 : $height;
        }

        return array('width' => $width, 'height' => $height);
    }

    /**
     * If the save_path is null, then we just output the $this->imagick image
     * @param string $save_path
     * @param \Imagick $this->imagick
     * @return bool|string
     */
    private function _saveImage($save_path)
    {
        if ($save_path) {
            $dirname = dirname($save_path);

            if (!is_dir($dirname)) {
                mkdir($dirname, 0777, true);
            }

            if (is_numeric($this->save_quality) && $this->save_quality > 0 && $this->save_quality < 100) {
                $this->imagick->setImageCompressionQuality($this->save_quality);
            }

            $results = $this->imagick->writeImage($save_path);

            if ($results) {
                chmod($save_path, 0777);
            } else {
                $this->error = "Could not save image '$save_path'";
            }

            return $results;
        }

        return $this->imagick->getImage();
    }
}
