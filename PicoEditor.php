<?php

/**
 * Provides an online Markdown editor and file manager for Pico.
 *
 * @author Oleh Astappiev, Tyler Heshka, Gilbert Pellegrom and others
 * @license http://opensource.org/licenses/MIT The MIT License
 * @version 2.0-dev
 */
class PicoEditor extends AbstractPicoPlugin
{
    /**
     * API version used by this plugin
     *
     * @var int
     */
    const API_VERSION = 3;

    /**
     * login status
     */
    private $is_admin;

    /**
     * logging out
     */
    private $is_logout;

    /**
     * path to this plugin directory
     *
     * @see PicoEditor::onConfigLoaded()
     */
    private $plugin_path;

    /**
     * content directory
     *
     * @see Pico::getConfig()
     */
    private $contentDir;

    /**
     * content file extension
     *
     * @see Pico::getConfig()
     */
    private $contentExt;

    /**
     * Pico Editor password
     */
    private $password;

    /**
     * url rewriting enabled?
     */
    private $urlRewriting;

    /**
     * custom admin url
     */
    private $adminUrl;

    /**
     * Triggered after Pico has read its configuration
     *
     * @param array &$config array of config variables
     * @see Pico::getBaseUrl()
     * @see Pico::isUrlRewritingEnabled()
     *
     * @see Pico::getConfig()
     */
    public function onConfigLoaded(array &$config)
    {
        // not seeking admin page
        $this->is_admin = false;
        // not logging out
        $this->is_logout = false;
        // path to the plugin, used for rendering templates
        $this->plugin_path = dirname(__FILE__);
        // Pico's content dir
        $this->contentDir = $config['content_dir'];
        // Pico's content extention
        $this->contentExt = $config['content_ext'];
        // check config for url rewriting
        if (isset($config['rewrite_url']) && !empty($config['rewrite_url']) &&
            $config['rewrite_url'] == true) {
            $this->urlRewriting = '/';
        } else {
            $this->urlRewriting = '/?';
        }
        // check configuration for password
        if (isset($config['PicoEditor']['password']) && !empty($config['PicoEditor']['password'])) {
            $this->password = $config['PicoEditor']['password'];
        }
        // check configuration for custom admin url
        if (isset($config['PicoEditor']['url']) && !empty($config['PicoEditor']['url'])) {
            $this->adminUrl = $config['PicoEditor']['url'];
        }
        // check for session
        if (!isset($_SESSION)) {
            session_start();
        }
    }

    /**
     * Triggered after Pico has evaluated the request URL
     *
     * @param string &$url part of the URL describing the requested contents
     * @see Pico::getRequestUrl()
     *
     */
    public function onRequestUrl(&$url)
    {
        // are we looking for admin?
        if ($url == $this->adminUrl) {
            $this->is_admin = true;
        }
        // are we looking for admin/new?
        if ($url == 'admin/new') {
            $this->doNew();
        }
        // are we looking for admin/open?
        if ($url == 'admin/open') {
            $this->doOpen();
        }
        // are we looking for admin/save?
        if ($url == 'admin/save') {
            $this->doSave();
        }
        // are we looking for admin/delete?
        if ($url == 'admin/delete') {
            $this->doDelete();
        }
        // are we looking for admin/logout?
        if ($url == 'admin/logout') {
            $this->is_logout = true;
        }
    }

    /**
     * Triggered before Pico renders the page
     *
     * @param string &$templateName file name of the template
     * @param array  &$twigVariables template variables
     * @see DummyPlugin::onPageRendered()
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function onPageRendering(&$templateName, array &$twigVariables)
    {
        // LOGGING OUT
        if ($this->is_logout) {
            // destory the current session
            session_destroy();
            // redirect to the login page...
            header('Location: ' . $twigVariables['base_url'] . $this->urlRewriting . $this->adminUrl);
            // don't continue to render template
            exit;
        }

        //LOGGING IN
        if ($this->is_admin) {
            // override 404 header
            header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');

            // customizable endpoint used in editor's template
            $twigVariables['editor_url'] = $this->adminUrl;

            // check if no password exists
            if (!$this->password) {
                // set the error message
                $twigVariables['login_error'] = 'No password set!';
                // render the login view
                echo $this->getPico()->getTwig()->render('views/login.twig', $twigVariables); // Render login.twig
                // don't continue to render template
                exit;
            }
            // if no current session exists,
            if (!isset($_SESSION['pico_logged_in']) || !$_SESSION['pico_logged_in']) {
                // check that user is POSTing a password
                if (isset($_POST['password'])) {
                    // does the password match the hashed password?
                    if (hash('sha512', $_POST['password']) == $this->password) {
                        // login success
                        $_SESSION['pico_logged_in'] = true;
                    } else {
                        // login failure
                        $twigVariables['login_error'] = 'Invalid password.';
                        // render the login view
                        echo $this->getPico()->getTwig()->render('views/login.twig', $twigVariables); // Render login.twig
                        // don't continue to render template
                        exit;
                    }
                } else {
                    // user did not submit a password.
                    echo $this->getPico()->getTwig()->render('views/login.twig', $twigVariables); // Render login.twig
                    // don't continue to render template
                    exit;
                }
            }
            // session exists, render the editor...
            echo $this->getPico()->getTwig()->render('views/editor.twig', $twigVariables); // Render editor.twig
            // don't continue to render template
            exit;
        }
    }

    /**
     * Triggered when Pico registers the twig template engine
     *
     * @param Twig_Environment &$twig Twig instance
     * @see Pico::getTwig()
     *
     */
    public function onTwigRegistered(Twig_Environment &$twig)
    {
        // have twig look for templates in our plugin directory
        $loader = new Twig_Loader_Filesystem($this->plugin_path);
        $twig->setLoader($loader);
    }

