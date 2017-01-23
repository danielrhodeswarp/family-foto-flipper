<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Storage;
use \DateTime;
use \Exception;

/**
 * Mark (ie. rename) duplicate files.
 * It reports about folder-wide duplicates but only acts on same folder duplicates
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
        {--k|key=quick : How to detect duplicates?; md5 (slow! but more accurate generally and especially folder-wide) or quick.}
        {--r|report-only : Report only, do not rename - or delete - any files}
        {--d|delete : Delete - instead of renaming - duplicates}
        {--p|position=prefix : Where, in the filename, to put the "DUPLICATE" marker?; prefix (start of filename) or suffix (end of filename but before the file extension)}
        ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Duplicate file marker (ie. renamer). Acts upon same folder duplicates but only reports for folder-wide duplicates.';

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
    	//for reporting
    	$reporting = [];
        $reporting['total-files-seen'] = 0;
        $reporting['of-which-are-same-folder-duplicates'] = 0;
    	$reporting['of-which-are-folder-wide-duplicates'] = 0;
        $reporting['same-folder-duplicates-marked'] = 0;   //NOTE, if two files are same then this will be '1'
    	$reporting['same-folder-duplicates-deleted'] = 0;
    	

    	

    	//get the inputs
        $sourceFolder = $this->argument('sourcefolder');
        $key = $this->option('key');
        $reportOnly = $this->option('report-only');
        $delete = $this->option('delete');
        $verbose = $this->option('verbose');
        $position = $this->option('position');
        

        //----input sanity checks----------------------------------------------

        
        //check $sourceFolder looks and smells like a folder
        //if(!is_dir($sourceFolder))
        if(!Storage::has($sourceFolder))
        {
        	$this->error('Please ensure that sourcefolder is actually a folder');
        	exit(1);	//non-zero
        }

        //----/end input sanity checks-----------------------------------------

        
        $folderwideCatalogue = [];	//for working out 'folder-wide-duplicates-seen' reporting


        $allFiles = Storage::allFiles($sourceFolder);
        
        //realtime progress bar only if not verbose (else it mucks up due to all the other stuff ebing echoed)
        if(!$verbose)
        {
            $bar = $this->output->createProgressBar(count($allFiles));
        }

        //get all folders and sub-folders
        $allFolders = Storage::allDirectories($sourceFolder);
        
        //add self folder
        $allFolders[] = $sourceFolder;

        
        

        //process all folders
        foreach($allFolders as $folder)
        {
        	//some temp storage per folder we are looking in
        	$catalogue = [];	//

        	//get all files in this folder (and not any sub-folders)
        	$files = Storage::files($folder);

            //process all files in this folder
        	foreach($files as $file)
        	{
        		$index = $this->getUniqueIndex($key, $file);

        		$catalogue[$index][] = $file;
                $folderwideCatalogue[$index][] = $file;

                        		
        		//DUMP($key);

                if(!$verbose)
                {
        		  $bar->advance();
                }

                $reporting['total-files-seen']++;
        	}

            //act upon file catalogue got from above loop
        	foreach($catalogue as $fileArray)
        	{
                $reporting['of-which-are-same-folder-duplicates'] += count($fileArray) - 1;

                //if more than one file having same index (ie. a probable file duplication)
        		if(count($fileArray) > 1)
        		{
        			
        			
        			
        			

        			if($verbose)
    				{
    					$this->info("{$fileArray[0]} has " . (string)(count($fileArray) - 1) . ' suspected ' . str_plural('duplicate', count($fileArray) - 1) . ' in same folder');
    				}

                    //act only if we need to
                    if($reportOnly)
                    {
                        continue;   //skip
                    }

                    //if we are not set to delete, then rename the files
        			if(!$delete)
        			{

        				//mark (such that the suspected duplicates can be grouped together) and record all after the first one

                        $reporting['same-folder-duplicates-marked'] += count($fileArray) - 1;

        				$grouper = mt_rand(1111, 9999);

        				//or mark them all, including the "original"? (for easier sifting in the file browser)
        				for($loop = 1; $loop < count($fileArray); $loop++)
        				//for($loop = 0; $loop < count($fileArray); $loop++)
        				{
        					//Storage::move($fileArray[$loop], $fileArray[$loop] . ".DUPLICATE_{$grouper}");
        					
        					$pathParts = pathinfo($fileArray[$loop]);
        					$sameFolder = $pathParts['dirname'];
        					
                            if($position == 'prefix')
                            {
                                $newFilename = "DUPLICATE_{$grouper}_{$pathParts['basename']}";    
                            }

                            else
                            {
                                //suffix (but before any file extension)
                                $newFilename = "{$pathParts['filename']}_DUPLICATE_{$grouper}.{$pathParts['extension']}";
                            }
                            

        					Storage::move($fileArray[$loop], "{$sameFolder}/{$newFilename}");
        				}


        			}

                    else
                    {
                        //delete duplicates

                        $reporting['same-folder-duplicates-deleted'] += count($fileArray) - 1;

                        for($loop = 1; $loop < count($fileArray); $loop++)
                        {
                            Storage::delete($fileArray[$loop]);
                        }
                    }
        			

        			//DUMP($fileArray);
        		}
        	}

            
        	
        	//DUMP($catalogue);
        	
        }

        //act upon folder-wide file catalogue got from first loop above
        //TBH this is just an FYI for the user and we don't act on this
        //as too fiddly for us and dangerous for user
        foreach($folderwideCatalogue as $key => $fileArray)
        {
            $reporting['of-which-are-folder-wide-duplicates'] += count($fileArray) - 1;
        
            /*
            if(count($fileArray) > 1)
            {
                VAR_DUMP($key);
                VAR_DUMP($fileArray);
                VAR_DUMP('----');
            }
            */
        }


        
        if(!$verbose)
        {
    	   $bar->finish();
        }

        $this->line('');    //hmmm, this seems to eb necessary...

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

    /**
     * $type is string and 'quick' or 'md5'
     */
    private function getUniqueIndex($type, $flysystemFilepath)
    {
        return $this->{"getUniqueIndex_{$type}"}($flysystemFilepath);
    }

    private function getUniqueIndex_quick($flysystemFilepath)
    {
        $key = Storage::size($flysystemFilepath) . '.' . Storage::getMimetype($flysystemFilepath);

        return $key;
    }

    private function getUniqueIndex_md5($flysystemFilepath)
    {
        $md5 = md5_file($this->flysystemFilepathToAbsoluteFilepath($flysystemFilepath));

        //NOTE $md5 could be bool false here if md5_file() fails
        
        return $md5;
    }

    private function flysystemFilepathToAbsoluteFilepath($flysystemFilepath)
    {
        return storage_path('app') . '/' . $flysystemFilepath;
    }

}
