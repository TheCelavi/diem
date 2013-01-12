<?php
require_once dmOs::join(sfConfig::get('sf_root_dir'), 'diem/dmCorePlugin/lib/vendor/simplehtmldom/simple_html_dom.php');
/*
 * @author TheCelavi
 */

class dmPageCache
{

    protected $filesystem;
    protected $dispacher;
    protected $context;

    public function __construct(dmContext $context, sfEventDispatcher $dispacher, dmFilesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        $this->dispacher = $dispacher;
        $this->context = $context;
    }

    public function connect()
    {
        if (sfConfig::get('dm_page_cache_enabled')) {            
            $this->getDispacher()->connect('response.filter_content', array($this, 'listenToResponseFilterContent'));
        }
    }

    public function listenToResponseFilterContent(sfEvent $event, $content)
    {      
        if ($this->context->getUser()->can(dmPageCacheLoader::getUserPageEditPermissions())) {
            return $content;
        }
        
        $page = $this->getPage();
        if  ($page->getIsStatic() && !$page->isSignin()) {
            $this->checkCachePool();
            file_put_contents(dmOs::join(
                $this->getCacheDir(),
                $this->getCacheName(
                    $_SERVER['PATH_INFO'],
                    implode('', array_merge($_GET, $_POST)),
                    $page->getIsSecure(),
                    $page->getCredentials()
                )
            ), $content);
        } elseif (!$page->isSignin() && !$page->getIsStatic()) {            
            $this->checkCachePool();
            file_put_contents(dmOs::join(
                $this->getCacheDir(),
                $this->getCacheName(
                    $_SERVER['PATH_INFO'],
                    implode('', array_merge($_GET, $_POST)),
                    $page->getIsSecure(),
                    $page->getCredentials(),
                    'php'
                )), 
                $this->parsePageDynamicContent($content)
            );
        }
        return $content;
    }

    protected function parsePageDynamicContent($content)
    {
        $html = str_get_html($content);
        foreach ($widgets = $html->find('.dm_widget') as $widget)
        {
            $widgetId = intval(str_replace('dm_widget_', '', $widget->id));
            
            if ($widgetId) {
                $cache = $widget->find('.dm_widget_cacheable', 0);
                if ($cache) {
                    $cache->innertext = sprintf('{#page#%s#page#}{#widget#%s#widget#}', $this->getPage()->getId(), $widgetId);
                }
            }
        }        
        $body = $html->find('body', 0);
        
        foreach ($scripts = $body->find('script') as $script) {
            $script->outertext = '';
        }
        
        $code = $body->innertext();
        
        $code = str_replace('{#page#', '<?php echo $helper->renderWidgetInner(array(\'page_id\'=>', $code);
        $code = str_replace('#page#}{#widget#', ', \'widget_id\'=>', $code);
        $code = str_replace('#widget#}', ')); ?>', $code);
        return $code;
    }

    protected function getCacheDir()
    {
        $cacheDir = dmOs::join(sfConfig::get('sf_cache_dir'), 'front', sfConfig::get('sf_environment'), 'page');
        if (!file_exists($cacheDir)) {
            $this->getFileSystem()->mkdir($cacheDir);
        }
        return $cacheDir;
    }

    public function getCacheName($uri, $params, $isSecure, $credentials = '', $type = 'html')
    {
        return dmPageCacheLoader::getCacheName($uri, $params, $isSecure, $credentials, $type);
    }

    public function emptyCache()
    {
        $files = $this->getFileSystem()->find('file')->in($this->getCacheDir());
        return $this->getFileSystem()->unlink($files);
    }


    public function deleteCacheByKey($key)
    {
        $files = $this->getFileSystem()->find('file')->name($key.'.*')->in($this->getCacheDir());
        return $this->getFileSystem()->unlink($files);
    }
    
    public function deleteCache($uri)
    {
        $files = $this->getFileSystem()->find('file')->name(md5($uri).'.*')->in($this->getCacheDir());
        return $this->getFileSystem()->unlink($files);
    }

    public function deleteThisCache()
    {
        $this->deleteCache($_SERVER['PATH_INFO']);
    }

    /**
     * @return dmFilesystem
     */
    protected function getFileSystem()
    {
        return $this->filesystem;
    }

    /**
     * @return dmContext
     */
    protected function getContext()
    {
        return $this->context;
    }

    /**
     * @return sfEventDispatcher
     */
    protected function getDispacher()
    {
        return $this->dispacher;
    }

    /**
     * @return DmPage
     */
    protected function getPage()
    {
        return $this->getContext()->getPage();
    }
    
    protected function checkCachePool()
    {
        $files = $this->getFileSystem()->find('file')->in($this->getCacheDir());
        if (count($files) > sfConfig::get('dm_page_cache_max_pool_size')) {
            $this->getFileSystem()->unlink($files);
        }
    }
}

