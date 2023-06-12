<?php

/*
 * Gallery CMS - Define datatypes (- custom page types, taxonomies & metaboxes).
 */



add_action( 'init', 'artcms_init' );
add_action( 'add_meta_boxes', 'artcms_add_meta' );
add_action( 'save_post', 'artcms_save_meta' );



function artcms_init()
{
    artcms_register_post_types();
    artcms_register_taxonomies();
    // add column sorting?
}








function artcms_add_meta()
{


    add_meta_box( 
        'artcms_profile_details', 
        'Details', 
        'artcms_profile_details_callback', 
        [ 'artcms_profile' ], 
        'side', 
        'high' 
    );



    add_meta_box( 
        'artcms_work_meta', 
        'Meta', 
        'artcms_work_meta_callback', 
        [ 'artcms_work' ], 
        'side', 
        'high' 
    );



    add_meta_box( 
        'artcms_collection_info', 
        'Collection info', 
        'artcms_collection_info_callback', 
        [ 'artcms_collection' ], 
        'side', 
        'high' 
    );
    add_meta_box( 
        'artcms_collection_contributors', 
        'Featured Profiles', 
        'artcms_collection_contributors_callback', 
        [ 'artcms_collection' ], 
        'normal' 
    );


}





function artcms_save_meta( $post )
{

}








function artcms_register_post_types()
{


    $profilelabels = array(
        'name'               => __( 'Profiles', 'artcms' ),
        'singular_name'      => __( 'Profile', 'artcms' ),
        'add_new'            => __( 'New Profile', 'artcms' ),
        'add_new_item'       => __( 'Add New Profile', 'artcms' ),
        'edit_item'          => __( 'Edit Profile', 'artcms' ),
        'item_updated'       => __( 'Profile updated', 'artcms' ),
        'new_item'           => __( 'New Profile', 'artcms' ),
        'all_items'          => __( 'All Profiles', 'artcms' ),
        'view_item'          => __( 'View Profile', 'artcms' ),
        'view_items'         => __( 'View Profiles', 'artcms' ),
        'search_items'       => __( 'Search Profiles', 'artcms' ),
        'not_found'          => __( 'No Profiles found', 'artcms' ),
        'not_found_in_trash' => __( 'No Profiles found in Trash', 'artcms' ),
    );
    $profileargs = array(
        'labels'       => $profilelabels,
        'menu_icon'    => 'dashicons-id-alt',
        'has_archive'  => true,
        'public'       => true,
        'hierarchical' => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'supports'     => array(
            'title',
            'editor',
            'excerpt',
            'revisions',
            'custom-fields',
            'thumbnail',
            'page-attributes',
            'post-formats'
        ),
        'rewrite'      => array( 'slug' => 'profile' ),
        'show_in_rest' => true
    );

    register_post_type( 'artcms_profile', $profileargs );





    $worklabels = array(
        'name'               => __( 'Works', 'artcms' ),
        'singular_name'      => __( 'Work', 'artcms' ),
        'add_new'            => __( 'New Work', 'artcms' ),
        'add_new_item'       => __( 'Add New Work', 'artcms' ),
        'edit_item'          => __( 'Edit Work', 'artcms' ),
        'item_updated'       => __( 'Work updated', 'artcms' ),
        'new_item'           => __( 'New Work', 'artcms' ),
        'all_items'          => __( 'All Works', 'artcms' ),
        'view_item'          => __( 'View Work', 'artcms' ),
        'view_items'         => __( 'View Works', 'artcms' ),
        'search_items'       => __( 'Search Works', 'artcms' ),
        'not_found'          => __( 'No Works found', 'artcms' ),
        'not_found_in_trash' => __( 'No Works found in Trash', 'artcms' ),
    );
    $workargs = array(
        'labels'       => $worklabels,
        'menu_icon'    => 'dashicons-art',
        'has_archive'  => true,
        'public'       => true,
        'hierarchical' => true,
        'show_ui'      => true,
        'show_in_menu' => true,
        'supports'     => array(
            'title',
            'editor',
            'excerpt',
            'custom-fields',
            'thumbnail',
            'page-attributes',
            'post-formats'
        ),
        'rewrite'      => array( 'slug' => 'work' ),
        'show_in_rest' => true
    );

    register_post_type( 'artcms_work', $workargs );





    $collectionlabels = array(
        'name'               => __( 'Collections', 'artcms' ),
        'singular_name'      => __( 'Collection', 'artcms' ),
        'add_new'            => __( 'New Collection', 'artcms' ),
        'add_new_item'       => __( 'Add New Collection', 'artcms' ),
        'edit_item'          => __( 'Edit Collection', 'artcms' ),
        'item_updated'       => __( 'Collection updated', 'artcms' ),
        'new_item'           => __( 'New Collection', 'artcms' ),
        'all_items'          => __( 'All Collections', 'artcms' ),
        'view_item'          => __( 'View Collection', 'artcms' ),
        'view_items'         => __( 'View Collections', 'artcms' ),
        'search_items'       => __( 'Search Collections', 'artcms' ),
        'not_found'          => __( 'No Collections found', 'artcms' ),
        'not_found_in_trash' => __( 'No Collections found in Trash', 'artcms' ),
    );
    $collectionargs = array(
        'labels'       => $collectionlabels,
        'menu_icon'    => 'dashicons-layout',
        'has_archive'  => true,
        'public'       => true,
        'hierarchical' => true,
        'show_ui'      => true,
        'show_in_menu' => true,
        'supports'     => array(
            'title',
            'editor',
            'excerpt',
            'custom-fields',
            'thumbnail',
            'page-attributes',
            'post-formats'
        ),
        'rewrite' => array( 'slug' => 'collection' ),
        'show_in_rest' => true
    );

    register_post_type( 'artcms_collection', $collectionargs );


}








