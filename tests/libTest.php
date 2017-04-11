<?php

use NeoHandlebars\MoreDraw;

require __DIR__ . './../vendor/autoload.php';

class MoreDrawTests extends PHPUnit_Framework_TestCase
{
    /** @var MoreDraw */
    private $lib;

    private $testFileName = '_unitTest';
    private $testFilePath;
    private $testFIleContent = "Hello {{name}}!";

    private $testPartialsPath;
    private $testPartialsDirName = '_testPartials';
    private $testPartialsCount = 3;

    private $testData = [
        'name' => 'world'
    ];

    private $testExpectedResultWithData = "Hello world!";
    private $testExpectedResultMissingData = "Hello !";

    protected function setUp()
    {
        $this->lib = new MoreDraw();
        $this->lib->init();

        $this->testFilePath = $this->lib->_getTemplateDir() . "/" . $this->testFileName . "." . $this->lib->_getTemplateExtension();
        $this->testPartialsPath = $this->lib->_getTemplateDir() . "/" . $this->testPartialsDirName;

        // make test file
        $this->makeTestFile();
        $this->makeTestPartials();

        // clear cache
        if (is_file($this->lib->_getCacheMapFile())) {
            unlink($this->lib->_getCacheMapFile());
        }
    }

    protected function tearDown()
    {
        $this->removeTestFile();
        $this->removeTestPartials();

        // clear cache
        if (is_file($this->lib->_getCacheMapFile())) {
            unlink($this->lib->_getCacheMapFile());
        }

        $this->lib = null;
    }

    /**
     * @covers NeoHandlebars\MoreDraw::getTemplate
     */
    public function testGetTemplate()
    {
        $template = $this->lib->getTemplate($this->testFileName);
        $this->assertEquals($this->testFIleContent, $template);
    }

    /**
     * @covers NeoHandlebars\MoreDraw::getTemplate
     * @expectedException Exception
     */
    public function testGetTemplate_Broken()
    {
        $this->lib->getTemplate(null);
    }

