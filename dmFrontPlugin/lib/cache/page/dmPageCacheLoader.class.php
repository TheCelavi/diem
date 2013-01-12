<?php

class dmPageCacheLoader 
{
    static $cacheDir;
    static $environment;

    public static function load($cacheDir, $environment)
    {
        self::$cacheDir = $cacheDir;
        self::$environment = $environment;
        session_name('symfony');
        session_start();
        if (
            $_SESSION['symfony/user/sfUser/superAdmin'] || 
            self::can(self::getUserPageEditPermissions())            
        ) {
            return; // If this user can edit page, do not use cache in session
        }        
        $params = implode('', array_merge($_GET, $_POST));
        $uri = $_SERVER['PATH_INFO'];
        self::loadStaticPage($uri, $params);
        self::loadDynamicPage($uri, $params);
    }
    
    /**
     * Checks if user has permission to se this page
     * @param string $perms of cached page
     * @return boolean 
     */
    public static function can($perms)
    {
        $userPerms = $_SESSION['symfony/user/sfUser/credentials'];
        if(is_string($userPerms)) {
            $userPerms = array_map('trim', explode(',', $userPerms));
        }
    
        if(is_array($userPerms)){
            return (bool) count(array_intersect($perms, $userPerms));
        }
        
        return false;
    }

    /**
     * Generates cache name using MD5 algorithm
     * @param string $uri URI for cache
     * @param string $params GET and POST params
     * @param type $isSecure Do we fetch a secure page
     * @param type $credentials Credentials of page
     * @param type $type Type of cache HTML or PHP
     * @return string Cache name
     */
    public static function getCacheName($uri, $params, $isSecure, $credentials = '', $type = 'html')
    {
        if ($isSecure) {
            return sprintf('%s.%s._secure_page_%s.%s', md5($uri), md5($params), str_replace(',', '.', $credentials), $type);
        } else {
            return sprintf('%s.%s.%s', md5($uri), md5($params), $type);
        }
    }
    
    /**
     * Gets the cache dir
     * @return string
     */
    public static function getCacheDir()
    {
        return self::$cacheDir . 'front/' . self::$environment . '/page/';
    }
    
    /**
     * Defined permissions which enables to the user to administer pages
     * @return array
     */
    public static function getUserPageEditPermissions()
    {
        return array(
            'media_bar_front', 
            'page_add',
            'page_bar_front',
            'page_delete',
            'page_edit',
            'tool_bar_front',
            'widget_add',
            'widget_delete',
            'widget_edit',
            'widget_edit_fast',
            'widget_edit_fast_content_image',
            'widget_edit_fast_content_link',
            'widget_edit_fast_content_text',
            'widget_edit_fast_content_title',
            'widget_edit_fast_navigation_menu',
            'widget_edit_fast_record',
            'zone_add',
            'zone_delete',
            'zone_edit',
            'behavior_add',	
            'behavior_delete', 
            'behavior_edit',
            'behavior_sort',
            'clear_cache',
            'code_editor',
            'manual_metas'
        );
    }
    
    protected static function loadStaticPage($uri, $params)
    {
        $content = null;
        // Check if there is no secure cached page
        $fileName = self::getCacheName($uri, $params, false, '', 'html');
        if (file_exists(self::getCacheDir() . $fileName)) {
            $content = @file_get_contents(self::getCacheDir() . $fileName);
            if ($content) { // We found content, echo it!
                echo $content;
                exit; // Since we found it, no other execution is required
            }
        }
        
        
        // No cache - check if there is a secure version
        if ($_SESSION['symfony/user/sfUser/authenticated']) { // User is authenticated
            $pattern = self::getCacheName($uri, $params, false, '', '');
            $search = self::getCacheDir() . $pattern . '_secure_page_' . "*" . '.html';
            $files = glob($search);
            if (count($files)) {
                $fileName = $files[0];
                $requiredPerms = explode('.', str_replace(self::getCacheDir() . $pattern . '_secure_page_', '', $fileName));
                array_pop($requiredPerms);
                if (self::can($requiredPerms)) {
                    $content = file_get_contents($fileName);
                    if ($content) {
                        echo $content;
                        exit;
                    }
                }
            }
        }
    }
    
    protected static function loadDynamicPage($uri, $params)
    {
        $fileName = self::getCacheDir() . self::getCacheName($uri, $params, false, '', 'php');              
        if (file_exists($fileName)) {
            $content = @file_get_contents($fileName);
            if ($content) { // We found content, echo it!
                $_SESSION['symfony/page_cache/template'] = $fileName;
            }
        }
        
        
        // No cache - check if there is a secure version
        if ($_SESSION['symfony/user/sfUser/authenticated']) { // User is authenticated
            $pattern = self::getCacheName($uri, $params, false, '', 'php');
            $search = self::getCacheDir() . $pattern . '_secure_page_' . "*" . '.php';
            $files = glob($search);
            if (count($files)) {
                $fileName = $files[0];
                $requiredPerms = explode('.', str_replace(self::getCacheDir() . $pattern . '_secure_page_', '', $fileName));
                array_pop($requiredPerms);
                if (self::can($requiredPerms)) {
                    $content = file_get_contents($fileName);
                    if ($content) {
                        $_SESSION['symfony/page_cache/template'] = $fileName;
                    }
                }
            }
        }
    }
}