function artcms_register_taxonomies()
{


    $grouplabels = array(
        'name'              => _x( 'Groups', 'taxonomy general name', 'artcms' ),
        'singular_name'     => _x( 'Group', 'taxonomy singular name', 'artcms' ),
        'search_items'      => __( 'Search Groups', 'artcms' ),
        'all_items'         => __( 'All Groups', 'artcms' ),
        'parent_item'       => __( 'Parent Group', 'artcms' ),
        'parent_item_colon' => __( 'Parent Group:', 'artcms' ),
        'edit_item'         => __( 'Edit Group', 'artcms' ),
        'update_item'       => __( 'Update Group', 'artcms' ),
        'add_new_item'      => __( 'Add new Group', 'artcms' ),
        'new_item_name'     => __( 'New Group name', 'artcms' ),
        'menu_name'         => __( 'Groups', 'artcms' ),
    );
    $groupargs = array(
        'hierarchical'      => true,
        'labels'            => $grouplabels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'group' ],
        'show_in_rest'      => true
    );

    register_taxonomy( 'artcms_group', [ 'artcms_profile', 'artcms_collection' ], $groupargs );





    $typelabels = array(
        'name'              => _x( 'Types', 'taxonomy general name', 'artcms' ),
        'singular_name'     => _x( 'Type', 'taxonomy singular name', 'artcms' ),
        'search_items'      => __( 'Search Types', 'artcms' ),
        'all_items'         => __( 'All Types', 'artcms' ),
        'parent_item'       => __( 'Parent Type', 'artcms' ),
        'parent_item_colon' => __( 'Parent Type:', 'artcms' ),
        'edit_item'         => __( 'Edit Type', 'artcms' ),
        'update_item'       => __( 'Update Type', 'artcms' ),
        'add_new_item'      => __( 'Add new Type', 'artcms' ),
        'new_item_name'     => __( 'New Type name', 'artcms' ),
        'menu_name'         => __( 'Types', 'artcms' ),
    );
    $typeargs = array(
        'hierarchical'      => true,
        'labels'            => $typelabels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'type' ],
        'show_in_rest'      => true
    );

    register_taxonomy( 'artcms_type', [ 'artcms_work' ], $typeargs );





    $labellabels = array(
        'name'              => _x( 'Labels', 'taxonomy general name', 'artcms' ),
        'singular_name'     => _x( 'Label', 'taxonomy singular name', 'artcms' ),
        'search_items'      => __( 'Search Labels', 'artcms' ),
        'all_items'         => __( 'All Labels', 'artcms' ),
        'parent_item'       => __( 'Parent Label', 'artcms' ),
        'parent_item_colon' => __( 'Parent Label:', 'artcms' ),
        'edit_item'         => __( 'Edit Label', 'artcms' ),
        'update_item'       => __( 'Update Label', 'artcms' ),
        'add_new_item'      => __( 'Add new Label', 'artcms' ),
        'new_item_name'     => __( 'New Label name', 'artcms' ),
        'menu_name'         => __( 'Labels', 'artcms' ),
    );
    $labelargs = array(
        'hierarchical'      => false,
        'labels'            => $labellabels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'label' ],
        'show_in_rest'      => true
    );

    register_taxonomy( 'artcms_label', [ 'artcms_work', 'artcms_collection' ], $labelargs );
   

}








