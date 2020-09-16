<?php
/* Prohibit direct script loading */
defined('ABSPATH') || die('No direct script access allowed!');

/**
 * Class WpmfAddonGoogleDrive
 * This class that holds most of the admin functionality for Google Drive
 */
class WpmfAddonGoogleDrive
{

    /**
     * Params
     *
     * @var $param
     */
    protected $params;

    /**
     * Last error
     *
     * @var $lastError
     */
    protected $lastError;

    /**
     * Breadcrumb
     *
     * @var string
     */
    public $breadcrumb = '';

    /**
     * Files fields
     *
     * @var string
     */
    protected $wpmffilesfields = 'nextPageToken,items(thumbnailLink,alternateLink,id,description,labels(hidden,restricted,trashed),embedLink,etag,downloadUrl,iconLink,exportLinks,mimeType,modifiedDate,fileExtension,webContentLink,fileSize,userPermission,imageMediaMetadata(width,height),kind,permissions(kind,name,role,type,value,withLink),parents(id,isRoot,kind),title,openWithLinks),kind';

    /**
     * WpmfAddonGoogleDrive constructor.
     *
     * @param string $type Google photo or google drive
     */
    public function __construct($type = 'google-drive')
    {
        set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());
        require_once 'Google/autoload.php';
        $this->loadParams($type);
    }

    /**
     * Is Shared Drive
     *
     * @param array $configs Configs
     *
     * @return boolean
     */
    public function isTeamDrives($configs)
    {
        if (!empty($configs['drive_type']) && $configs['drive_type'] === 'team_drive') {
            return true;
        }

        return false;
    }

    /**
     * Get google drive config
     *
     * @return mixed
     */
    public function getAllCloudConfigs()
    {
        return WpmfAddonHelper::getAllCloudConfigs();
    }

    /**
     * Save google drive config
     *
     * @param array $data Data config
     *
     * @return boolean
     */
    public function saveCloudConfigs($data)
    {
        return WpmfAddonHelper::saveCloudConfigs($data);
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
     * Load google drive params
     *
     * @param string $type Google photo or google drive
     *
     * @return void
     */
    protected function loadParams($type = 'google-drive')
    {
        if ($type === 'google-drive') {
            $params       = WpmfAddonHelper::getAllCloudConfigs();
        } else {
            $params       = WpmfAddonHelper::getAllCloudConfigs($type);
        }

        $this->params = new stdClass();

        $this->params->google_client_id     = isset($params['googleClientId']) ? $params['googleClientId'] : '';
        $this->params->google_client_secret = isset($params['googleClientSecret']) ? $params['googleClientSecret'] : '';
        $this->params->google_credentials   = isset($params['googleCredentials']) ? $params['googleCredentials'] : '';
    }

    /**
     * Save google drive params
     *
     * @return void
     */
    protected function saveParams()
    {
        $params                       = $this->getAllCloudConfigs();
        $params['googleClientId']     = $this->params->google_client_id;
        $params['googleClientSecret'] = $this->params->google_client_secret;
        $params['googleCredentials']  = $this->params->google_credentials;
        $this->saveCloudConfigs($params);
    }

    /**
     * Get author url
     *
     * @return string
     */
    public function getAuthorisationUrl()
    {
        $client = new WpmfGoogle_Client();
        $client->setClientId($this->params->google_client_id);
        $uri = admin_url('options-general.php?page=option-folder&task=wpmf&function=wpmf_authenticated');
        $client->setRedirectUri($uri);
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');
        $client->setState('');
        $client->setScopes(array(
            'https://www.googleapis.com/auth/drive',
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile',
            'https://www.googleapis.com/auth/drive.appdata',
            'https://www.googleapis.com/auth/drive.apps.readonly',
            'https://www.googleapis.com/auth/drive.file'
        ));

        $tmpUrl = parse_url($client->createAuthUrl());
        $query  = explode('&', $tmpUrl['query']);
        $url    = $tmpUrl['scheme'] . '://' . $tmpUrl['host'];
        if (isset($tmpUrl['port'])) {
            $url .= $tmpUrl['port'] . $tmpUrl['path'] . '?' . implode('&', $query);
        } else {
            $url .= $tmpUrl['path'] . '?' . implode('&', $query);
        }

        return $url;
    }

    /**
     * Access google drive app
     *
     * @param string $type Google photo or google drive
     *
     * @return string
     */
    public function authenticate($type = 'google-drive')
    {
        $code   = $this->getInput('code', 'GET', 'none');
        $client = new WpmfGoogle_Client();
        $client->setClientId($this->params->google_client_id);
        $client->setClientSecret($this->params->google_client_secret);
        if ($type === 'google-drive') {
            $url = admin_url('options-general.php?page=option-folder&task=wpmf&function=wpmf_authenticated');
        } else {
            $url = admin_url('options-general.php?page=option-folder&task=wpmf&function=wpmf_google_photo_authenticated');
        }

        $client->setRedirectUri($url);
        return $client->authenticate($code);
    }

    /**
     * Logout google drive app
     *
     * @return void
     */
    public function logout()
    {
        $client = new WpmfGoogle_Client();
        $client->setClientId($this->params->google_client_id);
        $client->setClientSecret($this->params->google_client_secret);
        $client->setAccessToken($this->params->google_credentials);
        $client->revokeToken();
    }

    /**
     * Set credentials
     *
     * @param string $credentials Credentials
     *
     * @return void
     */
    public function storeCredentials($credentials)
    {
        $this->params->google_credentials = $credentials;
        $this->saveParams();
    }

    /**
     * Get credentials
     *
     * @return mixed
     */
    public function getCredentials()
    {
        return $this->params->google_credentials;
    }

    /**
     * Check author
     *
     * @return array
     */
    public function checkAuth()
    {
        $client = new WpmfGoogle_Client();
        $client->setClientId($this->params->google_client_id);
        $client->setClientSecret($this->params->google_client_secret);

        try {
            $client->setAccessToken($this->params->google_credentials);
            $service = new WpmfGoogle_Service_Drive($client);
            $service->files->listFiles(array());
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
        return array('success' => true);
    }

    /**
     * Get Google Client
     *
     * @param array $config Google client config
     *
     * @return WpmfGoogle_Client
     */
    public function getClient($config)
    {
        $client                 = new WpmfGoogle_Client();
        $client->setClientId($config['googleClientId']);
        $client->setClientSecret($config['googleClientSecret']);
        $client->setAccessToken($config['googleCredentials']);
        return $client;
    }

    /**
     * Check folder exist
     *
     * @param integer $id Id of folder
     *
     * @return boolean
     */
    public function folderExists($id)
    {
        $config = get_option('_wpmfAddon_cloud_config');
        $client = $this->getClient($config);
        $service = new WpmfGoogle_Service_Drive($client);
        try {
            $file = $service->files->get(
                $id,
                array(
                    'supportsAllDrives' => $this->isTeamDrives($config)
                )
            );
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
        return true;
    }

    /**
     * Create folder
     *
     * @param string $name     Folder name
     * @param string $parentID Folder parent ID
     *
     * @return WpmfGoogle_Service_Drive_DriveFile
     */
    public function doCreateFolder($name, $parentID)
    {
        $config = get_option('_wpmfAddon_cloud_config');
        $client = $this->getClient($config);
        $service = new WpmfGoogle_Service_Drive($client);
        $file = new WpmfGoogle_Service_Drive_DriveFile();
        $file->name = $name;
        $file->mimeType = 'application/vnd.google-apps.folder';
        if ($parentID !== null) {
            $file->parents = array($parentID);
        }

        $fileId = $service->files->create($file, array('supportsAllDrives' => $this->isTeamDrives($config)));
        return $fileId;
    }

    /**
     * Add new folder when connect google drive
     *
     * @param string $title    Title of folder
     * @param null   $parentId Parent of folder
     *
     * @return boolean|WpmfGoogle_Service_Drive_DriveFile
     */
    public function createFolder($title, $parentId = null)
    {
        try {
            $config = get_option('_wpmfAddon_cloud_config');
            $client = $this->getClient($config);
            $client->setScopes(array(
                'https://www.googleapis.com/auth/drive',
                'https://www.googleapis.com/auth/drive.appdata',
                'https://www.googleapis.com/auth/drive.apps.readonly',
                'https://www.googleapis.com/auth/drive.file'
            ));
            $service = new WpmfGoogle_Service_Drive($client);
            if ($this->isTeamDrives($config)) {
                $drive = new WpmfGoogle_Service_Drive_Drive();
                $drive->name = $title;
                $fileId = $service->drives->create(time(), $drive);
            } else {
                $file           = new WpmfGoogle_Service_Drive_DriveFile();
                $file->name    = $title;
                $file->mimeType = 'application/vnd.google-apps.folder';
                if ($parentId !== null) {
                    $file->parents = array($parentId);
                }

                $fileId = $service->files->create($file, array('supportsAllDrives' => $this->isTeamDrives($config)));
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
        return $fileId;
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
        update_option('wpmf_cloud_name_syncing', 'google_drive');
        // Create folders in media library
        $list_folders = get_option('wpmf_google_folders');
        $allDriveFiles = get_option('wpmf_google_allfiles');
        $childs     = array();
        $pageToken  = null;
        do {
            try {
                $configs = get_option('_wpmfAddon_cloud_config');
                $params = array(
                    'q'          => "'" . $folderID . "' in parents and trashed = false",
                    'supportsAllDrives' => $this->isTeamDrives($configs)
                );

                if ($this->isTeamDrives($configs)) {
                    $params['corpora'] = 'drive';
                    $params['driveId'] = $configs['googleBaseFolder'];
                    $params['includeItemsFromAllDrives'] = true;
                }

                if ($pageToken) {
                    $params['pageToken'] = $pageToken;
                }

                $client = $this->getClient($configs);
                $service     = new WpmfGoogle_Service_Drive($client);
                $files     = $service->files->listFiles($params);
                $childs    = array_merge($childs, $files->getFiles());
                $pageToken = $files->getNextPageToken();
            } catch (Exception $e) {
                print 'An error occurred: ' . esc_html($e->getMessage());
                $pageToken = null;
            }
        } while ($pageToken);

        // Create files in media library
        $list_files = get_option('wpmf_google_attachments');
        foreach ($childs as $child) {
            if ($child->mimeType !== 'application/vnd.google-apps.folder') {
                $allDriveFiles[$child->id] = array('id' => $child->id, 'type' => 'file');
                $list_files[$child->id] = array(
                    'id' => $child->id,
                    'name' => $child->name,
                    'parent' => $parent,
                    'file' => array('mimeType' => $child->mimeType),
                    'image' => array(),
                    'size' => $child->fileSize
                );

                if (strpos($child->mimeType, 'image') !== false) {
                    $metadata = $child->getImageMediaMetadata();
                    $dimensions = array('width' => 0, 'height' => 0);
                    if (isset($metadata)) {
                        $dimensions = array(
                            'width' => $metadata->width,
                            'height' => $metadata->height
                        );
                    }

                    $list_files[$child->id]['image'] = $dimensions;
                }
            }
        }

        foreach ($childs as $child) {
            if ($child->mimeType === 'application/vnd.google-apps.folder') {
                $allDriveFiles[$child->id] = array('id' => $child->id, 'type' => 'folder');
                $info = pathinfo($child->name);
                $args = array(
                    'hide_empty' => false,
                    'meta_query' => array(
                        array(
                            'key'       => 'wpmf_drive_id',
                            'value'     => $child->id,
                            'compare'   => '='
                        )
                    ),
                    'taxonomy'  => WPMF_TAXO
                );
                $folders = get_terms($args);
                if (empty($folders)) {
                    $inserted = wp_insert_term($child->name, WPMF_TAXO, array('parent' => (int) $parent));
                    if (!is_wp_error($inserted)) {
                        $list_folders[$child->id] = array('id' => $child->id, 'parent' => $inserted['term_id']);
                        add_term_meta($inserted['term_id'], 'wpmf_drive_id', $child->id);
                        add_term_meta($inserted['term_id'], 'wpmf_drive_type', 'google_drive');
                    }
                } else {
                    foreach ($folders as $folder) {
                        $list_folders[$child->id] = array('id' => $child->id, 'parent' => $folder->term_id);
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

        if (isset($list_folders[$folderID])) {
            unset($list_folders[$folderID]);
        }

        update_option('wpmf_google_attachments', $list_files);
        update_option('wpmf_google_folders', $list_folders);
        update_option('wpmf_google_allfiles', $allDriveFiles);
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

        if (!get_option('wpmf_google_folders', false)) {
            add_option('wpmf_google_folders', array(), '', 'yes');
        }

        if (!get_option('wpmf_google_attachments', false)) {
            add_option('wpmf_google_attachments', array(), '', 'yes');
        }

        if (!get_option('wpmf_google_allfiles', false)) {
            add_option('wpmf_google_allfiles', array(), '', 'yes');
        }

        try {
            set_time_limit(0);
            $params = get_option('_wpmfAddon_cloud_config');
            if (empty($params['googleBaseFolder'])) {
                wp_send_json(array('status' => false));
            }

            if (isset($params['connected']) && (int) $params['connected'] === 0) {
                wp_send_json(array('status' => false));
            }

            // continue sync with next cloud
            if (isset($_POST['type']) && $_POST['type'] === 'auto') {
                $options = array(
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

            $list_folders = get_option('wpmf_google_folders');
            if (empty($list_folders)) {
                $list_folders = array();
                $folderID = $params['googleBaseFolder'];
                $inserted = wp_insert_term('Google Drive', WPMF_TAXO, array('parent' => 0));
                if (is_wp_error($inserted)) {
                    $root_id = $inserted->error_data['term_exists'];
                } else {
                    $root_id = $inserted['term_id'];
                }

                $cloud_folder_id = get_term_meta($root_id, 'wpmf_drive_root_id', true);
                if (!empty($cloud_folder_id)) {
                    update_term_meta($root_id, 'wpmf_drive_root_id', $folderID);
                } else {
                    add_term_meta($root_id, 'wpmf_drive_root_id', $folderID);
                }

                $list_folders[$folderID] = array('id' => $folderID, 'parent' => $root_id);
                update_option('wpmf_google_folders', $list_folders);
                update_option('wpmf_google_folder_id', $root_id);
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
        $params = get_option('_wpmfAddon_cloud_config');
        if (empty($params['googleCredentials'])) {
            wp_send_json(array('status' => false));
        }

        $drive_files = get_option('wpmf_google_attachments');
        if (empty($drive_files)) {
            wp_send_json(array('status' => true, 'continue' => false));
        }

        if (isset($params['link_type']) && $params['link_type'] === 'public') {
            $limit = 1;
        } else {
            $limit = 5;
        }

        $i = 0;
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
                    $link = $this->getLink($child['id']);
                    if (!$link) {
                        if (isset($drive_files[$cloudFileId])) {
                            unset($drive_files[$cloudFileId]);
                            update_option('wpmf_google_attachments', $drive_files);
                        }
                        continue;
                    }
                    $attachment = array(
                        'guid'           => $link,
                        'post_mime_type' => $child['file']['mimeType'],
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
                    update_post_meta($attach_id, 'wpmf_drive_type', 'google_drive');

                    $meta = array();
                    if (strpos($child['file']['mimeType'], 'image') !== false) {
                        if (isset($child['image']['width']) && isset($child['image']['height'])) {
                            $meta['width'] = $child['image']['width'];
                            $meta['height'] = $child['image']['height'];
                        } else {
                            list($width, $heigth) = wpmfGetImgSize($link);
                            $meta['width'] = $width;
                            $meta['height'] = $heigth;
                        }

                        $meta['file'] = $attached;
                    }

                    if (isset($child['size'])) {
                        $meta['filesize'] = $child['size'];
                    }
                    update_post_meta($attach_id, '_wp_attachment_metadata', $meta);
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
                update_option('wpmf_google_attachments', $drive_files);
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
        $allDriveFiles = get_option('wpmf_google_allfiles');
        remove_action('delete_attachment', array($this, 'deleteAttachment'));
        remove_action('wpmf_before_delete_folder', array($this, 'deleteFolderLibrary'));
        if (!empty($allDriveFiles)) {
            $this->removeFoldersSync($allDriveFiles);
            $paged = isset($_POST['paged']) ? (int) $_POST['paged'] : 1;
            $continue = $this->removeFilesSync($allDriveFiles, $paged, true);
            if ($continue) {
                sleep(1);
                wp_send_json(array(
                    'status'   => true,
                    'continue' => true
                ));
            }
        }

        update_option('wpmf_google_allfiles', array());
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
                        'value'     => 'google_drive',
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
                        'value'     => 'google_drive',
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
                    'value'     => 'google_drive',
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
     * Download google file
     *
     * @return void
     */
    public function downloadFile()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- download URL inserted post content
        if (empty($_REQUEST['id'])) {
            wp_send_json(array('status' => false));
        }

        $id = $_REQUEST['id'];

        $config = get_option('_wpmfAddon_cloud_config');
        $client = $this->getClient($config);
        $service = new WpmfGoogle_Service_Drive($client);

        $file    = $service->files->get($id, array('supportsAllDrives' => $this->isTeamDrives($config)));
        $content    = $service->files->get($id, array('alt' => 'media', 'supportsAllDrives' => $this->isTeamDrives($config)));
        if ($file !== null) {
            include_once 'includes/mime-types.php';
            $contenType = getMimeType($file->fileExtension);
            $this->downloadHeader($file->name, (int) $file->fileSize, $contenType);
            // phpcs:ignore WordPress.Security.EscapeOutput -- Content already escaped in the method
            echo $content;
        }

        die();
    }

    /**
     * Send a raw HTTP header
     *
     * @param string  $file       File name
     * @param integer $size       File size
     * @param string  $contenType Content type
     *
     * @return void
     */
    public function downloadHeader($file, $size, $contenType)
    {
        ob_end_clean();
        ob_start();
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $contenType);
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        if ((int) $size !== 0) {
            header('Content-Length: ' . $size);
        }
        ob_clean();
        flush();
    }

    /**
     * Get publish link file
     *
     * @return void
     */
    public function previewFile()
    {
        if (empty($_REQUEST['wpmf_nonce'])
            || !wp_verify_nonce($_REQUEST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        ob_start();
        $html = '';
        if (isset($_REQUEST['id']) && isset($_REQUEST['mimetype']) && isset($_REQUEST['ext'])) {
            $ext        = $_REQUEST['ext'];
            $imagesType = array('jpg', 'png', 'gif', 'jpeg', 'jpe', 'bmp', 'ico', 'tiff', 'tif', 'svg', 'svgz');
            $videoType  = array(
                'mp4',
                'wmv',
                'mpeg',
                'mpe',
                'mpg',
                'mov',
                'qt',
                'rv',
                'avi',
                'movie',
                'flv',
                'webm',
                'ogv'
            );//,'3gp'
            $audioType  = array(
                'mid',
                'midi',
                'mp2',
                'mp3',
                'mpga',
                'ram',
                'rm',
                'rpm',
                'ra',
                'wav'
            );  // ,'aif','aifc','aiff'
            if (in_array($ext, $imagesType)) {
                $mediaType = 'image';
            } elseif (in_array($ext, $videoType)) {
                $mediaType = 'video';
            } elseif (in_array($ext, $audioType)) {
                $mediaType = 'audio';
            } else {
                $mediaType = '';
            }

            $mimetype     = $_REQUEST['mimetype'];
            $downloadLink = admin_url('admin-ajax.php') . '?action=wpmf-download-file&id=' . urlencode($_REQUEST['id']) . '&link=true&dl=1';
            require(WPMFAD_PLUGIN_DIR . '/class/templates/media.php');
            $html = ob_get_contents();
            ob_end_clean();
            // phpcs:ignore WordPress.Security.EscapeOutput -- Content already escaped in the method
            echo $html;
        }
        die();
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
        $config = get_option('_wpmfAddon_cloud_config');
        $client = $this->getClient($config);
        $service = new WpmfGoogle_Service_Drive($client);

        $upload_dir = wp_upload_dir();
        $file         = $service->files->get($cloud_id, array('supportsAllDrives' => $this->isTeamDrives($config)));
        if (!empty($file)) {
            $content   = $service->files->get($cloud_id, array('alt' => 'media', 'supportsAllDrives' => $this->isTeamDrives($config)));
            $mime_type = strtolower($file->getMimeType());
            $status = $this->insertAttachmentMetadata(
                $upload_dir['path'],
                $upload_dir['url'],
                $filename,
                $content,
                $mime_type,
                $extension,
                $term_id
            );

            if ($status) {
                return true;
            }
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
     * Do upload File
     *
     * @param string $client   Google client
     * @param string $filePath File path
     * @param string $parentID Cloud parent ID
     * @param string $name     File name
     * @param string $action   Action
     *
     * @return mixed
     */
    public function doUploadFile($client, $filePath, $parentID, $name, $action = 'upload')
    {
        /* Update Mime-type if needed (for IE8 and lower?) */
        include_once 'includes/mime-types.php';
        $config = get_option('_wpmfAddon_cloud_config');
        $fileExtension = pathinfo($name, PATHINFO_EXTENSION);
        $filetype    = getMimeType($fileExtension);
        $chunkSizeBytes = 1 * 1024 * 1024;
        try {
            /* Create new Google File */
            $googledrive_file = new WpmfGoogle_Service_Drive_DriveFile();
            $googledrive_file->setName($name);
            $googledrive_file->setMimeType($filetype);
            $googledrive_file->setParents(array($parentID));

            /* Call the API with the media upload, defer so it doesn't immediately return. */
            $service = new WpmfGoogle_Service_Drive($client);
            $client->setDefer(true);
            $request = $service->files->create($googledrive_file, array('supportsAllDrives' => $this->isTeamDrives($config)));
            $request->disableGzip();

            /* Create a media file upload to represent our upload process. */
            $media = new WpmfGoogle_Http_MediaFileUpload(
                $client,
                $request,
                $filetype,
                null,
                true,
                $chunkSizeBytes
            );

            $filesize = filesize($filePath);
            $media->setFileSize($filesize);

            /* Start partialy upload
              Upload the various chunks. $status will be false until the process is
              complete. */
            $uploadStatus = false;
            $handle       = fopen($filePath, 'rb');
            while (!$uploadStatus && !feof($handle)) {
                set_time_limit(60);
                $chunk        = fread($handle, $chunkSizeBytes);
                $uploadStatus = $media->nextChunk($chunk);
                if ($action === 'upload_from_library') {
                    if (!empty($uploadStatus)) {
                        return $uploadStatus;
                    }
                }
            }

            fclose($handle);
        } catch (Exception $ex) {
            return false;
        }

        return true;
    }

    /**
     * Get variable
     *
     * @param string $name   Input name
     * @param string $type   Input type
     * @param string $filter Filter
     *
     * @return null
     */
    public function getInput($name, $type = 'GET', $filter = 'cmd')
    {
        $input = null;
        switch (strtoupper($type)) {
            case 'GET':
                // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- No action, nonce is not required
                if (isset($_GET[$name])) {
                    $input = $_GET[$name];
                }
                break;
            case 'POST':
                if (isset($_POST[$name])) {
                    $input = $_POST[$name];
                }
                // phpcs:enable
                break;
            case 'FILES':
                if (isset($_FILES[$name])) {
                    $input = $_FILES[$name];
                }
                break;
            case 'COOKIE':
                if (isset($_COOKIE[$name])) {
                    $input = $_COOKIE[$name];
                }
                break;
            case 'ENV':
                if (isset($_ENV[$name])) {
                    $input = $_ENV[$name];
                }
                break;
            case 'SERVER':
                if (isset($_SERVER[$name])) {
                    $input = $_SERVER[$name];
                }
                break;
            default:
                break;
        }

        switch (strtolower($filter)) {
            case 'cmd':
                $input = preg_replace('/[^a-z\.]+/', '', strtolower($input));
                break;
            case 'int':
                $input = intval($input);
                break;
            case 'bool':
                $input = $input ? 1 : 0;
                break;
            case 'string':
                $input = sanitize_text_field($input);
                break;
            case 'none':
                break;
            default:
                $input = null;
                break;
        }
        return $input;
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
                if ($cloud_type && $cloud_type === 'google_drive') {
                    try {
                        $filePath = get_attached_file($attachment_id);
                        $scaled = WpmfAddonHelper::fixImageOrientation(array('file' => $filePath));
                        $filePath = $scaled['file'];
                        if (file_exists($filePath)) {
                            $info = pathinfo($filePath);

                            $config = get_option('_wpmfAddon_cloud_config');
                            $client = $this->getClient($config);

                            // upload attachment to cloud
                            $uploaded_file = $this->doUploadFile($client, $filePath, $cloud_id, $info['basename'], 'upload_from_library');
                            if ($uploaded_file) {
                                // add attachment meta
                                add_post_meta($attachment_id, 'wpmf_drive_id', $uploaded_file->id);
                                add_post_meta($attachment_id, 'wpmf_drive_type', 'google_drive');
                                unlink($filePath);

                                // add attachment metadata
                                $upload_path = wp_upload_dir();
                                $attached = trim($upload_path['subdir'], '/') . '/' . $uploaded_file->title;

                                $meta = array();
                                if (strpos($uploaded_file->mimeType, 'image') !== false) {
                                    $metadata = $uploaded_file->getImageMediaMetadata();
                                    if (isset($metadata->width) && isset($metadata->height)) {
                                        $meta['width'] = $metadata->width;
                                        $meta['height'] = $metadata->height;
                                    }

                                    $meta['file'] = $attached;
                                }

                                if (isset($uploaded_file->fileSize)) {
                                    $meta['filesize'] = $uploaded_file->fileSize;
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
     * Get link
     *
     * @param string $drive_id Cloud file ID
     *
     * @return boolean|string
     */
    public function getLink($drive_id)
    {
        $config = get_option('_wpmfAddon_cloud_config');
        $client = $this->getClient($config);
        $service     = new WpmfGoogle_Service_Drive($client);
        if (isset($config['link_type']) && $config['link_type'] === 'public') {
            try {
                $userPermission = new WpmfGoogle_Service_Drive_Permission(array(
                    'type' => 'anyone',
                    'role' => 'reader',
                ));
                $service->permissions->create($drive_id, $userPermission, array('fields' => 'id', 'supportsAllDrives' => $this->isTeamDrives($config)));
                $link = 'https://drive.google.com/uc?id=' . $drive_id;
            } catch (Exception $e) {
                $link = false;
            }
        } else {
            $link = admin_url('admin-ajax.php') . '?action=wpmf-download-file&id=' . urlencode($drive_id) . '&link=true&dl=0';
        }

        return $link;
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
            $drive_type = get_post_meta($attachment_id, 'wpmf_drive_type', true);
            if ($drive_type === 'google_drive') {
                // public file
                global $wpdb;
                $link = $this->getLink($drive_id);
                if ($link) {
                    $where = array('ID' => $attachment_id);
                    $wpdb->update($wpdb->posts, array('guid' => $link), $where);
                }
            }

            // update meta data
            $data = get_post_meta($attachment_id, 'wpmf_attachment_metadata', true);
            if (!empty($data)) {
                if (empty($data['width']) && empty($data['height'])) {
                    list($width, $heigth) = wpmfGetImgSize($link);
                    $data['width'] = $width;
                    $data['height'] = $heigth;
                }
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
                if ($cloud_type && $cloud_type === 'google_drive') {
                    $folder = $this->doCreateFolder($name, $cloud_id);
                    add_term_meta($folder_id, 'wpmf_drive_id', $folder->getId());
                    add_term_meta($folder_id, 'wpmf_drive_type', 'google_drive');
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
                if ($cloud_type && $cloud_type === 'google_drive') {
                    $config = get_option('_wpmfAddon_cloud_config');
                    if ($config['googleBaseFolder'] !== $cloud_id) {
                        $client = $this->getClient($config);
                        $service = new WpmfGoogle_Service_Drive($client);
                        $service->files->delete($cloud_id, array('supportsAllDrives' => $this->isTeamDrives($config)));
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
                if ($cloud_type && $cloud_type === 'google_drive') {
                    $config = get_option('_wpmfAddon_cloud_config');
                    if ($config['googleBaseFolder'] !== $cloud_id) {
                        $client = $this->getClient($config);
                        $service = new WpmfGoogle_Service_Drive($client);
                        $file = new WpmfGoogle_Service_Drive_DriveFile();
                        $file->setName($name);
                        $service->files->update($cloud_id, $file, array('supportsAllDrives' => $this->isTeamDrives($config)));
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
                if ($cloud_type && $cloud_type === 'google_drive') {
                    $config = get_option('_wpmfAddon_cloud_config');
                    if ($config['googleBaseFolder'] !== $cloud_id) {
                        $client = $this->getClient($config);
                        $service = new WpmfGoogle_Service_Drive($client);
                        $cloud_parentid = wpmfGetCloudFolderID($parent_id);

                        $file = $service->files->get($cloud_id, array('fields' => 'id,parents', 'supportsAllDrives' => $this->isTeamDrives($config)));

                        $oldParents = $file->getParents();
                        $newFile = new WpmfGoogle_Service_Drive_DriveFile();
                        $service->files->update($cloud_id, $newFile, array(
                            'removeParents' => implode(',', $oldParents),
                            'addParents' => $cloud_parentid,
                            'supportsAllDrives' => $this->isTeamDrives($config)
                        ));
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
                if ($cloud_type && $cloud_type === 'google_drive') {
                    $cloud_parentid = wpmfGetCloudFolderID($parent_id);
                    $config = get_option('_wpmfAddon_cloud_config');
                    $client = $this->getClient($config);
                    $service = new WpmfGoogle_Service_Drive($client);
                    $file = $service->files->get($cloud_id, array('fields' => 'id,parents', 'supportsAllDrives' => $this->isTeamDrives($config)));
                    $oldParents = $file->getParents();
                    $newFile = new WpmfGoogle_Service_Drive_DriveFile();
                    $service->files->update($cloud_id, $newFile, array(
                        'removeParents' => implode(',', $oldParents),
                        'addParents' => $cloud_parentid,
                        'supportsAllDrives' => $this->isTeamDrives($config)
                    ));
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
                if ($cloud_type && $cloud_type === 'google_drive') {
                    $config = get_option('_wpmfAddon_cloud_config');
                    $client = $this->getClient($config);
                    $service = new WpmfGoogle_Service_Drive($client);
                    $service->files->delete($cloud_id, array('supportsAllDrives' => $this->isTeamDrives($config)));
                }
            }
        } catch (Exception $ex) {
            return false;
        }

        return true;
    }

    /**
     * Insert attachment
     *
     * @param array   $info        File info
     * @param object  $child       File details
     * @param integer $parent      Parent folder
     * @param array   $upload_path Upload path
     *
     * @return void
     */
    public function insertAttachment($info, $child, $parent, $upload_path)
    {
        $link = $this->getLink($child->id);
        if (!$link) {
            return;
        }
        $attachment = array(
            'guid'           => $link,
            'post_mime_type' => $child->mimeType,
            'post_title'     => $info['filename'],
            'post_type'     => 'attachment',
            'post_status'    => 'inherit'
        );

        $attach_id   = wp_insert_post($attachment);
        $attached = trim($upload_path['subdir'], '/') . '/' . $child->name;
        wp_set_object_terms((int) $attach_id, (int) $parent, WPMF_TAXO);

        update_post_meta($attach_id, '_wp_attached_file', $attached);
        update_post_meta($attach_id, 'wpmf_size', $child->fileSize);
        update_post_meta($attach_id, 'wpmf_filetype', $info['extension']);
        update_post_meta($attach_id, 'wpmf_order', 0);
        update_post_meta($attach_id, 'wpmf_drive_id', $child->id);
        update_post_meta($attach_id, 'wpmf_drive_type', 'google_drive');

        $meta = array();
        if (strpos($child->mimeType, 'image') !== false) {
            $metadata = $child->getImageMediaMetadata();
            if (isset($metadata->width)) {
                $meta['width'] = $metadata->width;
            }

            if (isset($metadata->height)) {
                $meta['height'] = $metadata->height;
            }

            $meta['file'] = $attached;
        }

        if (isset($child->fileSize)) {
            $meta['filesize'] = $child->fileSize;
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
     * @param object $service       Google Service
     * @param string $folderID      Folder ID
     * @param string $parent        Parent
     * @param array  $allDriveFiles All rrive files
     *
     * @return array
     */
    public function doAutoSyncWithCrontabMethod($service, $folderID, $parent, $allDriveFiles)
    {
        $childs     = array();
        $pageToken  = null;
        $config = get_option('_wpmfAddon_cloud_config');
        do {
            try {
                $params = array(
                    'q'          => "'" . $folderID . "' in parents and trashed = false",
                    'supportsAllDrives' => $this->isTeamDrives($config)
                );

                if ($this->isTeamDrives($config)) {
                    $params['corpora'] = 'drive';
                    $params['driveId'] = $config['googleBaseFolder'];
                    $params['includeItemsFromAllDrives'] = true;
                }

                if ($pageToken) {
                    $params['pageToken'] = $pageToken;
                }

                $files     = $service->files->listFiles($params);
                $childs    = array_merge($childs, $files->getFiles());
                $pageToken = $files->getNextPageToken();
            } catch (Exception $e) {
                print 'An error occurred: ' . esc_html($e->getMessage());
                $pageToken = null;
            }
        } while ($pageToken);

        $upload_path = wp_upload_dir();
        foreach ($childs as $child) {
            if ($child->mimeType !== 'application/vnd.google-apps.folder') {
                $allDriveFiles[$child->id] = $child;
                $info = pathinfo($child->name);
                $args = array(
                    'post_type' => 'attachment',
                    'posts_per_page' => -1,
                    'post_status' => 'any',
                    'meta_query' => array(
                        array(
                            'key' => 'wpmf_drive_id',
                            'value' => $child->id,
                            'compare' => '='
                        )
                    )
                );
                $files = get_posts($args);
                if (empty($files)) {
                    // insert attachment
                    $this->insertAttachment($info, $child, $parent, $upload_path);
                } else {
                    // update attachment
                    foreach ($files as $file) {
                        $this->updateAttachment($info, $file->ID, $parent);
                    }
                }
            } else {
                $allDriveFiles[$child->id] = $child;
                $info = pathinfo($child->name);
                $args = array(
                    'hide_empty' => false,
                    'meta_query' => array(
                        array(
                            'key' => 'wpmf_drive_id',
                            'value' => $child->id,
                            'compare' => '='
                        )
                    ),
                    'taxonomy' => WPMF_TAXO
                );
                $folders = get_terms($args);
                if (empty($folders)) {
                    $inserted = wp_insert_term($child->name, WPMF_TAXO, array('parent' => (int)$parent));
                    if (!is_wp_error($inserted)) {
                        add_term_meta($inserted['term_id'], 'wpmf_drive_id', $child->id);
                        add_term_meta($inserted['term_id'], 'wpmf_drive_type', 'google_drive');
                        $allDriveFiles = $this->doAutoSyncWithCrontabMethod($service, $child->id, $inserted['term_id'], $allDriveFiles);
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
                        $allDriveFiles = $this->doAutoSyncWithCrontabMethod($service, $child->id, $folder->term_id, $allDriveFiles);
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
        $params = get_option('_wpmfAddon_cloud_config');
        if (!empty($params['googleCredentials']) && !empty($params['googleBaseFolder'])) {
            try {
                set_time_limit(0);
                $client = $this->getClient($params);
                $service = new WpmfGoogle_Service_Drive($client);

                $folderID = $params['googleBaseFolder'];
                $inserted = wp_insert_term('Google Drive', WPMF_TAXO, array('parent' => 0));
                if (is_wp_error($inserted)) {
                    $root_id = $inserted->error_data['term_exists'];
                } else {
                    $root_id = $inserted['term_id'];
                }

                $cloud_folder_id = get_term_meta($root_id, 'wpmf_drive_root_id', true);
                if (!empty($cloud_folder_id)) {
                    update_term_meta($root_id, 'wpmf_drive_root_id', $folderID);
                } else {
                    add_term_meta($root_id, 'wpmf_drive_root_id', $folderID);
                }

                update_option('wpmf_google_folder_id', $root_id);
                $allDriveFiles = $this->doAutoSyncWithCrontabMethod($service, $folderID, $root_id, array());
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
