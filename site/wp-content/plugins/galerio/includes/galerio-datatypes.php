<?php

/*
 * Galerio - Define datatypes (- custom page types, taxonomies & metaboxes).
 */



add_action( 'init', 'galerio_init' );
add_action( 'add_meta_boxes', 'galerio_add_meta' );
add_action( 'save_post', 'galerio_save_meta' );



function galerio_init()
{
    galerio_register_post_types();
    galerio_register_taxonomies();
    // add column sorting?
}








function galerio_add_meta()
{


    add_meta_box( 
        'galerio_profile_details', 
        'Details', 
        'galerio_profile_details_callback', 
        [ 'galerio_profile' ], 
        'side', 
        'high' 
    );



    add_meta_box( 
        'galerio_work_meta', 
        'Meta', 
        'galerio_work_meta_callback', 
        [ 'galerio_work' ], 
        'side', 
        'high' 
    );



    add_meta_box( 
        'galerio_collection_info', 
        'Collection info', 
        'galerio_collection_info_callback', 
        [ 'galerio_collection' ], 
        'side', 
        'high' 
    );
    add_meta_box( 
        'galerio_collection_contributors', 
        'Featured Profiles', 
        'galerio_collection_contributors_callback', 
        [ 'galerio_collection' ], 
        'normal' 
    );


}





function galerio_save_meta( $post )
{

}








