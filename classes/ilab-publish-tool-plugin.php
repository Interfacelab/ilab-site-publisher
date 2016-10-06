<?php

global $ilab_publish_version;
$ilab_publish_version= '0.4';

require_once('ilab-publish-tool-hooks.php');
require_once('ilab-publish-activity-table.php');
require_once('ilab-publish-history-table.php');

class ILabPublishingToolPlugin {
    private $hooks;

    public function __construct() {
        $this->hooks=new ILabPublishToolHooks($this);
        add_action('admin_menu', [$this,'create_admin_menu']);

        add_action('plugins_loaded',function(){
            if (current_user_can('publish_live')) {
                if (get_option('ilab-publish-state')=='needs') {
                    add_action('admin_notices',function()  {
                        echo ILabPublishView::render_view('message.php',[
                            'type'=>'notice updated is-dismissible',
                            'message'=>'The site has been changed and needs to be published.  <a href="/wp/wp-admin/admin.php?page=site-publisher-top">Click here to publish.</a>'
                        ]);
                    });
                }
            }
        });



        add_action( 'wp_ajax_ilab_publish_live', [$this,'publishLive']);
    }

    public function create_admin_menu()
    {
        add_menu_page('Settings', 'Site Publisher', 'publish_live', 'site-publisher-top', [$this,'renderPublish'],'dashicons-welcome-view-site');
        add_submenu_page( 'site-publisher-top', 'Site Publisher', 'Publish', 'publish_live', 'site-publisher-top', [$this,'renderPublish']);
        add_submenu_page( 'site-publisher-top', 'Site Publisher Activity Log', 'Activity Log', 'publish_live', 'site-publisher-activity', [$this,'renderActivityLog']);
        add_submenu_page( 'site-publisher-top', 'Site Publisher History', 'Publish History', 'publish_live', 'site-publisher-history', [$this,'renderPublishHistory']);
        add_submenu_page( 'site-publisher-top', 'Site Publisher Settings', 'Settings', 'publish_live', 'site-publisher-settings', [$this,'renderSettings']);
        add_action( 'admin_init', [$this,'register_plugin_settings'] );
    }

    public function register_plugin_settings() {
        register_setting('ilab-publish-group','ilab-publish-current-version');
        register_setting('ilab-publish-group','ilab-publish-current-theme-version');
        register_setting('ilab-publish-group','ilab-publish-last-version');
        register_setting('ilab-publish-group','ilab-publish-state');
        register_setting('ilab-publish-group','ilab-publish-cache-servers');
        register_setting('ilab-publish-group','ilab-publish-cache-secret');
        register_setting('ilab-publish-group','ilab-publish-mode');
        register_setting('ilab-publish-group','ilab-publish-cloudflare-email');
        register_setting('ilab-publish-group','ilab-publish-cloudflare-key');
        register_setting('ilab-publish-group','ilab-publish-cloudflare-domain');


        add_settings_section('ilab-publish-mode-settings','Publish Mode',null,'site-publisher-settings');
        $this->registerSelectSetting('ilab-publish-mode',['cloudflare' => 'CloudFlare', 'varnish' => 'Varnish'], 'Publishing Mode','ilab-publish-mode-settings');

        add_settings_section('ilab-publish-cloudflare-settings','Cloudflare Settings',null,'site-publisher-settings');
        $this->registerTextFieldSetting('ilab-publish-cloudflare-email','CloudFlare Email','ilab-publish-cloudflare-settings');
        $this->registerTextFieldSetting('ilab-publish-cloudflare-domain','CloudFlare Domain','ilab-publish-cloudflare-settings');
        $this->registerPasswordFieldSetting('ilab-publish-cloudflare-key','CloudFlare Key','ilab-publish-cloudflare-settings');

        add_settings_section('ilab-publish-server-settings','Server Settings',null,'site-publisher-settings');
        $this->registerTextAreaFieldSetting('ilab-publish-cache-servers','Varnish Servers','ilab-publish-server-settings');
        $this->registerPasswordFieldSetting('ilab-publish-cache-secret','Varnish Secret','ilab-publish-server-settings');

        if (!get_option('ilab-publish-state'))
            update_option('ilab-publish-state','needs');
        if (!get_option('ilab-publish-current-version'))
            update_option('ilab-publish-current-version',date('YmdHis'));
        if (!get_option('ilab-publish-current-theme-version'))
            update_option('ilab-publish-current-theme-version',date('YmdHis'));

        $size_names=get_intermediate_image_sizes();

        foreach($size_names as $name)
            register_setting('ilab-image-editor-group','ilab-size-'.$name.'-keep-gif');
    }