    /**
     * Check the login status before manipulating files...
     */
    private function doCheckLogin()
    {
        if (!isset($_SESSION['pico_logged_in']) || !$_SESSION['pico_logged_in']) {
            die(json_encode(array('error' => 'Error: Unathorized')));
        }
    }

    /**
     * Create new file.
     *
     * @param void
     * @return json/array the contents of the new file
     */
    private function doNew()
    {
        /**
         * TODO: Create new files in sub directories
         */

        // check for logged in
        $this->doCheckLogin();
        // sanitize post title
        $title = isset($_POST['title']) && $_POST['title'] ? strip_tags($_POST['title']) : '';
        // get base name
        $file = $this->slugify(basename($title));
        // die if error...
        if (!$file) {
            die(json_encode(array('error' => 'Error: Invalid file name')));
        }
        // clear errors
        $error = '';
        // set file extension
        $file .= $this->contentExt;
        // the file content
        $content = '---
Title: ' . $title . '
Description:
Author:
Date: ' . date('Y/m/d') . '
Robots: noindex,nofollow
Template:
---';
        // check for duplicates
        if (file_exists($this->contentDir . $file)) {
            $error = 'Error: A post already exists with this title';
        } else {
            // save the file
            file_put_contents($this->contentDir . $file, $content);
        }
        // return results
        die(json_encode(array(
            'title' => $title,
            'content' => $content,
            'file' => basename(str_replace($this->contentExt, '', $file)),
            'error' => $error,
        )));
    }

    /**
     * Open a file.
     *
     * @param string $POST ['file'] the file to open
     */
    private function doOpen()
    {
        /**
         * TODO: Error when opening files that reside in a sub/folder; what is
         * causing this, and how can it be fixed?
         */

        $this->doCheckLogin();
        // check file url not blank
        $file_url = isset($_POST['file']) && $_POST['file'] ? $_POST['file'] : '';
        // get the base filename
        $file = urldecode(basename($file_url));
        // no file requested
        if (!$file) {
            die('Open Error: Invalid file ' . $file . ' at the URL: ' . $file_url);
        }
        // append the content extension
        $file .= $this->contentExt;
        // does the file exist, or die
        if (file_exists($this->contentDir . $file)) {
            // open the file
            die(file_get_contents($this->contentDir . $file));
        } else {
            die('Open Error: Invalid file ' . $file . ' at the URL: ' . $file_url);
        }
    }

    /**
     * Save changes to a file.
     *
     * @param string $POST ['file'] the file to save
     * @param string $POST ['contents'] the contents to save
     */
    private function doSave()
    {
        /**
         * TODO: save files that reside in sub directories
         */

        $this->doCheckLogin();
        // check file url not blank
        $file_url = isset($_POST['file']) && $_POST['file'] ? $_POST['file'] : '';
        // get the base filename
        $file = urldecode(basename($file_url));
        // no file requested
        if (!$file) {
            die('Save Error: Invalid file');
        }
        // no content sent
        $content = isset($_POST['content']) && $_POST['content'] ? $_POST['content'] : '';
        if (!$content) {
            die('Save Error: Invalid content');
        }
        // append the content extension
        $file .= $this->contentExt;
        // save the file
        file_put_contents($this->contentDir . $file, $content);
        // show the saved contents
        die($content);
    }

    /**
     * Delete a file.
     *
     * @param string $POST ['file'] the file to delete
     */
    private function doDelete()
    {
        /**
         * TODO: delete files that reside in sub directories
         */

        $this->doCheckLogin();
        // check file url not blank
        $file_url = isset($_POST['file']) && $_POST['file'] ? $_POST['file'] : '';
        // get the base filename
        $file = urldecode(basename($file_url));
        // no file was requested
        if (!$file) {
            die('Delete Error: Invalid file');
        }
        // append the content extension
        $file .= $this->contentExt;
        // if file exists,
        if (file_exists($this->contentDir . $file)) {
            // delete the file
            die(unlink($this->contentDir . $file));
        }
    }

    /**
     * Create a url-friendly post slug
     *
     * @param string &$output contents which will be sent to the user
     */
    private function slugify($text)
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
        // trim
        $text = trim($text, '-');
        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        // lowercase
        $text = strtolower($text);
        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);
        // in case of empty text
        if (empty($text)) {
            return 'n-a';
        }
        // return result
        return $text;
    }
}
