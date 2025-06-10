<?php

/*
 * Galerio - Build Admin Control Panel
 */


add_action('admin_menu', 'galerio_build_ctrl_panel');


function galerio_build_ctrl_panel()
{
    add_menu_page(
        'Galerio Options',
        'Galerio',
        'manage_options',
        plugin_dir_path(__FILE__) . 'galerio-controlpanel.php'
    );
}