    public static function install() {
        global $wpdb;
        global $ilab_publish_version;

        $installed_ver = get_option( "ilab_publish_version" );

        if ( $installed_ver != $ilab_publish_version ) {

            $activitylog = $wpdb->prefix . 'ilab_publish_activity';

            $activitylog_sql = "CREATE TABLE $activitylog
            (
  id BIGINT UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
  version varchar(64) not null,
  date_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
  object_id bigint unsigned,
  post_id bigint unsigned,
  user_id bigint unsigned not null,
  user_name varchar(255) not null,
  activity varchar(255),
  transition_from varchar(255),
  transition_to varchar(255),
  post_title varchar(255),
  post_type varchar(255),
  post_name varchar(255)
);";
            $history = $wpdb->prefix . 'ilab_publish_history';

            $history_sql = "CREATE TABLE $history
            (
  id BIGINT UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
  version varchar(64) not null,
  date_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
  user_id bigint unsigned not null,
  user_name varchar(255) not null,
  status varchar(64) not null
);";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $activitylog_sql );
            dbDelta( $history_sql );

            update_option( "ilab_publish_version", $ilab_publish_version );
        }
    }

    public static function uninstall() {

    }

    public function siteStateChanged($activity, $post_id=null, $object_id=null, $transition_from=null, $transition_to=null) {
        global $wpdb;

        if ($transition_from && !empty($transition_from) && $transition_to && !empty($transition_to)) {
            if (($transition_from=='publish') || ($transition_to=='publish'))
            {
                if (($transition_from=='publish') && ($transition_to=='publish'))
                    $activity='Post updated';
                else if (($transition_from=='publish') && ($transition_to=='trash'))
                    $activity='Post moved to trash';
                else if (($transition_from=='trash') && ($transition_to=='publish'))
                    $activity='Post removed from trash';
                else if (($transition_from=='draft') && ($transition_to=='publish'))
                    $activity='Post published';
                else if (($transition_from=='publish') && ($transition_to=='draft'))
                    $activity='Post unpublished';
                else if (($transition_from=='pending') && ($transition_to=='publish'))
                    $activity='Pending post published';
                else if (($transition_from=='publish') && ($transition_to=='pending'))
                    $activity='Post changed to pending.';
            }
            else
                return;
        }


        $post_title=null;
        $post_name=null;
        $post_type=null;

        if ($post_id) {
            $post=WP_Post::get_instance($post_id);
            if ($post) {
                $post_title=$post->post_title;
                $post_type=$post->post_type;
                $post_name=$post->post_name;
            }
        }

        $user=wp_get_current_user();
        $version=get_option('ilab-publish-current-version');

        $wpdb->insert($wpdb->prefix . 'ilab_publish_activity',[
            'version'=>$version,
            'user_id'=>$user->ID,
            'user_name'=>$user->display_name,
            'post_id'=>$post_id,
            'object_id'=>$object_id,
            'activity'=>$activity,
            'transition_from'=>$transition_from,
            'transition_to'=>$transition_to,
            'post_title'=>$post_title,
            'post_name'=>$post_name,
            'post_type'=>$post_type
        ]);

        update_option('ilab-publish-state','needs');
    }

    public function renderSettings() {
        echo ILabPublishView::render_view('settings.php',[
            'title'=>'Publish Settings',
            'group'=>'ilab-publish-group',
            'page'=>'site-publisher-settings'
        ]);
    }

    public function renderPublish() {
        echo ILabPublishView::render_view('publish.php',[]);
    }

    public function renderActivityLog() {
        echo ILabPublishView::render_view('activity.php',[]);
    }

    public function renderPublishHistory() {
        echo ILabPublishView::render_view('history.php',[]);
    }


    protected function registerTextFieldSetting($option_name,$title,$settings_slug,$description=null,$placeholder=null)
    {
        add_settings_field($option_name,$title,[$this,'renderTextFieldSetting'],'site-publisher-settings',$settings_slug,['option'=>$option_name,'description'=>$description, 'placeholder' => $placeholder]);

    }

    public function renderTextFieldSetting($args)
    {
        $value=get_option($args['option']);
        echo "<input size='40' type=\"text\" name=\"{$args['option']}\" value=\"$value\" placeholder=\"{$args['placeholder']}\">";
        if ($args['description'])
            echo "<p class='description'>".$args['description']."</p>";
    }

    protected function registerTextAreaFieldSetting($option_name,$title,$settings_slug,$description=null)
    {
        add_settings_field($option_name,$title,[$this,'renderTextAreaFieldSetting'],'site-publisher-settings',$settings_slug,['option'=>$option_name,'description'=>$description]);

    }

    protected function registerSelectSetting($option_name,$options,$title,$settings_slug,$description=null)
    {
        add_settings_field($option_name,$title,[$this,'renderSelectSetting'],'site-publisher-settings',$settings_slug,['option'=>$option_name,'description'=>$description, 'options' => $options]);
    }

