<?php

namespace NeoHandlebars;

/*
 * Handlebars template engine
 * @author: K.Perov <fe3dback@yandex.ru>
 *
 * Based on original handlebars parser:
 * https://github.com/zordius/lightncandy
 */

use Exception;
use LightnCandy\LightnCandy;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class MoreDraw
{
    private $useMemCache = true;
    private $memCached = [];
    private $partials = [];

    // this store all data provided to templates
    private $templateDataCache = [];

    const TEMPLATE_DIR = __DIR__ . '/templates';
    const TEMPLATE_EXT = 'hbs';
    const CACHE_DIR = __DIR__ . '/cache';

    private $p_TEMPLATE_DIR = self::TEMPLATE_DIR;
    private $p_TEMPLATE_EXT = self::TEMPLATE_EXT;
    private $p_CACHE_DIR = self::CACHE_DIR;
    private $p_CACHE_MAP_FILE = __DIR__ . "/map.json";


    /**
     * Add template to global partials array
     * all partial can be used from any other templates
     * like this: {{> name}}
     *
     * This template get all data from parent
     *
     * @param $name - template name
     *
     * @return bool - true if success, false if partial already added.
     * @throws Exception
     */
    public function addPartial($name)
    {
        $name = $name ?: false;

        if (!$name) {
            throw new Exception("Can't add partial without name");
        }

        $partial = $this->getTemplate($name);
        if (!in_array($name, array_keys($this->partials))) {
            $this->partials[$name] = $partial;
            asort($this->partials);
            return true;
        }

        return false;
    }

    /**
     * Add all templates in folder $folderName
     * to global partials array
     *
     * @param $folderName
     *
     * @throws Exception
     */
    public function addManyPartials($folderName)
    {
        $folderName = $folderName ?: false;

        if (!$folderName) {
            throw new Exception("Can't add partials without folder name");
        }

        $fullPath = $this->p_TEMPLATE_DIR . "/{$folderName}";
        if (!is_dir($fullPath)) {
            throw new Exception("Can't add partials in {$folderName}, folder {$fullPath} not exist.");
        }

        // get current version
        $iterator = new RecursiveIteratorIterator
        (
            new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
        );

        foreach ($iterator as $path => $dir) {
            if ($dir->isFile()) {
                $name = str_replace($this->p_TEMPLATE_DIR . '/', '', $path);
                $name = str_replace('.' . $this->p_TEMPLATE_EXT, '', $name);
                $this->addPartial($name);
            }
        }
    }

    /**
     * Remove template from global partials array
     *
     * @param $name - template name
     *
     * @return bool - true if success, false if partial already removed.
     */
    public function removePartial($name)
    {
        if (in_array($name, array_keys($this->partials))) {
            unset($this->partials[$name]);
            return true;
        }

        return false;
    }

    /**
     * Remove all partials
     */
    public function clearPartials()
    {
        $partials = $this->getPartials();
        foreach ($partials as $name => $data) {
            $this->removePartial($name);
        }
    }

    /**
     * Return array of partials
     * [name] => template raw string
     *
     * @return array
     */
    public function getPartials()
    {
        return $this->partials;
    }

    /**
     * Render template with data
     * and return back html string
     *
     * @param       $name - template name
     * @param array $data - any data (if data have _index key, all provided array will be exported to js)
     * @param bool $useCache - DO NOT TURN OFF CACHE
     *
     * @return string - html output
     * @throws Exception
     */
    public function render($name, $data = [], $useCache = true)
    {
        $compileSettings = [
            'flags' => LightnCandy::FLAG_HANDLEBARSJS,
            'partials' => $this->partials
        ];

        $name = $name ?: false;
        $data = $data ?: [];
        $useCache = $useCache ?: true;

        if (!$name) {
            throw new Exception("Can't render handlebars template. Template name empty");
        }

        $renderer = false;

        // check cache
        if ($useCache) {
            if ($this->useMemCache) {
                if (in_array($name, array_keys($this->memCached))) {
                    $renderer = $this->memCached[$name];
                }
            }

            if (!$renderer) {
                $cached = $this->p_CACHE_DIR . "/{$name}.php";

                if (!is_file($cached)) {
                    // @codeCoverageIgnoreStart

                    $path = pathinfo($cached);
                    $fullDirPath = $path['dirname'];

                    if (!is_dir($fullDirPath)) {
                        $dirExist = mkdir($fullDirPath, 0777, true);
                        if (!$dirExist) {
                            throw new Exception("Can't create dir '{$fullDirPath}' to save template cache");
                        }
                    }

                    $template = $this->getTemplate($name);

                    try {
                        $phpize = LightnCandy::compile($template, $compileSettings);
                    } catch (Exception $e) {
                        throw new Exception("Can't render handlebars template. Internal Error");
                    }

                    $status = file_put_contents($cached, "<?php \n" . $phpize . "\n?>");
                    if (!$status) {
                        throw new Exception("Can't save template '{$name}' cache to '{$cached}' file");
                    }

                    // @codeCoverageIgnoreEnd
                }

                $renderer = include($cached);

                if ($this->useMemCache) {
                    $this->memCached[$name] = $renderer;
                }
            }
        } else {
            // @codeCoverageIgnoreStart
            try {
                $template = $this->getTemplate($name);
                $phpize = LightnCandy::compile($template, $compileSettings);
                $renderer = eval($phpize);
            } catch (Exception $e) {
                throw new Exception("Can't render handlebars template. Internal Error");
            }
            // @codeCoverageIgnoreEnd
        }

        // @codeCoverageIgnoreStart
        if (!$renderer) {
            throw new Exception("Can't render handlebars template. Renderer not set");
        }
        // @codeCoverageIgnoreEnd

        if (isset($data['_index'])) {
            $this->templateDataCache[$name][$data['_index']] = $data;
        }

        return $renderer($data);
    }

    /**
     * Return template string, usable in  latest js render
     * on frontend
     *
     * @param $name - template name
     * @return string - raw template string
     * @throws Exception
     */
    public function getTemplate($name)
    {
        $raw = $this->p_TEMPLATE_DIR . "/{$name}." . $this->p_TEMPLATE_EXT;
        if (is_file($raw)) {
            return file_get_contents($raw);
        }

        Throw new Exception("Can't get handlebars template {$name} at {$raw}. File not found");
    }

    /**
     * Return raw template string wrapped with script tag (for js use)
     * In js:   let s = $("#{name}").html();    // get template raw string
     *          let o = Handlebars.compile(s);
     *
     * @param $name - template name
     * @return string
     */
    public function getJSWrapper($name)
    {
        $template = $this->getTemplate($name);
        $output = trim(str_replace(["\n", "\t", "\r"], "", $template));
        $jsId = str_replace("/", "__", $name);
        return <<<HTML
<script id="hb-{$jsId}" type="text/x-handlebars-template">{$output}</script>
HTML;

    }

    /**
     * Get all raw template strings,
     * and wrap each by "x-handlebars-template" tag.
     *
     * @return string
     *
     * @codeCoverageIgnore
     */
    public function getAllJSTemplates()
    {
        $resultData = "";

        $iterator = new RecursiveIteratorIterator
        (
            new RecursiveDirectoryIterator($this->p_TEMPLATE_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
        );

        foreach ($iterator as $path => $dir) {
            if ($dir->isFile()) {
                $name = str_replace($this->p_TEMPLATE_DIR . '/', '', $path);
                $name = str_replace('.' . $this->p_TEMPLATE_EXT, '', $name);
                $resultData .= $this->getJSWrapper($name);
            }
        }

        $templatesData = json_encode($this->getTemplateDataCache(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $partialsData = json_encode($this->getPartials());

        $resultData .= <<<HTML
<script type="text/javascript">
	__handlebars_server_partials = {$partialsData};
</script> 
HTML;

        $resultData .= <<<HTML
<script type="text/javascript">
	__handlebars_server_data = {$templatesData}
</script>
HTML;

        return $resultData;
    }

    /**
     * Return all given templates data (with indexes)
     *
     * @return array
     */
    public function getTemplateDataCache()
    {
        return $this->templateDataCache;
    }

    /**
     * Must be run before any other functions
     * each time when application start
     * (ex in /local/php_interface/init.php)
     *
     * @param array $config - can contain:
     *
     * @param templates_dir         //dir where templates will be located (default vendor/src/templates) - no back slash
     * @param cache_dir             //where templates cache will be stored (default vendor/src/cache) - no back slash
     * @param cache_map_dir         //where cache map file will be stored (default vendor/src) - no back slash
     *                              //cache_map_dir can't be same as cache_dir !
     * @param templates_extension   //extension of all templates (default "hbs") - no dot
     *
     *
     *
     * @throws Exception
     * @codeCoverageIgnore
     */
    public function init($config = [])
    {
        if (isset($config['templates_dir'])) {
            $this->p_TEMPLATE_DIR = $config['templates_dir'];
        }
        if (isset($config['cache_dir'])) {
            $this->p_CACHE_DIR = $config['cache_dir'];
        }
        if (isset($config['cache_map_dir'])) {
            $this->p_CACHE_MAP_FILE = $config['cache_map_dir'] . "/map.json";
        }
        if (isset($config['templates_extension'])) {
            $this->p_TEMPLATE_EXT = $config['templates_extension'];
        }

        if (!is_dir($this->p_TEMPLATE_DIR)) {
            if (!mkdir($this->p_TEMPLATE_DIR)) {
                throw new Exception("Can't create template dir for handlebars '" . $this->p_TEMPLATE_DIR . "'");
            };
        }
        if (!is_dir($this->p_CACHE_DIR)) {
            if (!mkdir($this->p_CACHE_DIR)) {
                throw new Exception("Can't create cache dir for handlebars '" . $this->p_CACHE_DIR . "'");
            };
        }
        if (!is_dir(dirname($this->p_CACHE_MAP_FILE))) {
            if (!mkdir(dirname($this->p_CACHE_MAP_FILE))) {
                throw new Exception("Can't create cache dir for map file '" . $this->p_CACHE_MAP_FILE . "'");
            };
        }

        $this->clearOldCache();
    }

    /**
     * @return string
     * @codeCoverageIgnore
     */
    public function _getTemplateDir()
    {
        return $this->p_TEMPLATE_DIR;
    }

    /**
     * @return string
     * @codeCoverageIgnore
     */
    public function _getCacheDir()
    {
        return $this->p_CACHE_DIR;
    }

    /**
     * @return string
     * @codeCoverageIgnore
     */
    public function _getCacheMapFile()
    {
        return $this->p_CACHE_MAP_FILE;
    }

    /**
     * @return string
     * @codeCoverageIgnore
     */
    public function _getTemplateExtension()
    {
        return $this->p_TEMPLATE_EXT;
    }

    /**
     * Allow use memCache.
     * This increase performance to many times when render used in loops
     *
     * Default is ON
     *
     * @param bool $bool - allow y/n
     *
     * @codeCoverageIgnore
     */
    public function useMemCache($bool = false)
    {
        $bool = !!$bool;
        $this->useMemCache = $bool;
    }

    // ==============================================================

    /**
     * Drop old cache. At next run, new cache will be build.
     * @codeCoverageIgnore
     */
    private function clearOldCache()
    {
        // test if map in cache folder
        $mapFile = $this->_getCacheMapFile();
        $cacheFolder = $this->_getCacheDir();

        if (strpos($mapFile, $cacheFolder) !== false)
        {
            throw new Exception("Cache map file can't be placed in cache directory, specify any other place.");
        }

        // clear

        $map = [];

        // last edit version
        $cache = [];

        if (is_file($this->p_CACHE_MAP_FILE)) {
            $cache = json_decode(file_get_contents($this->p_CACHE_MAP_FILE), true);
        }

        // get current version
        $iterator = new RecursiveIteratorIterator
        (
            new RecursiveDirectoryIterator($this->p_TEMPLATE_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
        );

        $files = array();
        foreach ($iterator as $path => $dir) {
            if ($dir->isFile()) {
                $files[] = str_replace($this->p_TEMPLATE_DIR . '/', '', $path);
            }
        }

        $allowDropCache = false;

        if (count($files) >= 1) {
            // test file version and remove old cache
            while ($f = array_shift($files)) {
                if (in_array($f, [".", ".."])) {
                    continue;
                }

                $path_template = $this->p_TEMPLATE_DIR . "/{$f}";
                $name = str_replace('.' . $this->p_TEMPLATE_EXT, '', $f);

                if (file_exists($path_template)) {
                    clearstatcache(true, $path_template);
                    $lastEdit = filemtime($path_template);

                    // compare with oldMap
                    if (isset($cache[$name])) {
                        $cacheEdit = $cache[$name];
                        if ($cacheEdit < $lastEdit) {
                            $allowDropCache = true;
                        }
                    }

                    $map[$name] = $lastEdit;
                }
            }
        }

        if ($allowDropCache) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->p_CACHE_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }

            rmdir($this->p_CACHE_DIR);
        }

        file_put_contents($this->p_CACHE_MAP_FILE, json_encode($map, JSON_PRETTY_PRINT));
    }
}