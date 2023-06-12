<?php

/*
 * Art CMS - Build Admin Control Panel
 */


add_action('admin_menu', 'artcms_build_ctrl_panel');


function artcms_build_ctrl_panel()
{
    add_menu_page(
        'Gallery CMS Options',
        'Gallery CMS',
        'manage_options',
        plugin_dir_path(__FILE__) . 'artcms-controlpanel.php'
    );
}
