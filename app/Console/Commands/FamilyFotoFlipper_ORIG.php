<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Storage;
use \DateTime;
use \Exception;

//spec, questions, notes etc

/*

[] source folder can have subfolders

[] what to do with files in source that aren't photo or video?
      (process as per usual or detect on all files using MIME type?)



*/

/**
 * 
 * 
 * @author Daniel Rhodes <daniel.rhodes@warpasylum.co.uk>
 */
class FamilyFotoFlipper_ORIG extends Command
{
	/**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'family-foto-flipper
        {sourcefolder : Source folder eg. /somewhere/or/other/myphotos}
        {destinationfolder : Destination folder eg. /could/be/anywhere/mysortedphotos}
        {--c|create-only-contentful-folders : Do not create destination month (or day) folders that would be empty}
		{--d|create-day-folders : Also create destination day (of month) folders}
        {--p|priority-date-for-sort=metadata : Date to use for sorting; metadata (photo file tags) or filemodified.}
        {--o|on-duplicate=rename : If two filenames clash when putting in destinationfolder, rename or clobber?}
        {--s|separate-videos-and-photos : When copying to destinationfolder, put videos and photos in separate folders (but still sorted accordingly by date)}
        ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Family foto flipper';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
    	//for reporting
    	$reporting = [];
    	$reporting['photo-but-no-metadata-date'] = 0;
    	$reporting['photo-but-corrupt-metadata-date'] = 0;
    	$reporting['probably-not-a-photo'] = 0;
    	$reporting['photos-renamed-for-copy-to-destination'] = 0;
    	$reporting['photos-clobbered-for-copy-to-destination'] = 0;

    	//get the inputs
        $sourceFolder = $this->argument('sourcefolder');
        $destinationFolder = $this->argument('destinationfolder');
        $createOnlyContentfulFolders = $this->option('create-only-contentful-folders');	//support for this is TODO
        $createDayFolders = $this->option('create-day-folders');
        $priorityDateForSort = $this->option('priority-date-for-sort');
        $onDuplicate = $this->option('on-duplicate');
        $separateVideosAndPhotos = $this->option('separate-videos-and-photos');

        //----input sanity checks----------------------------------------------

        //set allowed options for --priority-date-for-sort (-p)
        $allowedPriorityDateForSort = ['metadata', 'filemodified'];

        //check option passed for --priority-date-for-sort (-p) is allowed
        if(!in_array($priorityDateForSort, $allowedPriorityDateForSort))
        {
        	$this->error('--priority-date-for-sort (-p) must be one of ' . implode(', ', $allowedPriorityDateForSort));
        	exit(1);	//non-zero
        }

        //set allowed options for --on-duplicate (-o)
        $allowedOnDuplicate = ['rename', 'clobber'];

        //check option passed for --on-duplicate (-o) is allowed
        if(!in_array($onDuplicate, $allowedOnDuplicate))
        {
        	$this->error('--on-duplicate (-o) must be one of ' . implode(', ', $allowedOnDuplicate));
        	exit(1);	//non-zero
        }

        //check $sourceFolder and $destinationFolder look and smell like folders
        //if(!is_dir($sourceFolder) or !is_dir($destinationFolder))
        if(!Storage::has($sourceFolder) or !Storage::has($destinationFolder))
        {
        	$this->error('Please ensure that sourcefolder and destinationfolder are actually folders');
        	exit(1);	//non-zero
        }

        //----/end input sanity checks-----------------------------------------

        // reader with Native adapter
		$reader = \PHPExif\Reader\Reader::factory(\PHPExif\Reader\Reader::TYPE_NATIVE);

		// reader with Exiftool adapter
		//$reader = \PHPExif\Reader\Reader::factory(\PHPExif\Reader\Reader::TYPE_EXIFTOOL);

		//$exif = $reader->read('/path/to/file');


        $files = Storage::allFiles($sourceFolder);

        $bar = $this->output->createProgressBar(count($files));

        //$counter = 1;

        foreach($files as $file)
        {
        	//DUMP($counter . '] ' . $file);	//string filepath
	       	//DUMP($file . '---' . pathinfo(storage_path('app') . '/' . $file, PATHINFO_EXTENSION) . '----' . Storage::getMimetype($file));
        	//$counter++;
        	//CONTINUE;
        	
        	try
        	{
        		$exif = $reader->read(storage_path('app') . '/' . $file);	//what a good Storage:: way to do this (get the full path)?

        		//date from metadata
        		$metadataDate = $exif->getCreationdate();	//will be false or a DateTime (can be a goofy, invalid DateTime if date is missing or corrupt in the metadata)
				
				//just for reporting
	        	if(!$metadataDate)
	        	{
	        		$reporting['photo-but-no-metadata-date']++;
	        	}
        	}

        	catch(Exception $e)	//will get here if, eg, file is a video file and not a photo etc
        	{
        		$metadataDate = false;
        		$reporting['probably-not-a-photo']++;
        	}



        	$metadataDateValid = false;
        	if($metadataDate instanceof DateTime)
        	{
        		$metadataDateValid = checkdate($metadataDate->format('m'), $metadataDate->format('d'), $metadataDate->format('Y'));

        		if(!$metadataDateValid)
        		{
        			$this->error("Got a DateTime from {$file} metadata but it wasn't valid [{$metadataDate->format('Y-m-d')}]. Using filemodified date instead.");
        			$reporting['photo-but-corrupt-metadata-date']++;
        		}
        	}

        	
        	//DUMP($metadataDate);

	        //date from filemodified
        	$filemodifiedDate = new DateTime();
        	$filemodifiedDate->setTimestamp(Storage::lastModified($file));	//we assume lastModified timestamp is always present on a file

        	//DUMP($metadataDate);
        	//DUMP($filemodifiedDate);




        	//DUMP();
        	//DUMP(date('Y m d', Storage::lastModified($file)));	//a timestamp

        	//DUMP('a time: ' . date('Y m d', fileatime(storage_path('app') . '/' . $file)));
        	//DUMP('c time: ' . date('Y m d', filectime(storage_path('app') . '/' . $file)));
        	//DUMP('m time: ' . date('Y m d', filemtime(storage_path('app') . '/' . $file)));
        	//DUMP('----');


        	//we are good to go from here


        	$dateToUse = '';

        	if($priorityDateForSort == 'metadata')
        	{
        		$dateToUse = $metadataDate;

        		if(!$metadataDate or !$metadataDateValid)
        		{
        			$dateToUse = $filemodifiedDate;
        		}
        	}

        	elseif($priorityDateForSort == 'filemodified')
        	{
        		$dateToUse = $filemodifiedDate;
        	}

        	//create folder for this date in the (already existing) destinationfolder
        	//and copy the file over
        	
        	$year = $dateToUse->format('Y');
        	$month = $dateToUse->format('m');
        	$day = $dateToUse->format('d');

        	$targetSubfolder = "{$year}/{$month}/";

        	//support --create-day-folders
        	if($createDayFolders)
        	{
        		$targetSubfolder = "{$year}/{$month}/{$day}/";
        	}

        	//support --separate-videos-and-photos
        	if($separateVideosAndPhotos)
        	{
				$mimetype = Storage::getMimetype($file);

				if(str_contains($mimetype, 'video') or strtolower(pathinfo(storage_path('app') . '/' . $file, PATHINFO_EXTENSION)) == 'mts')
				{
					$targetSubfolder = "videos/{$targetSubfolder}";
				}

				else
				{
					$targetSubfolder = "photos/{$targetSubfolder}";
				}
        		
        	}

        	$targetFolder = "{$destinationFolder}/{$targetSubfolder}";

        	$finalDestination = $targetFolder . pathinfo($file, PATHINFO_BASENAME);

        	try
        	{
        		Storage::makeDirectory($targetFolder);
        		Storage::copy($file, $finalDestination);
        	
        	}

        	catch(\League\Flysystem\FileExistsException $exception)
        	{

        		if($onDuplicate == 'rename')
        		{
	        		$reporting['photos-renamed-for-copy-to-destination']++;
	        	
	        		$pathParts = pathinfo($file);	//will not contain 'extension' index if file has no extension
	        		if(!array_key_exists('extension', $pathParts))
	        		{
	        			$pathParts['extension'] = '';
	        		}

	        		$newFinalDestination = $targetFolder . $pathParts['filename'] . '_alt_' . mt_rand(1111, 9999) . '.' . $pathParts['extension'];

	        		$this->error("{$finalDestination} already exists in destinationfolder. Renaming to {$newFinalDestination}");

	        		Storage::copy($file, $newFinalDestination);
	        	}

	        	elseif($onDuplicate == 'clobber')
	        	{
	        		//NOTE this is more of a NOP than an actual overwrite clobber (ie. first one is kept)
	        		
	        		$reporting['photos-clobbered-for-copy-to-destination']++;
	        	}
        	}
        	
        		


        	$bar->advance();

        }

        
    	$bar->finish();

    	//summary report
        $dataRows = [];

        foreach ($reporting as $key => $value) {
            $dataRows[] = [$key, $value];
        }
        
        $this->table(
            ['Entity', 'Count'],
            $dataRows
        );
        
    }

}
