<?php
/*
Plugin Name: ILab Site Publisher
Plugin URI: https://github.com/jawngee/ILAB-Site-Publisher
Description: Publishing Tool
Author: Jon Gilkison
Version: 0.1
Author URI: http://interfacelab.com
*/


require_once('vendor/autoload.php');

define('ILAB_PUBLISH_TOOLS_DIR',dirname(__FILE__));
define('ILAB_PUBLISH_VIEW_DIR',ILAB_PUBLISH_TOOLS_DIR.'/views');

require_once('helpers/ilab-publish-view.php');
require_once('classes/ilab-publish-tool-plugin.php');

$plugin=new ILabPublishingToolPlugin();

register_activation_hook(__FILE__,['ILabPublishingToolPlugin','install']);
register_uninstall_hook(__FILE__,['ILabPublishingToolPlugin','uninstall']);