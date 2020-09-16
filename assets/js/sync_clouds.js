var wpmfOneDriveBusinessModuleAutoSync, wpmfDropboxModuleAutoSync, wpmfGoogleDriveModuleAutoSync, wpmfOneDriveModuleAutoSync, wpmfCloudSyncCookie;
var cloud_syncing = false;
var wpmf_cloud_last_sync;
var cloud_sync_icon;
(function ($) {
    cloud_sync_icon = `<span title="${wpmfAutoSyncClouds.l18n.hover_cloud_syncing}" class="wpmf-loading-sync"><svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid" class="lds-dual-ring" style="
    height: 14px;
    width: 14px;
    vertical-align: sub;
"><circle cx="50" cy="50" ng-attr-r="{{config.radius}}" ng-attr-stroke-width="{{config.width}}" ng-attr-stroke="{{config.stroke}}" ng-attr-stroke-dasharray="{{config.dasharray}}" fill="none" stroke-linecap="round" r="40" stroke-width="12" stroke="#2196f3" stroke-dasharray="62.83185307179586 62.83185307179586" transform="rotate(53.6184 50 50)"><animateTransform attributeName="transform" type="rotate" calcMode="linear" values="0 50 50;360 50 50" keyTimes="0;1" dur="1s" begin="0s" repeatCount="indefinite"></animateTransform></circle></svg></span>`;
    wpmfDropboxModuleAutoSync = {
        /**
         * Sync files from Dropbox to Media library
         *
         * @param first_sync
         */
        syncFilesToMedia: function (first_sync = false) {
            $.ajax({
                method: "POST",
                dataType: "json",
                url: ajaxurl,
                data: {
                    action: 'wpmf_dropbox_sync_files',
                    wpmf_nonce: wpmfAutoSyncClouds.vars.wpmf_nonce
                },
                success: function (response) {
                    if (response.status) {
                        if (response.continue) {
                            wpmfDropboxModuleAutoSync.syncFilesToMedia(first_sync);
                        } else {
                            if (first_sync) {
                                let $snack = wpmfSnackbarModule.getFromId('sync_all_clouds');
                                wpmfSnackbarModule.close($snack);
                            } else {
                                $('.dropbox_list > a > .wpmf-loading-sync').remove();
                                wpmfGoogleDriveModuleAutoSync.syncFoldersToMedia();
                            }
                        }
                    }
                }
            });
        },

        /**
         * Sync the folders from Dropbox to Media library
         *
         * @param first_sync
         * @param sync_token
         */
        syncFoldersToMedia: function (first_sync = false, sync_token = false) {
            $.ajax({
                method: "POST",
                dataType: "json",
                url: ajaxurl,
                data: {
                    action: 'wpmf_dropbox_sync_folders',
                    type: 'auto',
                    sync_token: sync_token,
                    wpmf_nonce: wpmfAutoSyncClouds.vars.wpmf_nonce
                },
                beforeSend: function () {
                    if (first_sync) {
                        if (!$('.wpmf-snackbar[data-id="sync_all_clouds"]').length) {
                            wpmfSnackbarModule.show({
                                id: 'sync_all_clouds',
                                content: wpmfoption.l18n.sync_all_clouds_notice,
                                auto_close: false,
                                is_progress: true
                            });
                        }
                    } else {
                        $('.spinner-cloud-sync').show();
                    }

                    if (!$('.dropbox_list > a > .wpmf-loading-sync').length) {
                        $('.dropbox_list > a').append(cloud_sync_icon);
                    }
                },
                success: function (response) {
                    if (response.status) {
                        if (response.continue) {
                            wpmfDropboxModuleAutoSync.syncFoldersToMedia(first_sync, sync_token);
                        } else {
                            wpmfDropboxModuleAutoSync.syncFilesToMedia(first_sync);
                        }
                    } else {
                        if (!first_sync) {
                            $('.dropbox_list > a > .wpmf-loading-sync').remove();
                            if (typeof response.continue !== "undefined" && !response.continue) {
                                return;
                            }
                            wpmfGoogleDriveModuleAutoSync.syncFoldersToMedia();
                        }
                    }
                },
                error: function () {
                    wpmfDropboxModuleAutoSync.syncFoldersToMedia(sync_token);
                }
            });
        }
    };

    wpmfGoogleDriveModuleAutoSync = {
        /**
         * Sync files from Google Drive to Media library
         */
        syncFilesToMedia: function () {
            $.ajax({
                method: "POST",
                dataType: "json",
                url: ajaxurl,
                data: {
                    action: 'wpmf_google_sync_files',
                    wpmf_nonce: wpmfAutoSyncClouds.vars.wpmf_nonce
                },
                success: function (response) {
                    if (response.status) {
                        if (response.continue) {
                            wpmfGoogleDriveModuleAutoSync.syncFilesToMedia();
                        } else {
                            $('.google_drive_list > a > .wpmf-loading-sync').remove();
                            wpmfOneDriveModuleAutoSync.syncFoldersToMedia();
                        }
                    }
                }
            });
        },

        /**
         * Sync the folders from Google Drive to Media library
         */
        syncFoldersToMedia: function () {
            $.ajax({
                method: "POST",
                dataType: "json",
                url: ajaxurl,
                data: {
                    action: 'wpmf_google_sync_folders',
                    type: 'auto',
                    wpmf_nonce: wpmfAutoSyncClouds.vars.wpmf_nonce
                },
                beforeSend: function () {
                    $('.spinner-cloud-sync').show();
                    if (!$('.google_drive_list > a > .wpmf-loading-sync').length) {
                        $('.google_drive_list > a').append(cloud_sync_icon);
                    }
                },
                success: function (response) {
                    if (response.status) {
                        if (response.continue) {
                            wpmfGoogleDriveModuleAutoSync.syncFoldersToMedia();
                        } else {
                            wpmfGoogleDriveModuleAutoSync.syncFilesToMedia();
                        }
                    } else {
                        $('.google_drive_list > a > .wpmf-loading-sync').remove();
                        wpmfOneDriveModuleAutoSync.syncFoldersToMedia();
                    }
                },
                error: function () {
                    wpmfGoogleDriveModuleAutoSync.syncFoldersToMedia();
                }
            });
        }
    };

    wpmfOneDriveModuleAutoSync = {
        /**
         * Sync files from OneDrive to Media library
         */
        syncFilesToMedia: function () {
            $.ajax({
                method: "POST",
                dataType: "json",
                url: ajaxurl,
                data: {
                    action: 'wpmf_onedrive_sync_files',
                    wpmf_nonce: wpmfAutoSyncClouds.vars.wpmf_nonce
                },
                success: function (response) {
                    if (response.status) {
                        if (response.continue) {
                            wpmfOneDriveModuleAutoSync.syncFilesToMedia();
                        } else {
                            $('.onedrive_list > a > .wpmf-loading-sync').remove();
                            wpmfOneDriveBusinessModuleAutoSync.syncFoldersToMedia();
                        }
                    }
                }
            });
        },

        /**
         * Sync the folders from OneDrive to Media library
         */
        syncFoldersToMedia: function () {
            $.ajax({
                method: "POST",
                dataType: "json",
                url: ajaxurl,
                data: {
                    action: 'wpmf_onedrive_sync_folders',
                    type: 'auto',
                    wpmf_nonce: wpmfAutoSyncClouds.vars.wpmf_nonce
                },
                beforeSend: function () {
                    $('.spinner-cloud-sync').show();
                    if (!$('.onedrive_list > a > .wpmf-loading-sync').length) {
                        $('.onedrive_list > a').append(cloud_sync_icon);
                    }
                },
                success: function (response) {
                    if (response.status) {
                        if (response.continue) {
                            wpmfOneDriveModuleAutoSync.syncFoldersToMedia();
                        } else {
                            wpmfOneDriveModuleAutoSync.syncFilesToMedia();
                        }
                    } else {
                        $('.onedrive_list > a > .wpmf-loading-sync').remove();
                        wpmfOneDriveBusinessModuleAutoSync.syncFoldersToMedia();
                    }
                },
                error: function () {
                    wpmfOneDriveModuleAutoSync.syncFoldersToMedia();
                }
            });
        }
    };

    wpmfOneDriveBusinessModuleAutoSync = {
        /**
         * Sync files from OneDrive Business to Media library
         */
        syncFilesToMedia: function () {
            $.ajax({
                method: "POST",
                dataType: "json",
                url: ajaxurl,
                data: {
                    action: 'wpmf_odvbs_sync_files',
                    wpmf_nonce: wpmfAutoSyncClouds.vars.wpmf_nonce
                },
                success: function (response) {
                    if (response.status) {
                        if (response.continue) {
                            wpmfOneDriveBusinessModuleAutoSync.syncFilesToMedia();
                        } else {
                            $.ajax({
                                method: "POST",
                                dataType: "json",
                                url: ajaxurl,
                                data: {
                                    action: 'wpmf_update_cloud_last_sync',
                                    wpmf_nonce: wpmfAutoSyncClouds.vars.wpmf_nonce
                                },
                                success: function (res) {
                                    cloud_syncing = false;
                                    wpmf_cloud_last_sync = res.time;
                                    $('.onedrive_business_list > a > .wpmf-loading-sync').remove();
                                }
                            });
                        }
                    }
                }
            });
        },

        /**
         * Sync the folders from OneDrive Business to Media library
         */
        syncFoldersToMedia: function () {
            $.ajax({
                method: "POST",
                dataType: "json",
                url: ajaxurl,
                data: {
                    action: 'wpmf_odvbs_sync_folders',
                    type: 'auto',
                    wpmf_nonce: wpmfAutoSyncClouds.vars.wpmf_nonce
                },
                beforeSend: function () {
                    $('.spinner-cloud-sync').show();
                    if (!$('.onedrive_business_list > a > .wpmf-loading-sync').length) {
                        $('.onedrive_business_list > a').append(cloud_sync_icon);
                    }
                },
                success: function (response) {
                    if (response.status) {
                        if (response.continue) {
                            wpmfOneDriveBusinessModuleAutoSync.syncFoldersToMedia();
                        } else {
                            wpmfOneDriveBusinessModuleAutoSync.syncFilesToMedia();
                        }
                    } else {
                        $.ajax({
                            method: "POST",
                            dataType: "json",
                            url: ajaxurl,
                            data: {
                                action: 'wpmf_update_cloud_last_sync',
                                wpmf_nonce: wpmfAutoSyncClouds.vars.wpmf_nonce
                            },
                            success: function (res) {
                                cloud_syncing = false;
                                wpmf_cloud_last_sync = res.time;
                            }
                        });
                    }
                },
                error: function () {
                    wpmfOneDriveBusinessModuleAutoSync.syncFoldersToMedia();
                }
            });
        }
    };

    wpmfCloudSyncCookie = {
        /**
         * set a cookie
         * @param cname cookie name
         * @param cvalue cookie value
         * @param exdays
         */
        setCookie: function (cname, cvalue, exdays) {
            let d = new Date();
            d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
            let expires = "expires=" + d.toUTCString();
            document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
        },
    };

    jQuery(document).ready(function ($) {
        var sync_token = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        if (typeof wpmf !== "undefined") {
            if (wpmf.vars.wpmf_pagenow === 'options-general.php' && wpmf.vars.base === 'settings_page_option-folder') {
                if ($('.cloud_first_connect').length) {
                    var first = $('.cloud_first_connect').val();
                    if (parseInt(first) === 1) {
                        wpmfDropboxModuleAutoSync.syncFoldersToMedia(true);
                    }
                }
            }
        }

        $('.btn-run-sync-cloud').on('click', function () {
            wpmfDropboxModuleAutoSync.syncFoldersToMedia();
        });

        wpmf_cloud_last_sync = wpmfAutoSyncClouds.vars.last_sync;
        if ((wpmfAutoSyncClouds.vars.sync_method === 'ajax' || wpmfAutoSyncClouds.vars.sync_method === 'curl') && parseInt(wpmfAutoSyncClouds.vars.sync_periodicity) !== 0) {
            setInterval(function () {
                var $snack = wpmfSnackbarModule.getFromId('sync_drive');
                if ($snack !== null && $snack.length) {
                    return;
                }
                var now = Math.floor(Date.now() / 1000);
                if (now - wpmfAutoSyncClouds.vars.sync_periodicity >= wpmf_cloud_last_sync) {
                    if (wpmfAutoSyncClouds.vars.sync_method === 'ajax') {
                        if (!cloud_syncing) {
                            cloud_syncing = true;
                            wpmfDropboxModuleAutoSync.syncFoldersToMedia(false, sync_token);
                        }
                    }

                    if (wpmfAutoSyncClouds.vars.sync_method === 'curl') {
                        $.ajax({
                            method: "POST",
                            dataType: "json",
                            url: ajaxurl,
                            data: {
                                action: 'wpmf_sync_cloud_curl',
                                wpmf_nonce: wpmfAutoSyncClouds.vars.wpmf_nonce
                            },
                            success: function (res) {
                                wpmf_cloud_last_sync = res.time;
                            }
                        });
                    }
                }
            },60000);
        }
    });
})(jQuery);