<?php
/* Prohibit direct script loading */
defined('ABSPATH') || die('No direct script access allowed!');

/**
 * Class WpmfAddonDropbox
 * This class that holds most of the admin functionality for Dropbox
 */
class WpmfAddonDropbox
{
    /**
     * Params
     *
     * @var object
     */
    protected $params;

    /**
     * App name
     *
     * @var string
     */
    protected $appName = 'WpmfAddon/1.0';

    /**
     * Last Error
     *
     * @var string
     */
    protected $lastError;

    /**
     * WpmfAddonDropbox constructor.
     */
    public function __construct()
    {
        set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());
        require_once 'Dropbox/autoload.php';
        $this->loadParams();
    }

    /**
     * Get last error
     *
     * @return mixed
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Get dropbox config by name
     *
     * @param string $name Name of option
     *
     * @return array|null
     */
    public function getDataConfigByDropbox($name)
    {
        return WpmfAddonHelper::getDataConfigByDropbox($name);
    }

    /**
     * Get dropbox config
     *
     * @return mixed
     */
    public function getAllDropboxConfigs()
    {
        return WpmfAddonHelper::getAllDropboxConfigs();
    }

    /**
     * Save dropbox config
     *
     * @param array $data Datas value
     *
     * @return boolean
     */
    public function saveDropboxConfigs($data)
    {
        return WpmfAddonHelper::saveDropboxConfigs($data);
    }

    /**
     * Load parameters
     *
     * @return void
     */
    protected function loadParams()
    {
        $params = $this->getDataConfigByDropbox('dropbox');

        $this->params = new stdClass();

        $this->params->dropboxKey    = isset($params['dropboxKey']) ? $params['dropboxKey'] : '';
        $this->params->dropboxSecret = isset($params['dropboxSecret']) ? $params['dropboxSecret'] : '';
        $this->params->dropboxToken  = isset($params['dropboxToken']) ? $params['dropboxToken'] : '';
    }

    /**
     * Save parameters
     *
     * @return void
     */
    protected function saveParams()
    {
        $params                  = $this->getAllDropboxConfigs();
        $params['dropboxKey']    = $this->params->dropboxKey;
        $params['dropboxSecret'] = $this->params->dropboxSecret;
        $params['dropboxToken']  = $this->params->dropboxToken;
        $this->saveDropboxConfigs($params);
    }

    /**
     * Get web auth
     *
     * @return \WPMFDropbox\WebAuthNoRedirect
     */
    public function getWebAuth()
    {
        $dropboxKey    = '';
        $dropboxSecret = 'dropboxSecret';
        if (!empty($this->params->dropboxKey)) {
            $dropboxKey = $this->params->dropboxKey;
        }
        if (!empty($this->params->dropboxSecret)) {
            $dropboxSecret = $this->params->dropboxSecret;
        }

        $appInfo = new WPMFDropbox\AppInfo($dropboxKey, $dropboxSecret);
        $webAuth = new WPMFDropbox\WebAuthNoRedirect($appInfo, $this->appName);

        return $webAuth;
    }

    /**
     * Get author Url allow user
     *
     * @return string
     */
    public function getAuthorizeDropboxUrl()
    {
        $authorizeUrl = $this->getWebAuth()->start();

        return $authorizeUrl;
    }

    /**
     * Convert the authorization code into an access token
     *
     * @param string $authCode Authorization code
     *
     * @return array
     */
    public function convertAuthorizationCode($authCode)
    {
        $list = array();
        list($accessToken, $dropboxUserId) = $this->getWebAuth()->finish($authCode);
        $list = array(
            'accessToken'   => $accessToken,
            'dropboxUserId' => $dropboxUserId
        );
        return $list;
    }

    /**
     * Check Author
     *
     * @return boolean
     */
    public function checkAuth()
    {
        $dropboxToken = $this->params->dropboxToken;
        if (!empty($dropboxToken)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Logout dropbox app
     *
     * @return void
     */
    public function logout()
    {
        $params                  = $this->getAllDropboxConfigs();
        $params['dropboxKey']    = $this->params->dropboxKey;
        $params['dropboxSecret'] = $this->params->dropboxSecret;
        $params['dropboxAuthor'] = '';
        $params['dropboxToken']  = '';
        $this->saveDropboxConfigs($params);
        $this->redirect(admin_url('options-general.php?page=option-folder&tab=wpmf-dropbox'));
    }

    /**
     * Get dropbox client
     *
     * @return \WPMFDropbox\Client|boolean
     */
    public function getAccount()
    {
        try {
            $wpmfAddon_dropbox_config = get_option('_wpmfAddon_dropbox_config');
            $dropboxToken             = $wpmfAddon_dropbox_config['dropboxToken'];
            $dbxClient                = new WPMFDropbox\Client($dropboxToken, $this->appName);
            return $dbxClient;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Create folder
     *
     * @param string $name Folder name
     * @param string $path Folder parent path
     *
     * @return array|null
     */
    public function doCreateFolder($name, $path)
    {
        $dropbox = $this->getAccount();
        try {
            $parent   = $path . '/' . $name;
            $result = $dropbox->createFolder($parent);
        } catch (Exception $e) {
            $parent   = $path . '/' . $name . '-' . time();
            $result = $dropbox->createFolder($parent);
        }
        return $result;
    }

    /**
     * Sync with media library
     *
     * @param string $folderID Folder ID
     * @param string $parent   Parent
     *
     * @return void
     */
    public function doSyncFoldersLibrary($folderID, $parent)
    {
        update_option('wpmf_cloud_name_syncing', 'dropbox');
        // Create folders in media library
        $list_folders = get_option('wpmf_dropbox_folders');
        $allDriveFiles = get_option('wpmf_dropbox_allfiles');
        $list_files = get_option('wpmf_dropbox_attachments');
        if (empty($list_files)) {
            $list_files = array();
        }

        try {
            $dropbox = $this->getAccount();
            $fs = $dropbox->getMetadataWithChildren($folderID);
            $childs = $fs['entries'];

            // Create files in media library

            foreach ($childs as $child) {
                if ($child['.tag'] === 'file') {
                    include_once 'includes/mime-types.php';
                    $fileExtension = pathinfo($child['name'], PATHINFO_EXTENSION);
                    $mimeType   = getMimeType($fileExtension);

                    $allDriveFiles[$child['id']] = array('id' => $child['id'], 'type' => 'file');
                    $list_files[$child['id']] = array(
                        'id' => $child['id'],
                        'name' => $child['name'],
                        'path_lower' => $child['path_lower'],
                        'rev' => $child['rev'],
                        'parent' => $parent,
                        'file' => array('mimeType' => $mimeType),
                        'image' => array(),
                        'size' => $child['size']
                    );

                    if (strpos($mimeType, 'image') !== false) {
                        $dimensions = array('width' => 0, 'height' => 0);
                        if (isset($child['media_info'])) {
                            if (empty($child['media_info']['metadata']['dimensions'])) {
                                $dimensions = array(
                                    'width' => $child['media_info']['metadata']['dimensions']['width'],
                                    'height' => $child['media_info']['metadata']['dimensions']['height']
                                );
                            }
                        }

                        $list_files[$child['id']]['image'] = $dimensions;
                    }
                }
            }

            foreach ($childs as $child) {
                if ($child['.tag'] === 'folder') {
                    $allDriveFiles[$child['id']] = array('id' => $child['id'], 'type' => 'folder');
                    $info = pathinfo($child['name']);
                    $args = array(
                        'hide_empty' => false,
                        'meta_query' => array(
                            array(
                                'key'       => 'wpmf_drive_id',
                                'value'     => $child['id'],
                                'compare'   => '='
                            )
                        ),
                        'taxonomy'  => WPMF_TAXO
                    );
                    $folders = get_terms($args);
                    if (empty($folders)) {
                        $inserted = wp_insert_term($child['name'], WPMF_TAXO, array('parent' => (int) $parent));
                        if (!is_wp_error($inserted)) {
                            $list_folders[$child['id']] = array('id' => $child['id'], 'parent' => $inserted['term_id']);
                            add_term_meta($inserted['term_id'], 'wpmf_drive_id', $child['id']);
                            add_term_meta($inserted['term_id'], 'wpmf_drive_type', 'dropbox');
                        }
                    } else {
                        foreach ($folders as $folder) {
                            $list_folders[$child['id']] = array('id' => $child['id'], 'parent' => $folder->term_id);
                            if (isset($parent)) {
                                if ((int) $folder->parent !== (int) $parent) {
                                    wp_update_term((int) $folder->term_id, WPMF_TAXO, array('parent' => (int) $parent));
                                }
                            }

                            if ($info['filename'] !== $folder->name) {
                                wp_update_term((int) $folder->term_id, WPMF_TAXO, array('name' => $info['filename']));
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            if (isset($list_folders[$folderID])) {
                unset($list_folders[$folderID]);
            }
        }

        if (isset($list_folders[$folderID])) {
            unset($list_folders[$folderID]);
        }

        update_option('wpmf_dropbox_attachments', $list_files);
        update_option('wpmf_dropbox_folders', $list_folders);
        update_option('wpmf_dropbox_allfiles', $allDriveFiles);
        if (!empty($list_folders)) {
            sleep(1);
            wp_send_json(array('status' => true, 'continue' => true));
        } else {
            wp_send_json(array('status' => true, 'continue' => false));
        }
    }

    /**
     * Sync folders with media library
     *
     * @return void
     */
    public function syncFoldersLibrary()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        $dropbox_config = get_option('_wpmfAddon_dropbox_config');
        if (empty($dropbox_config['dropboxToken'])) {
            wp_send_json(array('status' => false));
        }

        if (!get_option('wpmf_dropbox_folders', false)) {
            add_option('wpmf_dropbox_folders', array(), '', 'yes');
        }

        if (!get_option('wpmf_dropbox_attachments', false)) {
            add_option('wpmf_dropbox_attachments', array(), '', 'yes');
        }

        if (!get_option('wpmf_dropbox_allfiles', false)) {
            add_option('wpmf_dropbox_allfiles', array(), '', 'yes');
        }

        try {
            set_time_limit(0);
            // continue sync with next cloud
            if (isset($_POST['type']) && $_POST['type'] === 'auto') {
                // only run auto sync in one tab
                if (!empty($_POST['sync_token'])) {
                    if (!get_option('wpmf_cloud_sync_time', false) && !get_option('wpmf_cloud_sync_token', false)) {
                        add_option('wpmf_cloud_sync_time', time());
                        add_option('wpmf_cloud_sync_token', $_POST['sync_token']);
                    } else {
                        if ($_POST['sync_token'] !== get_option('wpmf_cloud_sync_token')) {
                            // stop run
                            if (time() - (int) get_option('wpmf_cloud_sync_time') < 60) {
                                wp_send_json(array('status' => false, 'continue' => false));
                            } else {
                                update_option('wpmf_cloud_sync_token', $_POST['sync_token']);
                                update_option('wpmf_cloud_sync_time', time());
                            }
                        }
                    }
                }

                $options = array(
                    'wpmf_google_attachments',
                    'wpmf_google_folders',
                    'wpmf_odv_attachments',
                    'wpmf_odv_folders',
                    'wpmf_odv_business_attachments',
                    'wpmf_odv_business_folders'
                );
                $continue = wpmfCheckSyncNextCloud($options);
                if ($continue) {
                    wp_send_json(array('status' => false));
                }
            }

            $list_folders = get_option('wpmf_dropbox_folders');
            if (empty($list_folders)) {
                $list_folders = array();
                $folderID = '';
                $inserted = wp_insert_term('Dropbox', WPMF_TAXO, array('parent' => 0));
                if (is_wp_error($inserted)) {
                    $root_id = $inserted->error_data['term_exists'];
                } else {
                    $root_id = $inserted['term_id'];
                }

                $cloud_folder_id = get_term_meta($root_id, 'wpmf_drive_root_id', true);
                if (!empty($cloud_folder_id)) {
                    update_term_meta($root_id, 'wpmf_drive_root_id', 'root');
                } else {
                    add_term_meta($root_id, 'wpmf_drive_root_id', 'root');
                }

                $list_folders[$folderID] = array('id' => $folderID, 'parent' => $root_id);
                update_option('wpmf_dropbox_folders', $list_folders);
                update_option('wpmf_dropbox_folder_id', $root_id);
            }

            if (!empty($list_folders)) {
                $first_element = array_values(array_slice($list_folders, 0, 1));
                $drive_id = $first_element[0]['id'];
                $parent = $first_element[0]['parent'];
                $this->doSyncFoldersLibrary($drive_id, $parent);
            }
        } catch (Exception $ex) {
            wp_send_json(array('status' => false, 'msg' => $ex->getMessage()));
        }
    }

    /**
     * Sync files with media library
     *
     * @return void
     */
    public function syncFilesLibrary()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        $upload_path = wp_upload_dir();
        $dropbox_config = get_option('_wpmfAddon_dropbox_config');
        $drive_files = get_option('wpmf_dropbox_attachments');
        if (empty($drive_files)) {
            $dropbox_config['first_connected'] = 0;
            update_option('_wpmfAddon_dropbox_config', $dropbox_config);
            wp_send_json(array('status' => true, 'continue' => false));
        }

        $dropbox      = $this->getAccount();
        if (isset($dropbox_config['link_type']) && $dropbox_config['link_type'] === 'public') {
            $limit = 1;
        } else {
            $limit = 5;
        }

        $i = 0;
        include_once 'includes/mime-types.php';
        // Create files in media library
        foreach ($drive_files as $cloudFileId => $child) {
            if ($i >= $limit) {
                sleep(1);
                wp_send_json(array('status' => true, 'continue' => true));
            }

            $i++;
            $parent = $child['parent'];
            if (!empty($child['file'])) {
                $info = pathinfo($child['name']);
                $args = array(
                    'post_type' => 'attachment',
                    'posts_per_page' => -1,
                    'post_status' => 'any',
                    'orderby' => 'ID',
                    'order' => 'ASC',
                    'meta_query' => array(
                        array(
                            'key'       => 'wpmf_drive_id',
                            'value'     => $child['id'],
                            'compare'   => '='
                        )
                    )
                );

                $files = get_posts($args);
                if (empty($files)) {
                    $link = $this->getLink($child['id'], $dropbox_config, $dropbox);
                    if (!$link) {
                        if (isset($drive_files[$cloudFileId])) {
                            unset($drive_files[$cloudFileId]);
                            update_option('wpmf_dropbox_attachments', $drive_files);
                        }
                        continue;
                    }

                    $width = isset($child['image']['width']) ? $child['image']['width'] : 0;
                    $height = isset($child['image']['height']) ? $child['image']['height'] : 0;
                    $fileExtension = pathinfo($child['name'], PATHINFO_EXTENSION);
                    $mimeType   = getMimeType(strtolower($fileExtension));

                    if (strpos($mimeType, 'image') !== false) {
                        $content = $dropbox->get_filecontent($child['path_lower']);
                        $filePath = $upload_path['path'] . '/' . time() . '.' . $info['extension'];
                        file_put_contents($filePath, $content);
                        $exif_data = wp_read_image_metadata($filePath);
                        if (!empty($exif_data) && isset($exif_data['orientation'])) {
                            $scaled = WpmfAddonHelper::fixImageOrientation(array('file' => $filePath));
                            $filePath = $scaled['file'];
                            $f         = fopen($filePath, 'rb');
                            $cloud_id = wpmfGetCloudFolderID($parent);
                            $id_folder = ($cloud_id === 'root') ? '' : $cloud_id;
                            $path      = $id_folder . '/' . $info['basename'];
                            $size = filesize($filePath);
                            $result = $dropbox->updateFile($path, $child['rev'], WPMFDropbox\WriteMode::update($child['rev']), $f, $size);
                            if (file_exists($filePath)) {
                                unlink($filePath);
                            }
                        }
                    }

                    $this->insertAttachment($info, $child, $parent, $upload_path, $link, $mimeType, $width, $height);
                } else {
                    // update attachment
                    remove_action('delete_attachment', array($this, 'deleteAttachment'));
                    if (count($files) > 1) {
                        foreach ($files as $index => $file) {
                            if ((int) $index === 0) {
                                continue;
                            }
                            wp_delete_attachment($file->ID);
                            $this->updateAttachment($info, $file->ID, $parent);
                        }
                    } else {
                        $this->updateAttachment($info, $files[0]->ID, $parent);
                    }
                }
            }

            if (isset($drive_files[$cloudFileId])) {
                unset($drive_files[$cloudFileId]);
                update_option('wpmf_dropbox_attachments', $drive_files);
            }
        }

        sleep(1);
        wp_send_json(array('status' => true, 'continue' => true));
    }

    /**
     * Remove the files/folders when sync
     *
     * @return void
     */
    public function syncRemoveItems()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        set_time_limit(0);
        $allDriveFiles = get_option('wpmf_dropbox_allfiles');
        remove_action('delete_attachment', array($this, 'deleteAttachment'));
        remove_action('wpmf_before_delete_folder', array($this, 'deleteFolderLibrary'));
        if (!empty($allDriveFiles)) {
            $this->removeFoldersSync($allDriveFiles);
            $paged = isset($_POST['paged']) ? (int) $_POST['paged'] : 1;
            $continue = $this->removeFilesSync($allDriveFiles, $paged, true);
            if ($continue) {
                sleep(2);
                wp_send_json(array(
                    'status'   => true,
                    'continue' => true
                ));
            }
        }

        update_option('wpmf_dropbox_allfiles', array());
        // Send back all needed informations in json format
        $main_class = $GLOBALS['wp_media_folder'];
        $terms = $main_class->getAttachmentTerms();

        update_option('wpmf_cloud_name_syncing', '');
        wp_send_json(array(
            'status'           => true,
            'continue' => false,
            'categories'       => $terms['attachment_terms'],
            'categories_order' => $terms['attachment_terms_order']
        ));
    }

    /**
     * Remove the files when sync
     *
     * @param array   $driveFiles Files list
     * @param integer $paged      Paged
     * @param boolean $paging     Is paging
     *
     * @return boolean
     */
    public function removeFilesSync($driveFiles, $paged = 1, $paging = false)
    {
        if ($paging) {
            $limit = 100;
            $offset = ($paged - 1) * $limit;
            $args = array(
                'post_type' => 'attachment',
                'posts_per_page' => $limit,
                'offset' => $offset,
                'post_status' => 'any',
                'meta_query' => array(
                    array(
                        'key'       => 'wpmf_drive_type',
                        'value'     => 'dropbox',
                        'compare'   => '='
                    )
                )
            );
            $files = get_posts($args);
            if (empty($files)) {
                return false;
            }
            foreach ($files as $file) {
                $drive_id = get_post_meta($file->ID, 'wpmf_drive_id', true);
                if (empty($driveFiles[$drive_id])) {
                    wp_delete_attachment($file->ID);
                }
            }

            return true;
        } else {
            $args = array(
                'post_type' => 'attachment',
                'posts_per_page' => -1,
                'post_status' => 'any',
                'meta_query' => array(
                    array(
                        'key'       => 'wpmf_drive_type',
                        'value'     => 'dropbox',
                        'compare'   => '='
                    )
                )
            );
            $files = get_posts($args);
            foreach ($files as $file) {
                $drive_id = get_post_meta($file->ID, 'wpmf_drive_id', true);
                if (empty($driveFiles[$drive_id])) {
                    wp_delete_attachment($file->ID);
                }
            }

            return true;
        }
    }

    /**
     * Remove the folders when sync
     *
     * @param array $driveFiles Files list
     *
     * @return void
     */
    public function removeFoldersSync($driveFiles)
    {
        $args = array(
            'hide_empty' => false,
            'meta_query' => array(
                array(
                    'key'       => 'wpmf_drive_type',
                    'value'     => 'dropbox',
                    'compare'   => '='
                )
            ),
            'taxonomy'  => WPMF_TAXO
        );
        $folders = get_terms($args);

        foreach ($folders as $folder) {
            $drive_id = get_term_meta($folder->term_id, 'wpmf_drive_id', true);
            if (empty($driveFiles[$drive_id])) {
                wp_delete_term($folder->term_id, WPMF_TAXO);
            }
        }
    }

    /**
     * Import file to media library
     *
     * @param string  $cloud_id  Cloud file ID
     * @param integer $term_id   Folder target ID
     * @param boolean $imported  Check imported
     * @param string  $filename  File name
     * @param string  $extension File extension
     *
     * @return boolean
     */
    public function importFile($cloud_id, $term_id, $imported, $filename, $extension)
    {
        $dropbox    = $this->getAccount();
        $upload_dir = wp_upload_dir();
        require_once 'includes/mime-types.php';

        // get dropbox file path by ID
        $cloud_path = $dropbox->getFileByID($cloud_id);
        if (empty($cloud_path['path_display'])) {
            return false;
        }

        $extension   = strtolower($extension);
        $content     = $dropbox->get_filecontent($cloud_path['path_display']);
        $getMimeType = getMimeType($extension);
        $status = $this->insertAttachmentMetadata(
            $upload_dir['path'],
            $upload_dir['url'],
            $filename,
            $content,
            $getMimeType,
            $extension,
            $term_id
        );

        if ($status) {
            return true;
        }

        return $imported;
    }

    /**
     * Insert a attachment to database
     *
     * @param string  $upload_path Wordpress upload path
     * @param string  $upload_url  Wordpress upload url
     * @param string  $file        File name
     * @param string  $content     Content of file
     * @param string  $mime_type   Mime type of file
     * @param string  $ext         Extension of file
     * @param integer $term_id     Media folder id to set file to folder
     *
     * @return boolean
     */
    public function insertAttachmentMetadata(
        $upload_path,
        $upload_url,
        $file,
        $content,
        $mime_type,
        $ext,
        $term_id
    ) {
        $file   = wp_unique_filename($upload_path, $file);
        $upload = file_put_contents($upload_path . '/' . $file, $content);
        if ($upload) {
            $attachment = array(
                'guid'           => $upload_url . '/' . $file,
                'post_mime_type' => $mime_type,
                'post_title'     => str_replace('.' . $ext, '', $file),
                'post_status'    => 'inherit'
            );

            $image_path = $upload_path . '/' . $file;
            // Insert attachment
            $attach_id   = wp_insert_attachment($attachment, $image_path);
            $attach_data = wp_generate_attachment_metadata($attach_id, $image_path);
            wp_update_attachment_metadata($attach_id, $attach_data);
            // set attachment to term
            wp_set_object_terms((int) $attach_id, (int) $term_id, WPMF_TAXO, true);
            return true;
        }

        return false;
    }

    /**
     * Download dropbox file
     *
     * @return void
     */
    public function downloadFile()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- download URL inserted post content
        if (isset($_REQUEST['id'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- download URL inserted post content
            $id_file  = $_REQUEST['id'];
            $dropbox  = $this->getAccount();
            $getFile  = $dropbox->getMetadata($id_file);
            $pinfo    = pathinfo($getFile['path_lower']);
            $tempfile = $pinfo['basename'];
            include_once 'includes/mime-types.php';
            $contenType = getMimeType($pinfo['extension']);
            header('Content-Disposition: inline; filename="' . basename($tempfile) . '"');
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $contenType);
            header('Content-Transfer-Encoding: binary');

            header('Pragma: public');
            header('Content-Length: ' . $getFile['size']);
            $content = $dropbox->get_filecontent($getFile['path_lower']);
            // phpcs:ignore WordPress.Security.EscapeOutput -- Content already escaped in the method
            echo $content;
            die();
        } else {
            wp_send_json(false);
        }
    }

    /**
     * Redirect url
     *
     * @param string $location URL
     *
     * @return void
     */
    public function redirect($location)
    {
        if (!headers_sent()) {
            header('Location: ' . $location, true, 303);
        } else {
            // phpcs:ignore WordPress.Security.EscapeOutput -- Content already escaped in the method
            echo "<script>document.location.href='" . str_replace("'", '&apos;', $location) . "';</script>\n";
        }
    }

    /**
     * Add attachment to cloud
     *
     * @param integer $attachment_id Attachment ID
     *
     * @return void
     */
    public function addAttachment($attachment_id)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- No action, nonce is not required
        if (!empty($_POST['wpmf_folder'])) {
            $folder_id = (int) $_POST['wpmf_folder'];
            $cloud_id = wpmfGetCloudFolderID($folder_id);
            if ($cloud_id) {
                $cloud_type = wpmfGetCloudFolderType($folder_id);
                if ($cloud_type && $cloud_type === 'dropbox') {
                    try {
                        $dropbox_config = get_option('_wpmfAddon_dropbox_config');
                        $filePath = get_attached_file($attachment_id);
                        $scaled = WpmfAddonHelper::fixImageOrientation(array('file' => $filePath));
                        $filePath = $scaled['file'];
                        $size = filesize($filePath);
                        if (file_exists($filePath)) {
                            $info = pathinfo($filePath);
                            $id_folder = ($cloud_id === 'root') ? '' : $cloud_id;
                            $f         = fopen($filePath, 'rb');
                            $dropbox   = $this->getAccount();
                            $path      = $id_folder . '/' . $info['basename'];

                            $result = $dropbox->uploadFile($path, WPMFDropbox\WriteMode::add(), $f, $size);
                            // upload attachment to cloud
                            if (!empty($result)) {
                                $metadata = $dropbox->getFileMetadata($result['path_display']);
                                // add attachment meta
                                global $wpdb;
                                add_post_meta($attachment_id, 'wpmf_drive_id', $result['id']);
                                add_post_meta($attachment_id, 'wpmf_drive_type', 'dropbox');

                                // update guid URL
                                $where = array('ID' => $attachment_id);
                                if (isset($dropbox_config['link_type']) && $dropbox_config['link_type'] === 'public') {
                                    // public file
                                    $links = $dropbox->get_shared_links($result['path_display']);
                                    if (!empty($links['links'])) {
                                        $shared_links = $links['links'][0];
                                    } else {
                                        $shared_links = $dropbox->create_shared_link($result['path_display']);
                                    }
                                    $link = $shared_links['url'] . '&raw=1';
                                } else {
                                    $link = admin_url('admin-ajax.php') . '?action=wpmf-dbxdownload-file&id=' . urlencode($result['id']) . '&link=true&dl=0';
                                }

                                $wpdb->update($wpdb->posts, array('guid' => $link), $where);
                                unlink($filePath);

                                // add attachment metadata
                                $upload_path = wp_upload_dir();
                                $attached = trim($upload_path['subdir'], '/') . '/' . $info['basename'];
                                $meta = array();
                                if (isset($metadata['media_info']['metadata']['dimensions']['width']) && isset($metadata['media_info']['metadata']['dimensions']['height'])) {
                                    $meta['width'] = $metadata['media_info']['metadata']['dimensions']['width'];
                                    $meta['height'] = $metadata['media_info']['metadata']['dimensions']['height'];
                                } else {
                                    list($width, $heigth) = wpmfGetImgSize($link);
                                    $meta['width'] = $width;
                                    $meta['height'] = $heigth;
                                }

                                $meta['file'] = $attached;
                                if (isset($metadata['size'])) {
                                    $meta['filesize'] = $metadata['size'];
                                }
                                add_post_meta($attachment_id, 'wpmf_attachment_metadata', $meta);
                            }
                        }
                    } catch (Exception $e) {
                        echo esc_html($e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Update metadata for cloud file
     *
     * @param array   $meta          Meta data
     * @param integer $attachment_id Attachment ID
     *
     * @return mixed
     */
    public function wpGenerateAttachmentMetadata($meta, $attachment_id)
    {
        $drive_id = get_post_meta($attachment_id, 'wpmf_drive_id', true);
        if (!empty($drive_id)) {
            $data = get_post_meta($attachment_id, 'wpmf_attachment_metadata', true);
            if (!empty($data) && !empty($meta)) {
                $meta = $data;
                delete_post_meta($attachment_id, 'wpmf_attachment_metadata');
            }
        }

        return $meta;
    }

    /**
     * Create cloud folder from media library
     *
     * @param integer $folder_id    Local folder ID
     * @param string  $name         Folder name
     * @param integer $parent_id    Local folder parent ID
     * @param array   $informations Informations
     *
     * @return boolean
     */
    public function createFolderLibrary($folder_id, $name, $parent_id, $informations)
    {
        try {
            $cloud_id = wpmfGetCloudFolderID($parent_id);
            if ($cloud_id) {
                $cloud_type = wpmfGetCloudFolderType($parent_id);
                if ($cloud_type && $cloud_type === 'dropbox') {
                    if ($cloud_id === 'root') {
                        $cloud_path = '';
                    } else {
                        $dropbox = $this->getAccount();
                        $cloud_id = $dropbox->getFileByID($cloud_id);
                        $cloud_path = $cloud_id['path_display'];
                    }

                    $folder = $this->doCreateFolder($name, $cloud_path);
                    add_term_meta($folder_id, 'wpmf_drive_id', $folder['id']);
                    add_term_meta($folder_id, 'wpmf_drive_type', 'dropbox');
                }
            }
        } catch (Exception $ex) {
            return false;
        }

        return true;
    }

    /**
     * Delete cloud folder from media library
     *
     * @param object $folder Local folder info
     *
     * @return boolean
     */
    public function deleteFolderLibrary($folder)
    {
        try {
            $cloud_id = wpmfGetCloudFolderID($folder->term_id);
            if ($cloud_id) {
                $cloud_type = wpmfGetCloudFolderType($folder->term_id);
                if ($cloud_type && $cloud_type === 'dropbox') {
                    $dropbox = $this->getAccount();
                    if ($cloud_id !== 'root') {
                        $cloud_path = $dropbox->getFileByID($cloud_id);
                        $dropbox->delete($cloud_path['path_display']);
                    }
                }
            }
        } catch (Exception $ex) {
            return false;
        }

        return true;
    }

    /**
     * Rename cloud folder from media library
     *
     * @param integer $id   Local folder ID
     * @param string  $name New name
     *
     * @return boolean
     */
    public function updateFolderNameLibrary($id, $name)
    {
        try {
            $cloud_id = wpmfGetCloudFolderID($id);
            if ($cloud_id) {
                $cloud_type = wpmfGetCloudFolderType($id);
                if ($cloud_type && $cloud_type === 'dropbox') {
                    $dropbox = $this->getAccount();
                    if ($cloud_id !== 'root') {
                        $cloud_path = $dropbox->getFileByID($cloud_id);
                        $pathinfo = pathinfo($cloud_path['path_display']);
                        $dropbox->move($cloud_path['path_display'], rtrim($pathinfo['dirname'], '/') . '/' . urldecode($name));
                    }
                }
            }
        } catch (Exception $ex) {
            return false;
        }

        return true;
    }

    /**
     * Move cloud folder from media library
     *
     * @param integer $folder_id    Local folder ID
     * @param integer $parent_id    Local folder new parent ID
     * @param array   $informations Informations
     *
     * @return boolean
     */
    public function moveFolderLibrary($folder_id, $parent_id, $informations)
    {
        try {
            $cloud_id = wpmfGetCloudFolderID($folder_id);
            if ($cloud_id) {
                $cloud_type = wpmfGetCloudFolderType($folder_id);
                if ($cloud_type && $cloud_type === 'dropbox') {
                    if ($cloud_id !== 'root') {
                        $dropbox = $this->getAccount();
                        $cloud_parentid = wpmfGetCloudFolderID($parent_id);
                        $cloud_path = $dropbox->getFileByID($cloud_id);
                        $pathinfo = pathinfo($cloud_path['path_display']);
                        if ($cloud_parentid === 'root') {
                            $newpath = '/' . $pathinfo['filename'];
                        } else {
                            $cloud_parent_path = $dropbox->getFileByID($cloud_parentid);
                            $newpath = $cloud_parent_path['path_display'] . '/' . $pathinfo['filename'];
                        }

                        $dropbox->move($cloud_path['path_display'], $newpath);
                    }
                }
            }
        } catch (Exception $ex) {
            return false;
        }

        return true;
    }

    /**
     * Move cloud folder from media library
     *
     * @param integer $fileid       Local file ID
     * @param integer $parent_id    Local folder new parent ID
     * @param array   $informations Informations
     *
     * @return boolean
     */
    public function moveFileLibrary($fileid, $parent_id, $informations)
    {
        try {
            $cloud_id = wpmfGetCloudFileID($fileid);
            if ($cloud_id) {
                $cloud_type = wpmfGetCloudFileType($fileid);
                if ($cloud_type && $cloud_type === 'dropbox') {
                    $dropbox = $this->getAccount();
                    $cloud_parentid = wpmfGetCloudFolderID($parent_id);

                    $cloud_path = $dropbox->getFileByID($cloud_id);
                    $pathinfo = pathinfo($cloud_path['path_display']);
                    if ($cloud_parentid === 'root') {
                        $newpath = '/' . $pathinfo['basename'];
                    } else {
                        $cloud_parent_path = $dropbox->getFileByID($cloud_parentid);
                        $newpath = $cloud_parent_path['path_display'] . '/' . $pathinfo['basename'];
                    }

                    $dropbox->move($cloud_path['path_display'], $newpath);
                }
            }
        } catch (Exception $ex) {
            return false;
        }

        return true;
    }

    /**
     * Delete cloud attachment
     *
     * @param integer $pid Attachment ID
     *
     * @return boolean
     */
    public function deleteAttachment($pid)
    {
        try {
            $cloud_id = wpmfGetCloudFileID($pid);
            if ($cloud_id) {
                $cloud_type = wpmfGetCloudFileType($pid);
                if ($cloud_type && $cloud_type === 'dropbox') {
                    $dropbox = $this->getAccount();
                    $cloud_path = $dropbox->getFileByID($cloud_id);
                    $dropbox->delete($cloud_path['path_display']);
                }
            }
        } catch (Exception $ex) {
            return false;
        }

        return true;
    }

    /**
     * Get file link
     *
     * @param string $id             Cloud file ID
     * @param array  $dropbox_config Dropbox settings
     * @param object $dropbox        Dropbox Client
     *
     * @return boolean|string
     */
    public function getLink($id, $dropbox_config, $dropbox)
    {
        try {
            $cloud_path = $dropbox->getFileByID($id);
            if (isset($dropbox_config['link_type']) && $dropbox_config['link_type'] === 'public') {
                // public file
                $links = $dropbox->get_shared_links($cloud_path['path_display']);
                if (!empty($links['links'])) {
                    $shared_links = $links['links'][0];
                } else {
                    $shared_links = $dropbox->create_shared_link($cloud_path['path_display']);
                }
                $link = $shared_links['url'] . '&raw=1';
            } else {
                $link = admin_url('admin-ajax.php') . '?action=wpmf-dbxdownload-file&id=' . urlencode($cloud_path['path_display']) . '&link=true&dl=0';
            }
        } catch (Exception $e) {
            $link = false;
        }

        return $link;
    }

    /**
     * Insert attachment
     *
     * @param array   $info        File info
     * @param array   $child       File details
     * @param integer $parent      Parent folder
     * @param array   $upload_path Upload path
     * @param string  $link        Link
     * @param string  $mimeType    Mime Type
     * @param integer $width       Width
     * @param integer $height      Height
     *
     * @return void
     */
    public function insertAttachment($info, $child, $parent, $upload_path, $link, $mimeType, $width = 0, $height = 0)
    {
        $attachment = array(
            'guid'           => $link,
            'post_mime_type' => $mimeType,
            'post_title'     => $info['filename'],
            'post_type'     => 'attachment',
            'post_status'    => 'inherit'
        );

        $attach_id   = wp_insert_post($attachment);
        $attached = trim($upload_path['subdir'], '/') . '/' . $child['name'];
        wp_set_object_terms((int) $attach_id, (int) $parent, WPMF_TAXO);

        update_post_meta($attach_id, '_wp_attached_file', $attached);
        update_post_meta($attach_id, 'wpmf_size', $child['size']);
        update_post_meta($attach_id, 'wpmf_filetype', $info['extension']);
        update_post_meta($attach_id, 'wpmf_order', 0);
        update_post_meta($attach_id, 'wpmf_drive_id', $child['id']);
        update_post_meta($attach_id, 'wpmf_drive_type', 'dropbox');

        $meta = array();
        if (strpos($mimeType, 'image') !== false) {
            if (!empty($width) && !empty($height)) {
                $meta['width'] = $width;
                $meta['height'] = $height;
            } else {
                list($width, $heigth) = wpmfGetImgSize($link);
                $meta['width'] = $width;
                $meta['height'] = $heigth;
            }
        }

        if (isset($child['size'])) {
            $meta['filesize'] = $child['size'];
        }
        update_post_meta($attach_id, '_wp_attachment_metadata', $meta);
    }

    /**
     * Update attachment
     *
     * @param array   $info    File info
     * @param integer $file_id Attachment ID
     * @param integer $parent  Parent folder
     *
     * @return void
     */
    public function updateAttachment($info, $file_id, $parent)
    {
        $curent_parents = get_the_terms($file_id, WPMF_TAXO);
        if (isset($parent)) {
            if (!$curent_parents) {
                wp_set_object_terms((int) $file_id, (int)$parent, WPMF_TAXO);
            } else {
                foreach ($curent_parents as $curent_parent) {
                    if ((int)$curent_parent->term_id !== (int)$parent) {
                        wp_set_object_terms((int) $file_id, (int)$parent, WPMF_TAXO);
                    }
                }
            }
        }

        $attached_file = get_post_meta($file_id, '_wp_attached_file', true);
        $attached_info = pathinfo($attached_file);
        if ($info['filename'] !== $attached_info['filename']) {
            $new_path = str_replace($attached_info['filename'], $info['filename'], $attached_file);
            update_post_meta($file_id, '_wp_attached_file', $new_path);
        }
    }

    /**
     * Sync with media library
     *
     * @param object $dropbox       Dropbox Client
     * @param string $folderID      Folder ID
     * @param string $parent        Parent
     * @param array  $allDriveFiles All rrive files
     *
     * @return array
     */
    public function doAutoSyncWithCrontabMethod($dropbox, $folderID, $parent, $allDriveFiles)
    {
        $fs = $dropbox->getMetadataWithChildren($folderID);
        $childs = $fs['entries'];
        // Create folders in media library
        $upload_path = wp_upload_dir();
        include_once 'includes/mime-types.php';
        // Create files in media library
        foreach ($childs as $child) {
            if ($child['.tag'] === 'file') {
                $allDriveFiles[$child['id']] = $child;
                $info = pathinfo($child['name']);
                $args = array(
                    'post_type' => 'attachment',
                    'posts_per_page' => -1,
                    'post_status' => 'any',
                    'meta_query' => array(
                        array(
                            'key' => 'wpmf_drive_id',
                            'value' => $child['id'],
                            'compare' => '='
                        )
                    )
                );
                $files = get_posts($args);
                if (empty($files)) {
                    // insert attachment
                    $dropbox_config = get_option('_wpmfAddon_dropbox_config');
                    $link = $this->getLink($child['id'], $dropbox_config, $dropbox);
                    if ($link) {
                        $fileExtension = pathinfo($child['name'], PATHINFO_EXTENSION);
                        $mimeType   = getMimeType($fileExtension);
                        $width = isset($child['media_info']['metadata']['dimensions']['width']) ? $child['media_info']['metadata']['dimensions']['width'] : 0;
                        $height = isset($child['media_info']['metadata']['dimensions']['width']) ? $child['media_info']['metadata']['dimensions']['width'] : 0;

                        if (strpos($mimeType, 'image') !== false) {
                            $content = $dropbox->get_filecontent($child['path_lower']);
                            $filePath = $upload_path['path'] . '/' . $child['name'];
                            file_put_contents($filePath, $content);
                            $exif_data = wp_read_image_metadata($filePath);
                            if (!empty($exif_data) && isset($exif_data['orientation'])) {
                                $scaled = WpmfAddonHelper::fixImageOrientation(array('file' => $filePath));
                                $filePath = $scaled['file'];
                                $f         = fopen($filePath, 'rb');
                                $cloud_id = wpmfGetCloudFolderID($parent);
                                $id_folder = ($cloud_id === 'root') ? '' : $cloud_id;
                                $path      = $id_folder . '/' . $info['basename'];
                                $size = filesize($filePath);
                                $result = $dropbox->updateFile($path, $child['rev'], WPMFDropbox\WriteMode::update($child['rev']), $f, $size);
                                if (file_exists($filePath)) {
                                    unlink($filePath);
                                }
                            }
                        }
                        $this->insertAttachment($info, $child, $parent, $upload_path, $link, $mimeType, $width, $height);
                    }
                } else {
                    // update attachment
                    foreach ($files as $file) {
                        $this->updateAttachment($info, $file->ID, $parent);
                    }
                }
            } else {
                $allDriveFiles[$child['id']] = $child;
                $info = pathinfo($child['name']);
                $args = array(
                    'hide_empty' => false,
                    'meta_query' => array(
                        array(
                            'key' => 'wpmf_drive_id',
                            'value' => $child['id'],
                            'compare' => '='
                        )
                    ),
                    'taxonomy' => WPMF_TAXO
                );
                $folders = get_terms($args);
                if (empty($folders)) {
                    $inserted = wp_insert_term($child['name'], WPMF_TAXO, array('parent' => (int)$parent));
                    if (!is_wp_error($inserted)) {
                        add_term_meta($inserted['term_id'], 'wpmf_drive_id', $child['id']);
                        add_term_meta($inserted['term_id'], 'wpmf_drive_type', 'dropbox');
                        $allDriveFiles = $this->doAutoSyncWithCrontabMethod($dropbox, $child['id'], $inserted['term_id'], $allDriveFiles);
                    }
                } else {
                    foreach ($folders as $folder) {
                        if (isset($parent)) {
                            if ((int)$folder->parent !== (int)$parent) {
                                wp_update_term((int)$folder->term_id, WPMF_TAXO, array('parent' => (int)$parent));
                            }
                        }

                        if ($info['filename'] !== $folder->name) {
                            wp_update_term((int)$folder->term_id, WPMF_TAXO, array('name' => $info['filename']));
                        }
                        $allDriveFiles = $this->doAutoSyncWithCrontabMethod($dropbox, $child['id'], $folder->term_id, $allDriveFiles);
                    }
                }
            }
        }

        return $allDriveFiles;
    }

    /**
     * Sync folders and files with crontab method
     *
     * @return void
     */
    public function autoSyncWithCrontabMethod()
    {
        $dropbox_config = get_option('_wpmfAddon_dropbox_config');
        if (!empty($dropbox_config['dropboxToken'])) {
            try {
                set_time_limit(0);
                $dropbox = $this->getAccount();
                $folderID = '';
                $inserted = wp_insert_term('Dropbox', WPMF_TAXO, array('parent' => 0));
                if (is_wp_error($inserted)) {
                    $root_id = $inserted->error_data['term_exists'];
                } else {
                    $root_id = $inserted['term_id'];
                }

                $cloud_folder_id = get_term_meta($root_id, 'wpmf_drive_root_id', true);
                if (!empty($cloud_folder_id)) {
                    update_term_meta($root_id, 'wpmf_drive_root_id', 'root');
                } else {
                    add_term_meta($root_id, 'wpmf_drive_root_id', 'root');
                }

                update_option('wpmf_dropbox_folder_id', $root_id);
                $allDriveFiles = $this->doAutoSyncWithCrontabMethod($dropbox, $folderID, $root_id, array());
                $this->removeFilesSync($allDriveFiles);
                $this->removeFoldersSync($allDriveFiles);
                $time = time();
                if (!get_option('wpmf_cloud_time_last_sync', false)) {
                    add_option('wpmf_cloud_time_last_sync', $time);
                } else {
                    update_option('wpmf_cloud_time_last_sync', $time);
                }
            } catch (Exception $ex) {
                echo 'Sync cloud error';
            }
        }
    }
}
