<?php

require 'vendor/autoload.php';

// import the necessary library classes
use Intervention\Image\ImageManager;
use Carbon\Carbon;
use Symfony\Component\Dotenv\Dotenv;


// Function for emptying a folder

function emptyFolderContents ( $path, $pattern="jpg" ) {
	$files = glob($path.'/*.{'.$pattern.'}', GLOB_BRACE);
	foreach ( $files as $f ) {
		if (is_file($f)) unlink($f);
	}
}


// Loading the settings.env file
$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/settings.env');

// Setting up the config object by using values set in .env or default values
$conf = new stdclass();
$conf->dt_source = getenv('FILEDATE_SRC') ?: 'exif'; // Where to get the image timestamp, 'exif'|'filename'|'filemod'
$conf->dt_format = getenv('FILENAME_FORMAT') ?: 'Y-m-d_His'; // If getting the timestamp from the file name, specify date format.
$conf->im_driver = getenv('IMAGE_DRIVER') ?: 'imagick';
$conf->jpeg_quality = getenv('IMAGE_JPEG_QUALITY') ?? 90; // JPEG Quality. Higher means cleaner images.
$conf->font = getenv('IMAGE_FONT') ?: 0; // The font file to use for the superimposed text
$conf->nologo = getenv('IMAGE_NO_LOGO') ?: 0; // Set to 0 to disable the superimposed logo
$conf->logofile = getenv('IMAGE_LOGO') ?: 'logo.png'; // Logo to superimpose on the images
$conf->logo_height = getenv('IMAGE_LOGO_HEIGHT') ?: 200;
$conf->logo_opacity = getenv('IMAGE_LOGO_OPACITY') ?: 40; // The logo opacity, in percent. 0 = transparent, 100 = opaque
$conf->fontsize_date = getenv('IMAGE_FONTSIZE_DATE') ?: 26;  
$conf->fontsize_time = getenv('IMAGE_FONTSIZE_TIME') ?: 40;
$conf->date_format = getenv('IMAGE_DATE_FORMAT') ?: 'd.m.Y';
$conf->time_format = getenv('IMAGE_TIME_FORMAT') ?: 'H:i:s';
$conf->rotate_images = getenv('IMAGE_ROTATE') ?: 1; // For some reason, iPhone images are often interpreted as upside-down. So we rotate them.
$conf->rotate_skip = getenv('IMAGE_ROTATE_SKIP') ?: 0; // Use if the first x images does not need rotating. 0 = do not skip.

$conf->video_format = getenv('VIDEO_FORMAT') ?: 'hevc'; // 'hevc' or 'x264'
$conf->framerate = getenv('VIDEO_FRAMERATE') ?: 25;
$conf->crf_hevc = getenv('CRF_HEVC') ?: 30; // Constant Rate Factor for HEVC video. 30 seems to keep good detail in a HD picture.
$conf->crf_x264 = getenv('CRF_x264') ?: 25; // Constant Rate Factor for x264 video (24-28 should work well)

$conf->srcfolder = getenv('PATH_SRC') ?: 'images-src';
$conf->imgfolder = getenv('PATH_IMG') ?: 'tmp-processed';
$conf->seqfolder = getenv('PATH_SEQ') ?: 'tmp-sequence';
$conf->videofolder = getenv('PATH_VIDEO') ?: 'video';

$conf->keep_tmp_processed = getenv('KEEP_TMP_PROCESSED') ?: 0; // Will keep the processed images after the run.
$conf->keep_tmp_sequence = getenv('KEEP_TMP_SEQUENCE') ?: 0; // Will keep the processed images after the run.

echo "Welcome to the Timelapse Editor\n\n";
/* print_r($conf); exit; */

echo "\n0. Initialising...\n";
// Making sure the folders exists
foreach ([$conf->srcfolder, $conf->imgfolder, $conf->seqfolder, $conf->videofolder] as $f):
	if (! is_dir($f)):
		mkdir($f);
	endif;
endforeach;
// Deleting image files in the temporary folders
$oldstuff = array_merge(glob($conf->imgfolder.'/*.{jpg}', GLOB_BRACE), glob($conf->seqfolder.'/*.{jpg}', GLOB_BRACE));
foreach ($oldstuff as $f):
	if (is_file($f)) unlink($f);
endforeach;
unset($oldstuff, $f);

// Create an image manager instance with favored driver
// Choosing imagick if the extension is installed
if ( $conf->im_driver == 'imagick' && extension_loaded('imagick') ):
	$oManager = new ImageManager(['driver' => 'imagick']); // Using Imagemagick and ext-imagick
	echo ">> Using the imagick driver to process images";
else:
	$oManager = new ImageManager(); // Using the default GD library
	echo ">> Using the default GD driver to process images (oh, no...)";
endif;
echo "\n\n";

if (! $conf->nologo):
	$logo = $oManager->make($conf->logofile);
	// Resize to a given height, keeping aspect ratio
	$logo->resize(null, $conf->logo_height, function ($constraint) {
	    $constraint->aspectRatio();
	});
	// Setting opacity
	$logo->opacity($conf->logo_opacity);
