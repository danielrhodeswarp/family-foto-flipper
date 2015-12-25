<?php

class FileDatingAndMovingTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testSummat()
    {
        //$this->assertEquals(Storage::allFiles('sample_sourcefolder'), Storage::allFiles('sample_sourcefolder'));
        //
        
        /*Storage::shouldReceive('allFiles')
                    ->once()
                    ->with('sample_sourcefolder')
                    ->andReturn('allFilesArray');*/

        Cache::shouldReceive('get')
                    ->once()
                    ->with('key')
                    ->andReturn('value');
    }
}
