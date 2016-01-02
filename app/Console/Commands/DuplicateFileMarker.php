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
class DuplicateFileMarker extends Command
{
	/**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'duplicate-file-marker
        {sourcefolder : Source folder eg. /somewhere/or/other/myphotos}
        {--r|report-only : Report only, do not rename - or delete - any files}
        {--a|auto-delete : If two duplicates with very similar filename, delete one}
        ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Duplicate file marker (of files in same folder)';

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
     * Execute the console command. Check sourcefolder recursively for file duplicates in each folder (renaming to originalFilename.originalExtension.DUPLICATE if a duplicate)
     *
     * @return mixed
     */
    public function handle()
    {
    	//"duplicate" means:
    	//[1] same filesize
    	//[2] same mimetype
    	//[3] same fileextension

    	//for reporting
    	$reporting = [];
    	$reporting['same-folder-duplicates-marked'] = 0;
    	$reporting['same-folder-duplicates-deleted'] = 0;
    	$reporting['same-folder-near-duplicates-seen'] = 0;	//TODO
    	$reporting['folder-wide-duplicates-seen'] = 0;	//TODO

    	

    	//get the inputs
        $sourceFolder = $this->argument('sourcefolder');
        $reportOnly = $this->option('report-only');
        $autoDelete = $this->option('auto-delete');
        $verbose = $this->option('verbose');
        

        //----input sanity checks----------------------------------------------

        
        //check $sourceFolder looks and smells like a folder
        //if(!is_dir($sourceFolder))
        if(!Storage::has($sourceFolder))
        {
        	$this->error('Please ensure that sourcefolder is actually a folder');
        	exit(1);	//non-zero
        }

        //----/end input sanity checks-----------------------------------------

        
        $folderwideCatalogue = [];	//for working out 'folder-wide-duplicates-seen' reporting (and actions)


        $allFiles = Storage::allFiles($sourceFolder);
        

        $bar = $this->output->createProgressBar(count($allFiles));

        //get all folders and sub-folders
        $allFolders = Storage::allDirectories($sourceFolder);
        
        //add self folder
        $allFolders[] = $sourceFolder;

        
        $count = 0;

        foreach($allFolders as $folder)
        {
        	//some temp storage per folder we are looking in
        	//$filesizes = [];
        	//$fileextensions = [];
        	//$mimetypes = [];
        	$catalogue = [];	//key is [filesize.fileextension.mimetype]

        	//get all files in this folder (and not any sub-folders)
        	$files = Storage::files($folder);

        	foreach($files as $file)
        	{
        		//lowercase the fileextension AND standardise JPEG to .jpg
        		$fileextension = strtolower(pathinfo(storage_path('app') . '/' . $file, PATHINFO_EXTENSION));
        		if($fileextension == 'jpeg')
        		{
        			$fileextension = 'jpg';
        		}

        		$key = Storage::size($file) . '.' . $fileextension . '.' . Storage::getMimetype($file);

        		if(!array_key_exists($key, $catalogue))
        		{
        			$catalogue[$key] = [];
        			$catalogue[$key][] = $file;
        		}

        		else
        		{
        			$catalogue[$key][] = $file;
        		}
        		
        		//DUMP($key);

        		$bar->advance();
        	}


        	foreach($catalogue as $fileArray)
        	{
        		if(count($fileArray) > 1)
        		{
        			

        			
    				//if auto-delete specified and applicable
    				if($autoDelete and count($fileArray) == 2)
    				{
    					$filename0 = pathinfo($fileArray[0], PATHINFO_FILENAME);
    					$filename1 = pathinfo($fileArray[1], PATHINFO_FILENAME);

    					//if one filename (minus the extension) includes the other [eg. when you have "20150101.jpg" and "20150101 (copy).jpg"]
    					if(str_contains($filename0, $filename1) or str_contains($filename1, $filename0))
    					{

    						$reporting['same-folder-duplicates-deleted']++;

    						if($verbose)
							{
								$this->info("Auto-deleting {$fileArray[0]}");
							}

    						if(!$reportOnly)
    						{
    							//TODO delete the one with longest filename (as that prob the copy)
    							Storage::delete($fileArray[0]);

    							

    							continue;
    						}
    					}
    				}

					//every other case--------
        			
        			//mark (such that the suspected duplicates can be grouped together) and record all after the first one
        			
        			$reporting['same-folder-duplicates-marked'] += count($fileArray) - 1;

        			if($verbose)
    				{
    					$this->info("{$fileArray[0]} has " . (string)(count($fileArray) - 1) . ' suspected duplicates');
    				}

        			if(!$reportOnly)
        			{

        				

        				$grouper = mt_rand(1111, 9999);

        				//or mark them all, including the "original"? (for easier sifting in the file browser)
        				//for($loop = 1; $loop < count($fileArray); $loop++)
        				for($loop = 0; $loop < count($fileArray); $loop++)
        				{
        					//Storage::move($fileArray[$loop], $fileArray[$loop] . ".DUPLICATE_{$grouper}");
        					
        					$pathParts = pathinfo($fileArray[$loop]);
        					$sameFolder = $pathParts['dirname'];
        					$newFilename = "DUPLICATE_{$grouper}_{$pathParts['basename']}";

        					Storage::move($fileArray[$loop], "{$sameFolder}/{$newFilename}");
        				}


        			}
        			

        			//DUMP($fileArray);
        		}
        	}
        	
        	//DUMP($catalogue);
        	
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