    /**
     * @covers NeoHandlebars\MoreDraw::getJSWrapper
     */
    public function testJSWrapper()
    {
        // get wrapper
        try {
            $output = $this->lib->getJSWrapper($this->testFileName);

            $this->assertContains('text/x-handlebars-template', $output, 'Not found "x-handlebars-template" type.');
            $this->assertContains('hb-' . $this->testFileName, $output, 'Not found valid id of script wrapper');
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    /**
     * @covers NeoHandlebars\MoreDraw::render
     */
    public function testRender()
    {
        // test
        try {
            // without data
            $output = $this->lib->render($this->testFileName);
            $this->assertEquals($this->testExpectedResultMissingData, $output);

            // with data
            $output = $this->lib->render($this->testFileName, $this->testData);
            $this->assertEquals($this->testExpectedResultWithData, $output);
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    /**
     * @covers NeoHandlebars\MoreDraw::getTemplateDataCache
     * @covers NeoHandlebars\MoreDraw::render
     */
    public function testTemplateDataCache()
    {
        // check template cache data
        $testCacheVal = 'test';
        $testCacheIndex = 'testIndex';

        $this->lib->render($this->testFileName, ['val' => $testCacheVal, '_index' => $testCacheIndex]);
        $templateCacheData = $this->lib->getTemplateDataCache();

        $val = $templateCacheData[$this->testFileName][$testCacheIndex]['val'];
        $this->assertEquals($testCacheVal, $val);
    }

    /**
     * @covers NeoHandlebars\MoreDraw::render
     * @expectedException Exception
     */
    public function testRender_WithoutName()
    {
        $this->lib->render(null);
    }

    /**
     * @covers NeoHandlebars\MoreDraw::addPartial
     */
    public function testAddPartial()
    {
        $this->lib->clearPartials();

        for ($i = 1; $i <= $this->testPartialsCount; $i++) {
            $partial = $this->testPartialsDirName . "/" . $this->testFileName . $i;
            $this->lib->addPartial($partial);
        }

        $this->doTestPartials();
    }

    /**
     * @covers NeoHandlebars\MoreDraw::addPartial
     * @expectedException Exception
     */
    public function testAddPartial_Broke()
    {
        $this->lib->clearPartials();
        $this->lib->addPartial(null);
    }

    /**
     * @covers NeoHandlebars\MoreDraw::addPartial
     */
    public function testAddPartial_AlreadyExist()
    {
        $this->lib->clearPartials();

        $partial = $this->testPartialsDirName . "/" . $this->testFileName . $this->testPartialsCount;

        $status = $this->lib->addPartial($partial);
        $this->assertTrue($status);

        $status = $this->lib->addPartial($partial);
        $this->assertFalse($status);

    }

    /**
     * @covers NeoHandlebars\MoreDraw::addManyPartials
     */
    public function testManyPartials()
    {
        $this->lib->clearPartials();
        $this->lib->addManyPartials($this->testPartialsDirName);
        $this->doTestPartials();
    }

    /**
     * @covers NeoHandlebars\MoreDraw::addManyPartials
     * @expectedException Exception
     */
    public function testManyPartials_WithoutName()
    {
        $this->lib->clearPartials();
        $this->lib->addManyPartials(null);
    }

    /**
     * @covers NeoHandlebars\MoreDraw::addManyPartials
     * @expectedException Exception
     */
    public function testManyPartials_NotExistFile()
    {
        $this->lib->clearPartials();
        $this->lib->addManyPartials('!notExistFile__/');
    }


    /**
     * @covers NeoHandlebars\MoreDraw::removePartial
     */
    public function testRemovePartials()
    {
        $this->lib->clearPartials();
        $this->lib->addManyPartials($this->testPartialsDirName);

        $rnd = floor(rand(1, $this->testPartialsCount));
        $this->lib->removePartial($this->testPartialsDirName . "/" . $this->testFileName . $rnd);

        $partials = $this->lib->getPartials();

        $this->assertCount($this->testPartialsCount - 1, $partials);
        $ind = 0;
        for ($i = 0; $i < $this->testPartialsCount; $i++) {
            ++$ind;

            if ($ind == $rnd) {
                continue;
            }

            $name = $this->testPartialsDirName . "/" . $this->testFileName . $ind;
            $this->assertContains($name, array_keys($partials));

            $data = $partials[$name];

            $this->assertEquals("partial {$ind}", $data, "Partial content not valid");
            $this->assertEquals($this->testPartialsDirName . "/" . $this->testFileName . $ind, $name, "Partial name not valid");
        }
    }

    /**
     * @covers NeoHandlebars\MoreDraw::removePartial
     */
    public function testRemovePartials_NotExist()
    {
        $this->lib->clearPartials();

        $partial = $this->testPartialsDirName . "/" . $this->testFileName . $this->testPartialsCount;

        // add
        $this->lib->addPartial($partial);

        // exist
        $status = $this->lib->removePartial($partial);
        $this->assertTrue($status);

        // not exist any more
        $status = $this->lib->removePartial($partial);
        $this->assertFalse($status);


    }

    /**
     * @covers NeoHandlebars\MoreDraw::getPartials
     */
    public function testGetPartials()
    {
        $this->lib->clearPartials();
        $this->lib->addManyPartials($this->testPartialsDirName);
        $partials = $this->lib->getPartials();

        $rnd = floor(rand(1, $this->testPartialsCount));
        $rndElm = $this->testPartialsDirName . "/" . $this->testFileName . $rnd;

        $this->assertCount($this->testPartialsCount, $partials);
        $this->assertArrayHasKey($rndElm, $partials);
    }

    /**
     * @covers NeoHandlebars\MoreDraw::clearPartials
     */
    public function testClearPartials()
    {

        $this->lib->addManyPartials($this->testPartialsDirName);
        $this->lib->clearPartials();
        $partials = $this->lib->getPartials();

        $this->assertCount(0, $partials);
    }

    // staff ==========

    private function makeTestFile()
    {
        file_put_contents($this->testFilePath, $this->testFIleContent);
    }

    private function removeTestFile()
    {
        if (is_file($this->testFilePath)) {
            unlink($this->testFilePath);
        }
    }

    private function makeTestPartials()
    {
        if (!is_dir($this->testPartialsPath)) {
            mkdir($this->testPartialsPath);
        }
        for ($i = 1; $i <= $this->testPartialsCount; $i++) {
            file_put_contents($this->testPartialsPath . "/" . $this->testFileName . $i . "." . $this->lib->_getTemplateExtension(), "partial {$i}");
        }
    }

    private function removeTestPartials()
    {
        for ($i = 1; $i <= $this->testPartialsCount; $i++) {
            $path = $this->testPartialsPath . "/" . $this->testFileName . $i . "." . $this->lib->_getTemplateExtension();

            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($this->testPartialsPath)) {
            rmdir($this->testPartialsPath);
        }
    }

    private function doTestPartials()
    {
        $dataPartials = $this->lib->getPartials();

        $this->assertCount($this->testPartialsCount, $dataPartials);

        $ind = 0;
        foreach ($dataPartials as $name => $data) {
            ++$ind;

            $this->assertEquals("partial {$ind}", $data, "Partial content not valid");
            $this->assertEquals($this->testPartialsDirName . "/" . $this->testFileName . $ind, $name, "Partial name not valid");
        }
    }

}