    public function renderSelectSetting($args)
    {
        $option = $args['option'];
        $options = $args['options'];

        $value=get_option($args['option']);

        echo "<select name=\"{$option}\">\n";
        foreach($options as $val => $name) {
            $opt = "\t<option value=\"{$val}\"";
            if ($val == $value)
                $opt .= " selected";
            $opt .= ">{$name}</option>\n";

            echo $opt;
        }
        echo "</select>\n";

        if ($args['description'])
            echo "<p class='description'>".$args['description']."</p>";
    }

    public function renderTextAreaFieldSetting($args)
    {
        $value=get_option($args['option']);
        echo "<textarea cols='40' rows='4' name=\"{$args['option']}\">$value</textarea>";
        if ($args['description'])
            echo "<p class='description'>".$args['description']."</p>";
    }

    protected function registerPasswordFieldSetting($option_name,$title,$settings_slug,$description=null)
    {
        add_settings_field($option_name,$title,[$this,'renderPasswordFieldSetting'],'site-publisher-settings',$settings_slug,['option'=>$option_name,'description'=>$description]);
    }

    public function renderPasswordFieldSetting($args)
    {
        $value=get_option($args['option']);
        echo "<input size='40' type=\"password\" name=\"{$args['option']}\" value=\"$value\">";
        if ($args['description'])
            echo "<p class='description'>".$args['description']."</p>";
    }

    private function logPublishStatus($status) {
        global $wpdb;
        $user=wp_get_current_user();
        $version=get_option('ilab-publish-current-version');

        $wpdb->insert($wpdb->prefix . 'ilab_publish_history',[
            'version'=>$version,
            'user_id'=>$user->ID,
            'user_name'=>$user->display_name,
            'status'=>$status
        ]);
    }

    private function purgeVarnish() {

        $purgeServers=get_option('ilab-publish-cache-servers');
        if (!$purgeServers || empty($purgeServers)) {
            $this->logPublishStatus("Missing server settings.");

            echo ILabPublishView::render_view('message.php',[
                'type'=>'error',
                'message'=>'Could not publish, missing cache server settings.'
            ]);

            wp_die();
        }

        error_log("[Publish] Starting purge ...");
        $purgeList=explode("\n",$purgeServers);
        foreach($purgeList as $server) {
            $server=trim($server);
            $purgeme="http://$server/";

            error_log("[Publish] Purging: $purgeme");
            return wp_remote_request($purgeme, array('method' => 'BAN', 'headers' => array( 'X-BAN' => '/' ) ) );
        }
    }

    private function purgeCloudflare() {
        $email = get_option('ilab-publish-cloudflare-email');
        $key = get_option('ilab-publish-cloudflare-key');
        $domain = get_option('ilab-publish-cloudflare-domain');

        if (!$email || !$key || !$domain) {
            $this->logPublishStatus("Cloudflare Error: Missing settings");
            echo ILabPublishView::render_view('message.php',[
                'type'=>'error',
                'message'=>"Purge unsuccessful because your Cloudflare settings are missing.   Please enter the correct settings and try again."
            ]);
            wp_die();
        }

        $id = null;

        $zone = new \Cloudflare\Zone($email, $key);
        $results = $zone->zones();
        if ($results->result && is_array($results->result)) {
            foreach($results->result as $zone) {
                if ($zone->name == $domain) {
                    $id = $zone->id;
                    break;
                }
            }
        }

        if (!$id) {
            $this->logPublishStatus("Cloudflare Error: Invalid domain");
            echo ILabPublishView::render_view('message.php',[
                'type'=>'error',
                'message'=>"Purge unsuccessful because your Cloudflare settings are missing.   The domain could not be found."
            ]);
            wp_die();
        }

        $cache = new \Cloudflare\Zone\Cache($email, $key);
        $result=$cache->purge($id, true);
        if ($result->success != 1) {
            $error=$result->errors[0];
            $this->logPublishStatus("Cloudflare Error: {$error->message}");
            echo ILabPublishView::render_view('message.php',[
                'type'=>'error',
                'message'=>"Purge unsuccessful because of a Cloudflare error: {$error->message}"
            ]);
            wp_die();
        }
    }

    public function publishLive() {

        if (!current_user_can('publish_live'))
            die;

        if ($_POST['theme']==1) {

            $newversion=date('YmdHis');
            update_option('ilab-publish-current-theme-version',$newversion);
            sleep(10);
        }

        $mode = get_option('ilab-publish-mode', 'varnish');
        if ($mode == 'varnish')
            $this->purgeVarnish();
        else if ($mode=='cloudflare')
            $this->purgeCloudflare();

        $newversion=date('YmdHis');

        update_option('ilab-publish-current-version',$newversion);
        update_option('ilab-publish-state','ok');
        delete_option('ilab_publisher_publishing_status');

        echo ILabPublishView::render_view('message.php',[
            'type'=>'updated',
            'message'=>"Publish was successful."
        ]);

        update_option('ilab-publish-last-published',date(DATE_RFC2822));

        $this->logPublishStatus("Success.");
        error_log("[Publish] Finished.");

        wp_die();
    }
}