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
     * path to this plugin directory
     *
     * @see PicoEditor::onConfigLoaded()
     */
    private $plugin_path;

    /**
     * {@code true} if requested page belongs to PicoEditor
     */
    private $is_admin;

    /**
     * PicoEditor password
     */
    private $password;

    /**
     * PicoEditor url
     */
    private $adminUrl = 'editor';

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
        // path to the plugin, used for rendering templates
        $this->plugin_path = dirname(__FILE__);
        // check configuration for password
        if (isset($config['PicoEditor.password']) && !empty($config['PicoEditor.password'])) {
            $this->password = $config['PicoEditor.password'];
        }
        if (isset($config['PicoEditor']['password']) && !empty($config['PicoEditor']['password'])) {
            $this->password = $config['PicoEditor']['password'];
        }
        // check configuration for custom admin url
        if (isset($config['PicoEditor.url']) && !empty($config['PicoEditor.url'])) {
            $this->adminUrl = $config['PicoEditor.url'];
        }
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
     */
    public function onRequestUrl(&$url)
    {
        // are we looking for admin?
        if (!empty($this->adminUrl) && strpos($url, $this->adminUrl) === 0) {
            $this->is_admin = true;

            // are we looking for admin/new?
            if ($url == $this->adminUrl . '/new') {
                $this->doNew();
            }
            // are we looking for admin/open?
            if ($url == $this->adminUrl . '/open') {
                $this->doOpen();
            }
            // are we looking for admin/save?
            if ($url == $this->adminUrl . '/save') {
                $this->doSave();
            }
            // are we looking for admin/delete?
            if ($url == $this->adminUrl . '/delete') {
                $this->doDelete();
            }
            // are we looking for admin/logout?
            if ($url == $this->adminUrl . '/logout') {
                $this->doLogout();
            }
        }
    }

    /**
     * Triggered before Pico renders the page
     *
     * @param string &$templateName file name of the template
     * @param array  &$twigVariables template variables
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @see  DummyPlugin::onPageRendered()
     * @uses $_POST['password']
     */
    public function onPageRendering(&$templateName, array &$twigVariables)
    {
        if ($this->is_admin) {
            // override 404 header
            header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');

            $loader = new Twig_Loader_Filesystem($this->plugin_path);
            $this->getPico()->getTwig()->setLoader($loader);

            // customizable endpoint used in editor's template
            // $twigVariables['plugin_url'] = $this->pico->getBaseUrl() . '/plugins/' . basename(__DIR__);
            $twigVariables['editor_url'] = $this->adminUrl;

            // check if no password exists
            if (!$this->password) {
                // set the error message
                $twigVariables['login_error'] = 'No password set!';
                // render the login view
                echo $this->getPico()->getTwig()->render('views/login.twig', $twigVariables);
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
                        echo $this->getPico()->getTwig()->render('views/login.twig', $twigVariables);
                        // don't continue to render template
                        exit;
                    }
                } else {
                    // user did not submit a password.
                    echo $this->getPico()->getTwig()->render('views/login.twig', $twigVariables);
                    // don't continue to render template
                    exit;
                }
            }

            // session exists, render the editor...
            echo $this->getPico()->getTwig()->render('views/editor.twig', $twigVariables);
            // don't continue to render template
            exit;
        }
    }

    /**
     * Exit from admin session
     */
    private function doLogout()
    {
        // destroy the current session
        session_destroy();
        // redirect to the login page...
        header('Location: ' . $this->pico->getPageUrl($this->adminUrl));
        // don't continue to render template
        exit;
    }

    /**
     * Create a new page
     *
     * @uses $_POST['title']
     */
    private function doNew()
    {
        $this->checkIfLoggedIn();

        // sanitize post title
        $title = isset($_POST['title']) ? strip_tags($_POST['title']) : null;

        // get base name
        $pageId = $this->slugify($title);

        if (empty($title) || empty($pageId)) {
            return $this->responseJson(['error' => 'Invalid page name.'], 400);
        }

        $pagePath = $this->getPagePath($pageId);
        if (file_exists($pagePath)) {
            return $this->responseJson(['error' => 'A page already exists with this name.'], 400);
        }

        $content = "---\nTitle: " . $title . "\nDescription: \nAuthor: \n" .
            "Date: " . date("Y/m/d") . "\nTemplate:\n---\n\n\n";

        if (!file_put_contents($pagePath, $content)) {
            return $this->responseJson(['error' => 'Can not create a file for new page.'], 400);
        }

        return $this->responseJson([
            'id' => $pageId,
            'title' => $title,
            'content' => $content,
            'url' => $this->pico->getPageUrl($pageId),
        ], 201);
    }

    /**
     * Load a page to editor
     *
     * @uses $_POST['pageId']
     */
    private function doOpen()
    {
        $this->checkIfLoggedIn();
        $pageId = isset($_POST['pageId']) ? $_POST['pageId'] : null;

        $pagePath = $this->getPagePath($pageId, true);

        $content = file_get_contents($pagePath);
        $this->responseText($content);
    }

    /**
     * Save changes to a file.
     *
     * @uses $_POST['pageId']
     * @uses $_POST['content']
     */
    private function doSave()
    {
        $this->checkIfLoggedIn();
        $pageId = isset($_POST['pageId']) ? $_POST['pageId'] : null;
        $content = isset($_POST['content']) ? $_POST['content'] : null;

        $pagePath = $this->getPagePath($pageId, true);

        if (empty($content)) {
            return $this->responseJson(['error' => 'Empty content given.'], 400);
        }

        if (!file_put_contents($pagePath, $content)) {
            return $this->responseJson(['error' => 'Unable to write changes to file.'], 400);
        }

        return $this->responseJson([]);
    }

    /**
     * Delete a page.
     *
     * @uses $_POST['pageId']
     */
    private function doDelete()
    {
        $this->checkIfLoggedIn();
        $pageId = isset($_POST['pageId']) ? $_POST['pageId'] : null;

        $pagePath = $this->getPagePath($pageId, true);

        unlink($pagePath);
        return $this->responseJson([]);
    }

    /**
     * Check the login status before manipulating files...
     *
     * @uses $_SESSION['pico_logged_in']
     */
    private function checkIfLoggedIn()
    {
        if (!isset($_SESSION['pico_logged_in']) || !$_SESSION['pico_logged_in']) {
            return $this->responseJson(['error' => 'Unauthorized'], 401);
        }
    }

    private function getPagePath($pageId, $ensureExists = false)
    {
        if (empty($pageId)) {
            return $this->responseJson(['error' => 'Empty page ID given.'], 400);
        }

        $pagePath = $this->pico->resolveFilePath($pageId);

        if ($ensureExists) {
            if (!file_exists($pagePath)) {
                return $this->responseJson(['error' => 'Missing file ' . $pagePath . ' for page: ' . $pageId], 400);
            }
        }

        return $pagePath;
    }

    /**
     * Converts a title of a page to a slug (filename)
     *
     * @param string $text
     * @return string a url-friendly post slug
     */
    private function slugify($text)
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\pL\d/]+~u', '-', $text);
        // trim
        $text = trim($text, '-');
        // convert to ascii
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        // lowercase
        $text = strtolower($text);
        // remove non[ascii text] characters
        $text = preg_replace('~[^-\w/]+~', '', $text);
        // in case of empty text
        if (empty($text)) {
            return 'n-a';
        }
        // return result
        return $text;
    }

    /**
     * Return $text as plain text and stop script
     *
     * @param string $text
     * @param int $code
     */
    private function responseText($text, $code = 200)
    {
        header_remove();
        http_response_code($code);
        header('Content-Type: text/html; charset=UTF-8');
        echo $text;
        exit;
    }

    /**
     * Return $data as serialized json and stop script
     *
     * @param array $data
     * @param int $code
     */
    private function responseJson($data, $code = 200)
    {
        header_remove();
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