endif;


echo "\n1. Processing files...\n";
$files = glob($conf->srcfolder.'/*.{png,jpg,jpeg,JPG}', GLOB_BRACE);

if (count($files) == 0) die('### There are no image files in the folder '.$conf->srcfolder."! ###\n");

$iFileNum = 0;
$iDupes = 0;
$iSaved = 0;
$dtHighest = Carbon::parse('1970-01-01', 'Europe/Oslo');
foreach($files as $file):
	$iFileNum++;
	echo ' '.$file . " ";

	$image = $oManager->make($file);
	// Rotating the image if set
	if ($iFileNum > $conf->rotate_skip && $conf->rotate_images):
		$image->rotate(180);
	endif;
	switch ($conf->dt_source):
		case 'exif':
			$dateTime = Carbon::parse($image->exif('DateTime'));
			break;
		case 'filename':
			// Provided the file name is 'yyyy-mm-dd_hhmmss.jpg'
			$fn = basename($file, '.jpg');
			$dateTime = Carbon::createFromFormat($conf->dt_format, $fn);
			break;
		case 'filemod':
			$dateTime = Carbon::parse(filemtime($file));
			break;
	endswitch;
	//$dateTime = Carbon::parse($image->exif('DateTime'));
	if ($dateTime->gt($dtHighest)):
		unset($dtHighest);
		$dtHighest = $dateTime->copy();
	endif;
	$save_location = $conf->imgfolder.'/'.$dateTime->format('Y-m-d-His').'.jpg'; // Where to put the resulting file
	if (!file_exists($save_location)):
		// Produces the file if it was not produced earlier (ie. it is not a duplicate)
		
		// Place the date on the picture
		$date_offset = 80;
		$image->text($dateTime->format($conf->date_format), ($image->width()-200), ($image->height()-$date_offset), function($font) use ($conf) {
			$font->file($conf->font);
		    $font->size($conf->fontsize_date);
		    $font->color([255, 255, 255, 0.6]); // rgba
		   	$font->align('left');
		    $font->valign('top');
		});
		// Place the time. Mind that the font size might throw this off a bit
		$time_offset = $date_offset - $conf->fontsize_date;
		$image->text($dateTime->format($conf->time_format), ($image->width()-200), ($image->height()-$time_offset), function($font) use ($conf) {
			$font->file($conf->font);
		    $font->size($conf->fontsize_time);
		    $font->color([255, 255, 255, 0.7]); // rgba
		   	$font->align('left');
		    $font->valign('top');
		});

		// Insert the logo, if set
		if (! $conf->nologo) $image->insert($logo, 'top-left', 10, 10);


		//$save_location = $conf->destfolder.'image-' . sprintf('%06d', $filenum) . $conf->filetype;

		$image->save($save_location, $conf->jpeg_quality);

		echo '-> '.$save_location."\n";
		$iSaved++;
	else:
		// We have already made this image, so we call it a duplicate
		echo '-> duplicate, deleting'."\n";
		unlink($file);
		$iDupes++;
	endif;
	unset($image);
endforeach;
echo "\nProcessed $iFileNum files, saved $iSaved, skipped $iDupes duplicates\n\n";
unset($manager);

echo "2. Creating the image sequence\n";
$files = glob($conf->imgfolder.'/*.{jpg}', GLOB_BRACE);
$iFileNum = 0;
foreach ($files as $file):
	$iFileNum++;
	$newname = $conf->seqfolder.'/seq-'. sprintf('%08d', $iFileNum) . '.jpg';
	copy($file, $newname);
	echo ' '. $file . ' -> '. $newname."\n";
endforeach;

// Emptying the temp folder for processed images
if ($conf->keep_tmp_processed == 0) emptyFolderContents($conf->imgfolder);

$videoname = 'video/timelapse'. $dtHighest->format('_Y-m-d') .'_1080p';

if (strtolower($conf->video_format) == 'hevc'):
	$ffmpeg_hevc = 'ffmpeg -y -framerate '.$conf->framerate.' -i '.$conf->seqfolder.'/seq-%08d.jpg -c:v libx265 -crf '.$conf->crf_hevc.' -tag:v hvc1 -movflags +faststart -an '.$videoname.'_hevc.mp4';
	echo "\n3. Creating HEVC video...\n";
	exec($ffmpeg_hevc);
else:
	$ffmpeg_x264 = 'ffmpeg -y -framerate '.$conf->framerate.' -i '.$conf->seqfolder.'/seq-%08d.jpg -c:v libx264 -crf 25 -movflags +faststart -an '.$videoname.'_x264.mp4';
	echo "\n3. Creating x264 video...\n";
	exec($ffmpeg_x264);
endif;

// Emptying the image sequence folder
if ($conf->keep_tmp_sequence == 0) emptyFolderContents($conf->seqfolder);

echo "    === T H E   E N D ===  \n";
?>