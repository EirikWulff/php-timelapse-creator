# Timelapse Editor #

This project is basically a PHP script that takes a sequence of photos, sorts them by their EXIF date, and creates a timelapse video from them. The project was built in macOS, but should work just as well on all platforms that can install and run PHP and ffmpeg.

## Requirements ##

* PHP 7.x - Install with [Homebrew](https://brew.sh) on macOS (brew install php)
* PHP Composer - Install with Homebrew on macOS (brew install composer)
* ffmpeg (with x264/x265 support) - Install with Homebrew on macOS (brew install ffmpeg)
* (optional) Imagemagick - Install with Homebrew on macOS (brew install imagemagick)
* (optional) imagick extension for PHP - Install using pecl (pecl install imagick)
* Quite a lot of available disk space...

The script is built to prefer using the imagick extension for the image editing (= MUCH better quality), but it will work-ish with PHP's built-in GD as well. The script will check for the imagick extension, and will fallback to using GD. Also, note that the imagick extension requires Imagemagick to be installed as well in order to work.

### Disk space ###
A general rule of thumb is to make sure to have available disk space that is 5 times the size of the folder containing the source images before running this script. It is possible to configure the script to use multiple folder locations, residing on different disks.

## Installation and usage ##

* Clone or download the source files, then run `composer install` to install the necessary libraries
* Put timelapse images into the 'images-src' folder
* *(optional)* Copy the file 'settings.env.example' to 'settings.env' and do your changes (look below for info)
* Run the script in the Terminal (`php create_timelapse.php`). 

It is probably wise to test with a small number of images untill you get the result you want. Also, 

The script will do the following:

### 0. Initialising ###
At first, the script will create the folders it needs, and empty the temporary folders before doing the actual work. By temporary folders, we mean the folder for the processed images (default: 'tmp-processed') and the image sequence folder (default: 'tmp-sequence'). They will also be emptied during after creating the image sequence and after creating the video, to conserve disk space.

### 1. Process the images ###
The script will first scan through all source photos and read the EXIF info contained in them. If it isn't a duplicate, the photo will put the date and time it was taken (from EXIF) superimposed in the lower right corner, while the **logo.png** will be placed in the top left corner, with a 40% transparency. PS! There are lots of other editing possibilities here, but this is what was needed in this project.

The resulting image will be placed in the folder for processed images (default: 'tmp-processed'), with it's EXIF date as filename (YYYY-MM-DD-HHMMSS).

If duplicates are encountered during processing, they are deleted from the source folder and skipped from processing.

And yes, thousands of 1080p images will take up quite a lot of space. It is wise to have the temporary folders residing on a fast disk with lots of space

### 2. Create the image sequence ###
After this, the script will scan the processed image folder, and create a photo sequence by copying the files to the 'tmp-sequence' folder. Their filenames will be in the pattern 'seq-########.jpg', the oldest will be 'seq-00000001.jpg' and so on and so on. This will make it possible for ffmpeg to create the video with the correct frame sequence.

### 3. Create the video ###
By default, the script will create a HEVC file with a Constant Rate Factor of 30 (≈ 5,7 Mbit/sec bitrate). The filename will also contain the date of the last photo in the sequence.

The resulting video will be placed in the video folder, with a name containing the date of the last image taken.

## Settings ##
All configuration can be done in the file *settings.env*. All default settings are available in the file *settings.env.example*, so a good routine is to copy settings.env.example to settings.env before running the script. You may also create a settings.env file containing only the settings you want to change. Most of the default settings will work fine as they are, even without creating a settings.env file at all. The most likely candidates for changes are **IMAGE_FONT** and possibly the **IMAGE_ROTATE** settings.

### Overview ###
* **IMAGE_FONT** - Path to the font file to use for the superimposed text. The default value will work only on macOS. You may also use integer values 1-5 to use the GD built-in fonts, as documented on [the image library website](http://image.intervention.io/api/text). A pro tip is to test this with a few images before you do the full 100000 image run. Default: "/Library/Fonts/Arial.ttf"
* **IMAGE_DRIVER** - Which driver to use whith image processing, default "imagick". Will use "gd" automatically if imagick is not available
* **IMAGE_FONTSIZE_DATE** - Font size to use on the superimposed date. Default: 26
* **IMAGE_FONTSIZE_TIME** - Font size for the superimposed time. Default: 40
* **IMAGE_DATE_FORMAT** - Date format to use, based on the [DateTime::format specification](http://php.net/manual/en/datetime.format.php). Default: "d.m.Y", eg. "01.11.2019".
* **IMAGE_TIME_FORMAT** - Time format to use, based on [the same specs](http://php.net/manual/en/datetime.format.php). Default: "H:i:s", eg. "19:42:08"
* **IMAGE_NO_LOGO** - Makes it possible to disable the use of a superimposed logo in the top left corner. Set to 1 to disable. Default: 0
* **IMAGE_LOGO** - The logo file (PNG preferred) to superimpose. Default: 'logo.png'
* **IMAGE_LOGO_HEIGHT** - The final size of the superimposed logo is set by scaling the image to a given height, keeping the aspect ratio. Default: 200
* **IMAGE_LOGO_OPACITY** - Transparency setting for the superimposed logo, in percent. 0 = invisible, 100 = opaque. Default: 40
* **IMAGE_JPEG_QUALITY** - JPEG Quality for the processed images. Keep high to avoid getting a bad video result. Default: 90
* **IMAGE_ROTATE** - For some reason, images originating from iPhone (iOS 12+) ends up upside-down when imported by Imagemagick. This setting will let the script rotate the image when editing it. Set to 0 to disable the rotation. Default: 1
* **IMAGE_ROTATE_SKIP** - If the first X images doesn't need rotating (for instance iPhone-images from iOS < 12), set this value to X. Default: 0
* **VIDEO_FORMAT** - Lets you specify the video encoding to use when making the video. You may use "x264" to enjoy much faster encoding (and much larger files), or "hevc" to use the more efficient HEVC/x265 format. Default: "hevc"
* **VIDEO_FRAMERATE** - The framerate (frames per second) of the resulting video. Default: 25
* **CRF_HEVC** - Constant Rate Factor for HEVC encoding. Higher value = smaller file. The default value results in a bitrate ≈ 5-6 Mbit/s. Default: 30
* **CRF_x264** - Constant Rate Factor to use for x264 encoding. The default value has about the same video quality as a HEVC CRF of 30, but results in a much larger file (bitrate ≈ 14 Mbit/s). Default: 25
* **PATH_SRC** - Path to the source folder, where the original images are stored, without trailing "/". Default: "images-src"
* **PATH_IMG** - Where to put images after superimposing the timestamp (and logo). Default: "images"
* **PATH_SEQ** - Where the numbered images are stored. This is where ffmpeg finds the image sequence it needs to produce the video. Default: "images-sequence"
* **PATH_VIDEO** - Where the finished video ends up. Default: "video"
* **KEEP_TMP_PROCESSED**/**KEEP_TMP_SEQUENCE** - Normally, the script will delete the temporary folders during and after processing to conserve disk space. If set to 1, these two settings will skip the deletion. The temporary folders *will* be emptied the next time the script is run, though. Default: 0
