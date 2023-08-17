<?php
/*
Plugin Name: Location Page Generator
Description: Creates Location Pages
Version: 0.93
Author: Gavin Steacy
License: GNU GPL v2

    Copyright 2011  Gavin Steacy

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/
$pages_added = false;
$num_pages = 0;
$location_template;
$title_template;
$location_template = get_option('location_template');
$title_template = get_option('title_template');
$slug_template = get_option('slug_template');

if (isset($_POST['submit']))
{
	unset($_POST['submit']);
	add_action('init', 'init_generator');
}
add_action('admin_menu', 'init_page');


function create_pages($suburbs, $parent, $page_template)
{
	global $pages_added;
	global $num_pages;
	global $location_template;
	global $title_template;
	global $slug_template;

	// If either of these templates are empty or missing, bail out. We shouldn't have even been able to get this far anyway.
	if (!$location_template || strlen($location_template) < 2)
		return;

	if (!$title_template || strlen($title_template) < 2)
		return;

	// Handle capitalised placeholder
	$location_template = str_replace("{Suburb}", "{suburb}", $location_template);
	$title_template = str_replace("{Suburb}", "{suburb}", $title_template);

	$suburbs = explode(PHP_EOL, $suburbs);
	foreach ($suburbs as $suburb)
	{
		if (strlen($suburb) < 2)
			continue;

		generate_page($page_template, $suburb, $parent);
		$num_pages++;
	}
	if ($num_pages > 0)
		$pages_added = true;
}

function generate_page($page_template, $suburb, $parent)
{
	global $location_template;
	global $title_template;

	$parent = isset($_POST['parent_page']) ? $_POST['parent_page'] : 0;

	// Check if "none" is selected and adjust the parent value
	if ($parent == 'none') {
		$parent = 0;
	}

	$title = create_page_title($suburb);
	$content = create_page_content($suburb);
	$slug = create_page_slug($title);

	// Look for any pages with the same slug
	$identical_posts = get_posts( array( 'name' => $slug, 'post_type' => 'page') );

	// If there are no pages with the same slug, create a new one
	if (count($identical_posts) < 1)
	{
		$post = array(
			'comment_status' => 'closed', // 'closed' means no comments.
			'ping_status' => 'closed', // 'closed' means pingbacks or trackbacks turned off
			'post_content' => $content, //The full text of the post.
			'post_name' => $slug, // The slug for your post
			'post_parent' => $parent, //Sets the parent of the new post.
			'post_status' => 'publish', //Set the status of the new post.
			'post_title' => $title, //The title of your post.
			'post_type' => 'page' //You may want to insert a regular post, page, link, a menu item or some custom post type
		);

		// Insert the post... and do nothing with the error if it is thrown? Should fix that.
		$post_id = wp_insert_post($post, $wp_error);

		// Make sure that the right page template is used.
		update_post_meta($post_id, '_wp_page_template', $page_template);

		// Make sure that the Exclude Pages plugin exists otherwise we can leave the pages in the menu (bad but better than spitting out errors)
		if (function_exists('ep_exclude_pages'))
		{
			// A bit of a hack to make sure that the Exclude Pages plugin doesn't bail out early.
			$_POST['ep_this_page_included'] = false;
			$_POST['ep_ctrl_present'] = true;

			// Exclude the page from the menu!
			ep_update_exclusions($post_id);
		}
	}
	// If the page already exists, just update its content instead
	else
	{
		$post = array (
			'ID' => $identical_posts[0]->ID, // Use the existing post's ID
			'post_content' => $content
			);
		wp_update_post( $post );
	}
}

function create_page_title($suburb)
{
	global $title_template;
	$title = preg_replace( '/\s+/', ' ', trim( $title_template ) );
	return str_replace("{suburb}", trim(ucwords($suburb)), $title);
}

function create_page_slug($suburb)
{
	global $slug_template;
	$slug = preg_replace( '/\s+/', ' ', trim( $slug_template ) );
	return str_replace("{suburb}", trim(ucwords($suburb)), $slug);
}

function create_page_content($suburb)
{
	global $location_template;
	return str_replace("{suburb}", trim(ucwords($suburb)), $location_template);
}

function init_page()
{
	create_menu();
	register_setting( 'location-generator-settings', 'location_template');
	register_setting( 'location-generator-settings', 'title_template');
	register_setting( 'location-generator-settings', 'slug_template' );
}

function init_generator()
{
	// Maybe I should make these global so they don't get passed around so much.
	create_pages($_POST['suburbs'], $_POST['page_id'], $_POST['page_template']);
}

function create_menu()
{
	add_menu_page( 'Location Page Generator', 'Locations', 'manage_options', 'create-location-pages', 'location_gen_page');
	add_submenu_page( 'create-location-pages', 'Create Location Pages', 'Create Pages', 'manage_options', 'create-location-pages', 'location_gen_page');
	add_submenu_page( 'create-location-pages', 'Set Locations Template', 'Set Template', 'manage_options', 'set-template', 'set_template_page');
}

function location_gen_page()
{
	global $pages_added;
	global $num_pages;
	global $location_template;
	global $title_template;

	$button_status = '';
	$button_value = 'Create Pages';

	if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}
	echo '<div class="wrap">';
	echo '<h2>Create Location Pages</h2>';

	// If either of these templates are empty or missing print a warning and disable the Create Pages button.
	if (!$location_template || strlen($location_template) < 2)
	{
		echo '<div class="error">You have not set the <strong>location page template</strong> yet! You can\'t create pages until this is done.</div>';
		$button_value = 'Disabled';
		$button_status = 'disabled="disabled"';
	}

	if (!$title_template || strlen($title_template) < 2)
	{
		echo '<div class="error">You have not set the <strong>title template</strong> yet! You can\'t create pages until this is done.</div>';
		$button_value = 'Disabled';
		$button_status = 'disabled="disabled"';
	}

	// Yay, it worked (hopefully)!
	if ($pages_added)
		echo '<div class="updated"><strong>' . $num_pages . '</strong> pages added successfully!</div>';

	echo '<p>Enter suburbs below, separated by a new line.</p>';
	echo '<form method="post" action="./admin.php?page=create-location-pages">';
	echo '<table border="1" cellpadding="0" cellspacing="0"><tbody><tr>';
	echo '<td><textarea style="width: 300px; height: 600px;" name="suburbs"></textarea></td>';
	echo '<td width="100">&nbsp;</td>';
	echo '<td valign="top"><strong>Parent Page</strong><br/>';

// Get the default dropdown HTML
$dropdown = wp_dropdown_pages( array( 'depth' => 4, 'echo' => 0, 'name' => 'parent_page' ));

// Add the "none" option
$none_option = '<option value="0">None</option>';
$dropdown_with_none = preg_replace('/<select(.*?)>/','<select$1>' . $none_option, $dropdown, 1);

// Output the modified dropdown
echo $dropdown_with_none . '<br /><br />';

	echo '<strong>Page Template</strong><br/>';
	echo '<select name="page_template">';
	echo '<option value="default">Default Template</option>';
	page_template_dropdown();
	echo '</select></td>';
	echo '</tr></tbody></table><br/ ><br />';
	echo '<input type="submit" name="submit" value="'. $button_value . '" '. $button_status . '>';
	echo '</form></div>';
}

function set_template_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $updated_message = isset($_GET['updated']) ? '<div class="updated">Template updated successfully!</div>' : '';

    $title_template = get_option('title_template', '');
    $slug_template = get_option('slug_template', '');
    $location_template = get_option('location_template', '');

    ?>
    <div class="wrap">
        <h2>Set Location Template</h2>
        <?php echo $updated_message; ?>
        <form method="post" action="options.php">
            <?php settings_fields('location-generator-settings'); ?>
            <?php do_settings_fields('location-generator-settings', 'Locations'); ?>
            <strong>Title Template</strong><br />
            <input type="text" style="width: 800px;" name="title_template" value="<?php echo esc_attr($title_template); ?>" /><br /><br />
            <strong>Slug Template</strong><br />
            <input type="text" style="width: 800px;" name="slug_template" value="<?php echo esc_attr($slug_template); ?>" /><br /><br />
            <strong>Page Content Template</strong><br />
            <textarea style="width: 800px; height: 500px;" name="location_template"><?php echo esc_textarea($location_template); ?></textarea><br /><br />
            <input type="submit" value="Save">
        </form>
    </div>
    <?php
}