function galerio_register_post_types()
{


    $profilelabels = array(
        'name'               => __( 'Profiles', 'galerio' ),
        'singular_name'      => __( 'Profile', 'galerio' ),
        'add_new'            => __( 'New Profile', 'galerio' ),
        'add_new_item'       => __( 'Add New Profile', 'galerio' ),
        'edit_item'          => __( 'Edit Profile', 'galerio' ),
        'item_updated'       => __( 'Profile updated', 'galerio' ),
        'new_item'           => __( 'New Profile', 'galerio' ),
        'all_items'          => __( 'All Profiles', 'galerio' ),
        'view_item'          => __( 'View Profile', 'galerio' ),
        'view_items'         => __( 'View Profiles', 'galerio' ),
        'search_items'       => __( 'Search Profiles', 'galerio' ),
        'not_found'          => __( 'No Profiles found', 'galerio' ),
        'not_found_in_trash' => __( 'No Profiles found in Trash', 'galerio' ),
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

    register_post_type( 'galerio_profile', $profileargs );





    $worklabels = array(
        'name'               => __( 'Works', 'galerio' ),
        'singular_name'      => __( 'Work', 'galerio' ),
        'add_new'            => __( 'New Work', 'galerio' ),
        'add_new_item'       => __( 'Add New Work', 'galerio' ),
        'edit_item'          => __( 'Edit Work', 'galerio' ),
        'item_updated'       => __( 'Work updated', 'galerio' ),
        'new_item'           => __( 'New Work', 'galerio' ),
        'all_items'          => __( 'All Works', 'galerio' ),
        'view_item'          => __( 'View Work', 'galerio' ),
        'view_items'         => __( 'View Works', 'galerio' ),
        'search_items'       => __( 'Search Works', 'galerio' ),
        'not_found'          => __( 'No Works found', 'galerio' ),
        'not_found_in_trash' => __( 'No Works found in Trash', 'galerio' ),
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

    register_post_type( 'galerio_work', $workargs );





    $collectionlabels = array(
        'name'               => __( 'Collections', 'galerio' ),
        'singular_name'      => __( 'Collection', 'galerio' ),
        'add_new'            => __( 'New Collection', 'galerio' ),
        'add_new_item'       => __( 'Add New Collection', 'galerio' ),
        'edit_item'          => __( 'Edit Collection', 'galerio' ),
        'item_updated'       => __( 'Collection updated', 'galerio' ),
        'new_item'           => __( 'New Collection', 'galerio' ),
        'all_items'          => __( 'All Collections', 'galerio' ),
        'view_item'          => __( 'View Collection', 'galerio' ),
        'view_items'         => __( 'View Collections', 'galerio' ),
        'search_items'       => __( 'Search Collections', 'galerio' ),
        'not_found'          => __( 'No Collections found', 'galerio' ),
        'not_found_in_trash' => __( 'No Collections found in Trash', 'galerio' ),
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

    register_post_type( 'galerio_collection', $collectionargs );


}








function galerio_register_taxonomies()
{


    $grouplabels = array(
        'name'              => _x( 'Groups', 'taxonomy general name', 'galerio' ),
        'singular_name'     => _x( 'Group', 'taxonomy singular name', 'galerio' ),
        'search_items'      => __( 'Search Groups', 'galerio' ),
        'all_items'         => __( 'All Groups', 'galerio' ),
        'parent_item'       => __( 'Parent Group', 'galerio' ),
        'parent_item_colon' => __( 'Parent Group:', 'galerio' ),
        'edit_item'         => __( 'Edit Group', 'galerio' ),
        'update_item'       => __( 'Update Group', 'galerio' ),
        'add_new_item'      => __( 'Add new Group', 'galerio' ),
        'new_item_name'     => __( 'New Group name', 'galerio' ),
        'menu_name'         => __( 'Groups', 'galerio' ),
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

    register_taxonomy( 'galerio_group', [ 'galerio_profile', 'galerio_collection' ], $groupargs );





    $typelabels = array(
        'name'              => _x( 'Types', 'taxonomy general name', 'galerio' ),
        'singular_name'     => _x( 'Type', 'taxonomy singular name', 'galerio' ),
        'search_items'      => __( 'Search Types', 'galerio' ),
        'all_items'         => __( 'All Types', 'galerio' ),
        'parent_item'       => __( 'Parent Type', 'galerio' ),
        'parent_item_colon' => __( 'Parent Type:', 'galerio' ),
        'edit_item'         => __( 'Edit Type', 'galerio' ),
        'update_item'       => __( 'Update Type', 'galerio' ),
        'add_new_item'      => __( 'Add new Type', 'galerio' ),
        'new_item_name'     => __( 'New Type name', 'galerio' ),
        'menu_name'         => __( 'Types', 'galerio' ),
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

    register_taxonomy( 'galerio_type', [ 'galerio_work' ], $typeargs );





    $labellabels = array(
        'name'              => _x( 'Labels', 'taxonomy general name', 'galerio' ),
        'singular_name'     => _x( 'Label', 'taxonomy singular name', 'galerio' ),
        'search_items'      => __( 'Search Labels', 'galerio' ),
        'all_items'         => __( 'All Labels', 'galerio' ),
        'parent_item'       => __( 'Parent Label', 'galerio' ),
        'parent_item_colon' => __( 'Parent Label:', 'galerio' ),
        'edit_item'         => __( 'Edit Label', 'galerio' ),
        'update_item'       => __( 'Update Label', 'galerio' ),
        'add_new_item'      => __( 'Add new Label', 'galerio' ),
        'new_item_name'     => __( 'New Label name', 'galerio' ),
        'menu_name'         => __( 'Labels', 'galerio' ),
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

    register_taxonomy( 'galerio_label', [ 'galerio_work', 'galerio_collection' ], $labelargs );
   

}








function galerio_profile_details_callback( $post )
{
    ?>


    <table>
        <tbody>
            <tr>
                <td colspan="4"><label for="galerio-profile-nationality">Nationality:</label></td>
            </tr>
            <tr>
                <td colspan="4"><input name="galerio-profile-nationality" id="galerio-profile-nationality" type="text"></td>
            </tr>
            <tr>
                <td><label for="galerio-profile-birthyear">Born:</label></td>
                <td><input name="galerio-profile-birthyear" id="galerio-profile-birthyear" type="number" step="1" min="-9999" max="9999"></td>
                <td><label for="galerio-profile-deathyear">Died:</label></td>
                <td><input name="galerio-profile-deathyear" id="galerio-profile-deathyear" type="number" step="1" min="-9999" max="9999"></td>
            </tr>
            <tr>
                <td colspan="4"><label for="galerio-profile-website">Website:</label></td>
            </tr>
            <tr>
                <td colspan="4"><input name="galerio-profile-website" id="galerio-profile-website" type="url"></td>
            </tr>
        </tbody>
    </table>


    <?php
}





function galerio_work_meta_callback( $post )
{
    ?>


    <table>
        <tbody>
            <tr>
                <td><label for="galerio-work-title">Title of piece:</label></td>
            </tr>
            <tr>
                <td><input name="galerio-work-title" id="galerio-work-title" type="text"></td>
            </tr>
            <tr>
                <td><label for="galerio-work-creator">Creator's name:</label></td>
            </tr>
            <tr>
                <td><input name="galerio-work-creator" id="galerio-work-creator" type="text"></td>
            </tr>
            <tr>
                <td><label for="galerio-work-creatorprofile">Profile page:</label></td>
            </tr>
            <tr>
                <td><input name="galerio-work-creatorprofile" id="galerio-work-creatorprofile" type="url"></td>
            </tr>
        </tbody>
    </table>


    <table>
        <tbody>
            <tr>
                <td><label for="galerio-work-year">Year:</label></td>
                <td><input name="galerio-work-year" id="galerio-work-year" type="number" step="1" min="-9999" max="9999"></td>
            </tr>
            <tr>
                <td colspan="2"><label for="galerio-work-location">Location of origin:</label></td>
            </tr>
            <tr>
                <td colspan="2"><input name="galerio-work-location" id="galerio-work-location" type="text"></td>
            </tr>
        </tbody>
    </table>


    <table>
        <tbody>
            <tr>
                <td><label for="galerio-work-genre">Genre:</label></td>
            </tr>
            <tr>
                <td><input name="galerio-work-genre" id="galerio-work-genre" type="text"></td>
            </tr>
            <tr>
                <td><label for="galerio-work-medium">Medium:</label></td>
            </tr>
            <tr>
                <td><input name="galerio-work-medium" id="galerio-work-medium" type="text"></td>
            </tr>
            <tr>
                <td><label for="galerio-work-size">Dimensions:</label></td>
            </tr>
            <tr>
                <td><input name="galerio-work-size" id="galerio-work-size" type="text"></td>
            </tr>
            <tr>
                <td><label for="galerio-work-duration">Duration:</label></td>
            </tr>
            <tr>
                <td><input name="galerio-work-duration" id="galerio-work-duration" type="text"></td>
            </tr>
        </tbody>
    </table>


    <?php
}





function galerio_collection_info_callback( $post )
{
    ?>


    <table>
        <tbody>
            <tr>
                <td colspan="2">Opening dates:</td>
            </tr>
            <tr>
                <td><label for="galerio-collection-datestart">From</label></td>
                <td><input name="galerio-collection-datestart" id="galerio-collection-datestart" type="date"></td>
            </tr>
            <tr>
                <td><label for="galerio-collection-dateend">to</label></td>
                <td><input name="galerio-collection-dateend" id="galerio-collection-dateend" type="date"></td>
            </tr>
            <tr>
                <td colspan="2"><label for="galerio-collection-location">Location:</label></td>
            </tr>
            <tr>
                <td colspan="2"><input name="galerio-collection-location" id="galerio-collection-location" type="text"></td>
            </tr>
            <tr>
                <td colspan="2"><label for="galerio-collection-link">Web site:</label></td>
            </tr>
            <tr>
                <td colspan="2"><input name="galerio-collection-link" id="galerio-collection-link" type="url"></td>
            </tr>
        </tbody>
    </table>
    

    <?php
}





function galerio_collection_contributors_callback( $post )
{
    ?>


    <p>To add names of contributing artists.</p>


    <?php
}