function artcms_profile_details_callback( $post )
{
    ?>


    <table>
        <tbody>
            <tr>
                <td colspan="4"><label for="artcms-profile-nationality">Nationality:</label></td>
            </tr>
            <tr>
                <td colspan="4"><input name="artcms-profile-nationality" id="artcms-profile-nationality" type="text"></td>
            </tr>
            <tr>
                <td><label for="artcms-profile-birthyear">Born:</label></td>
                <td><input name="artcms-profile-birthyear" id="artcms-profile-birthyear" type="number" step="1" min="-9999" max="9999"></td>
                <td><label for="artcms-profile-deathyear">Died:</label></td>
                <td><input name="artcms-profile-deathyear" id="artcms-profile-deathyear" type="number" step="1" min="-9999" max="9999"></td>
            </tr>
            <tr>
                <td colspan="4"><label for="artcms-profile-website">Website:</label></td>
            </tr>
            <tr>
                <td colspan="4"><input name="artcms-profile-website" id="artcms-profile-website" type="url"></td>
            </tr>
        </tbody>
    </table>


    <?php
}





function artcms_work_meta_callback( $post )
{
    ?>


    <table>
        <tbody>
            <tr>
                <td><label for="artcms-work-title">Title of piece:</label></td>
            </tr>
            <tr>
                <td><input name="artcms-work-title" id="artcms-work-title" type="text"></td>
            </tr>
            <tr>
                <td><label for="artcms-work-creator">Creator's name:</label></td>
            </tr>
            <tr>
                <td><input name="artcms-work-creator" id="artcms-work-creator" type="text"></td>
            </tr>
            <tr>
                <td><label for="artcms-work-creatorprofile">Profile page:</label></td>
            </tr>
            <tr>
                <td><input name="artcms-work-creatorprofile" id="artcms-work-creatorprofile" type="url"></td>
            </tr>
        </tbody>
    </table>


    <table>
        <tbody>
            <tr>
                <td><label for="artcms-work-year">Year:</label></td>
                <td><input name="artcms-work-year" id="artcms-work-year" type="number" step="1" min="-9999" max="9999"></td>
            </tr>
            <tr>
                <td colspan="2"><label for="artcms-work-location">Location of origin:</label></td>
            </tr>
            <tr>
                <td colspan="2"><input name="artcms-work-location" id="artcms-work-location" type="text"></td>
            </tr>
        </tbody>
    </table>


    <table>
        <tbody>
            <tr>
                <td><label for="artcms-work-genre">Genre:</label></td>
            </tr>
            <tr>
                <td><input name="artcms-work-genre" id="artcms-work-genre" type="text"></td>
            </tr>
            <tr>
                <td><label for="artcms-work-medium">Medium:</label></td>
            </tr>
            <tr>
                <td><input name="artcms-work-medium" id="artcms-work-medium" type="text"></td>
            </tr>
            <tr>
                <td><label for="artcms-work-size">Dimensions:</label></td>
            </tr>
            <tr>
                <td><input name="artcms-work-size" id="artcms-work-size" type="text"></td>
            </tr>
            <tr>
                <td><label for="artcms-work-duration">Duration:</label></td>
            </tr>
            <tr>
                <td><input name="artcms-work-duration" id="artcms-work-duration" type="text"></td>
            </tr>
        </tbody>
    </table>


    <?php
}





function artcms_collection_info_callback( $post )
{
    ?>


    <table>
        <tbody>
            <tr>
                <td colspan="2">Opening dates:</td>
            </tr>
            <tr>
                <td><label for="artcms-collection-datestart">From</label></td>
                <td><input name="artcms-collection-datestart" id="artcms-collection-datestart" type="date"></td>
            </tr>
            <tr>
                <td><label for="artcms-collection-dateend">to</label></td>
                <td><input name="artcms-collection-dateend" id="artcms-collection-dateend" type="date"></td>
            </tr>
            <tr>
                <td colspan="2"><label for="artcms-collection-location">Location:</label></td>
            </tr>
            <tr>
                <td colspan="2"><input name="artcms-collection-location" id="artcms-collection-location" type="text"></td>
            </tr>
            <tr>
                <td colspan="2"><label for="artcms-collection-link">Web site:</label></td>
            </tr>
            <tr>
                <td colspan="2"><input name="artcms-collection-link" id="artcms-collection-link" type="url"></td>
            </tr>
        </tbody>
    </table>
    

    <?php
}





function artcms_collection_contributors_callback( $post )
{
    ?>


    <p>To add names of contributing artists.</p>


    <?php
}
