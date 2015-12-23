<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Storage;

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
    	//get the inputs
        $sourceFolder = $this->argument('sourcefolder');
        $destinationFolder = $this->argument('destinationfolder');
        $createOnlyContentfulFolders = $this->option('create-only-contentful-folders');
        $createDayFolders = $this->option('create-day-folders');
        $priorityDateForSort = $this->option('priority-date-for-sort');

        //set allowed options for --priority-date-for-sort (-p)
        $allowedPriorityDateForSort = ['metadata', 'filecreate', 'filemodified'];

        //check option passed for --priority-date-for-sort (-p) is allowed
        if(!in_array($priorityDateForSort, $allowedPriorityDateForSort))
        {
        	$this->error('--priority-date-for-sort (-p) must be one of ' . implode(', ', $allowedPriorityDateForSort));
        	exit(1);	//non-zero
        }

        //check $sourceFolder and $destinationFolder look and smell like folders
        if(!is_dir($sourceFolder) or !is_dir($destinationFolder))
        {
        	$this->error('Please ensure that sourcefolder and destinationfolder are actually folders');
        	exit(1);	//non-zero
        }

        $files = Storage::allFiles($sourceFolder);
        foreach($files as $file){DUMP($file);}

        //we are good to go from here
        
        
    }

}
