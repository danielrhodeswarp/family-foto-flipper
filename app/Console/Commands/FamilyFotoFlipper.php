<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Storage;
use \DateTime;
use \Exception;

/**
 * 
 * 
 * @author Daniel Rhodes <daniel.rhodes@warpasylum.co.uk>
 */
class FamilyFotoFlipper extends Command
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
        {--p|priority-date-for-sort=metadata : Date to use for sorting; metadata (photo file tags), filecreate or filemodified.}
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

    	//get the inputs
        $sourceFolder = $this->argument('sourcefolder');
        $destinationFolder = $this->argument('destinationfolder');
        $createOnlyContentfulFolders = $this->option('create-only-contentful-folders');
        $createDayFolders = $this->option('create-day-folders');
        $priorityDateForSort = $this->option('priority-date-for-sort');

        //set allowed options for --priority-date-for-sort (-p)
        $allowedPriorityDateForSort = ['metadata', 'filemodified'];

        //check option passed for --priority-date-for-sort (-p) is allowed
        if(!in_array($priorityDateForSort, $allowedPriorityDateForSort))
        {
        	$this->error('--priority-date-for-sort (-p) must be one of ' . implode(', ', $allowedPriorityDateForSort));
        	exit(1);	//non-zero
        }

        //check $sourceFolder and $destinationFolder look and smell like folders
        //if(!is_dir($sourceFolder) or !is_dir($destinationFolder))
        if(!Storage::has($sourceFolder) or !Storage::has($destinationFolder))
        {
        	$this->error('Please ensure that sourcefolder and destinationfolder are actually folders');
        	exit(1);	//non-zero
        }


        // reader with Native adapter
		$reader = \PHPExif\Reader\Reader::factory(\PHPExif\Reader\Reader::TYPE_NATIVE);

		// reader with Exiftool adapter
		//$reader = \PHPExif\Reader\Reader::factory(\PHPExif\Reader\Reader::TYPE_EXIFTOOL);

		//$exif = $reader->read('/path/to/file');


        $files = Storage::allFiles($sourceFolder);

        $bar = $this->output->createProgressBar(count($files));

        foreach($files as $file)
        {
        	//DUMP($file);	//string filepath
        	
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

        	$targetFolder = "{$destinationFolder}/{$year}/{$month}/";

        	if($createDayFolders)
        	{
        		$targetFolder = "{$destinationFolder}/{$year}/{$month}/{$day}/";
        	}


        	$finalDestination = $targetFolder . pathinfo($file, PATHINFO_BASENAME);

        	try
        	{
        		Storage::makeDirectory($targetFolder);
        		Storage::copy($file, $finalDestination);
        	
        	}

        	catch(\League\Flysystem\FileExistsException $exception)
        	{

        		$reporting['photos-renamed-for-copy-to-destination']++;
        	
        		$newFinalDestination = $targetFolder . mt_rand(111, 999) . pathinfo($file, PATHINFO_BASENAME);

        		$this->error("{$finalDestination} already exists in destinationfolder. Renaming to {$newFinalDestination}");

        		Storage::copy($file, $newFinalDestination);
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
