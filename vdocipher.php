<?php
/**
 * Plugin Name: VdoCipher
 * Plugin URI: http://www.vdocipher.com
 * Description: Secured video hosting for wordpress
 * Version: 1.17
 * Author: VdoCipher
 * Author URI: http://www.vdocipher.com
 * License: GPL2
 */

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
    exit("<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">\r\n<html><head>\r\n<title>404 Not Found</title>\r\n".
        "</head><body>\r\n<h1>Not Found</h1>\r\n<p>The requested URL " . $_SERVER['SCRIPT_NAME'] . " was not found on ".
        "this server.</p>\r\n</body></html>");
}

function vdo_send($action, $params, $posts = array())
{
    $client_key = get_option('vdo_client_key');
    if ($client_key == false || $client_key == "") {
        return "Plugin not configured. Please set the key to embed videos.";
    }

    $getData = http_build_query($params);
    $posts["clientSecretKey"] = $client_key;
    $url = "http://api.vdocipher.com/v2/$action/?$getData";
    $response = wp_safe_remote_post($url, array(
        'method'    =>  'POST',
        'body'      =>  $posts
    ));
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        echo "VdoCipher: Something went wrong: $error_message";
        return "";
    }
    return $response['body'];
}
function vdo_shortcode($atts)
{
    extract(shortcode_atts(
        array(
                    'title' => 'TITLE_OF_VIDEO',
                    'width' => get_option('vdo_default_width') . "px",
                    'height' => get_option('vdo_default_height') . "px",
                    'id'    => 'id',
                    'no_annotate'=> false,
                    'version'=> 0,
                    ),
        $atts
    ));
    if (!$atts['id']) {
        if (!$atts['title']) {
            return "Required argument id for embedded video not found.";
        }
        $params = array(
                'search'=>array(
                    'title'=>$title
                    ),
                'page'=>1,
                'limit'=>30,
                'type'=>'json'
                );
        $video = vdo_send("videos", $params);
        $video = json_decode($video);
        if ($video == null) {
            return "404. Video not found.";
        }
        $video = $video[0]->id;
        if ($video == null) {
            return "No video with given title found.";
        }
    } else {
        $video = $id;
    }

    $params = array(
        'video'=>$video
    );
    $anno = false;
    if (!function_exists("eval_date")) {
        function eval_date($matches)
        {
            return current_time($matches[1]);
        }
    }
    if (get_option('vdo_annotate_code') != "") {
        $current_user = wp_get_current_user();
        $vdo_annotate_code = get_option('vdo_annotate_code');
        $vdo_annotate_code = apply_filters('vdocipher_annotate_preprocess', $vdo_annotate_code);
        if (is_user_logged_in()) {
            $vdo_annotate_code = str_replace('{name}', $current_user->display_name, $vdo_annotate_code);
            $vdo_annotate_code = str_replace('{email}', $current_user->user_email, $vdo_annotate_code);
            $vdo_annotate_code = str_replace('{username}', $current_user->user_login, $vdo_annotate_code);
            $vdo_annotate_code = str_replace('{id}', $current_user->ID, $vdo_annotate_code);
        }
        $vdo_annotate_code = str_replace('{ip}', $_SERVER['REMOTE_ADDR'], $vdo_annotate_code);
        $vdo_annotate_code = preg_replace_callback('/\{date\.([^\}]+)\}/', "eval_date", $vdo_annotate_code);
        $vdo_annotate_code = apply_filters('vdocipher_annotate_postprocess', $vdo_annotate_code);
        if (!$no_annotate) {
            $anno = array("annotate" => $vdo_annotate_code);
        }
    }
    $OTP = vdo_send("otp", $params, $anno);
    $OTP = json_decode($OTP);
    if (is_null($OTP)) {
        $output = "<span id='vdo$OTP' style='background:#555555;color:#FFFFFF'><h4>Video not found</h4></span>";
        return $output;
    }
    $OTP = $OTP->otp;

    $version = 0;
    if (isset($atts['version'])) {
        $version = $atts['version'];
    }
    if ((get_option('vdo_embed_version')) == false) {
        update_option('vdo_embed_version', '0.5');
    }
    if ((get_option('vdo_player_theme')) == false) {
        update_option('vdo_player_theme','9ae8bbe8dd964ddc9bdb932cca1cb59a');
    }
    $vdo_embed_version_str = get_option('vdo_embed_version');
    $vdo_player_theme = get_option('vdo_player_theme');

    // Old Embed Code
    if($vdo_embed_version_str === '0.5') {
        $output = "<div id='vdo$OTP' style='height:$height;width:$width;max-width:100%' ></div>";
        $output .= "<script> (function(v,i,d,e,o){v[o]=v[o]||{}; v[o].add = v[o].add || function V(a){".
            " (v[o].d=v[o].d||[]).push(a);};";
        $output .= "if(!v[o].l) { v[o].l=1*new Date(); a=i.createElement(d), m=i.getElementsByTagName(d)[0]; a.async=1; ".
            "a.src=e; m.parentNode.insertBefore(a,m);}";
        $output .= " })(window,document,'script','//de122v0opjemw.cloudfront.net/vdo.js','vdo'); vdo.add({ ";
        $output .= "o: '$OTP', ";
        if ($version == 32) {
            $output .= "version: '$version' ";
        }
        $output .= "}); </script>";
    }
    //New embed code
    else {
        $output .= "<div id='vdo$OTP' style='height:$height;width:$width;max-width:100%' ></div>";
        $output .= "<script>(function(v,i,d,e,o){v[o]=v[o]||{}; v[o].add = v[o].add || function V(a){ (v[o].d=v[o].d||[]).push(a);};";
        $output .= "if(!v[o].l) { v[o].l=1*new Date(); a=i.createElement(d), m=i.getElementsByTagName(d)[0];";
        $output .= "a.async=1; a.src=e; m.parentNode.insertBefore(a,m);}";
        $output .= "})(window,document,'script','https://d1z78r8i505acl.cloudfront.net/playerAssets/";
        $output .= "$vdo_embed_version_str";
        $output .= "/vdo.js','vdo');";
        $output .= "vdo.add({";
        $output .= "otp: '$OTP',";
        $output .= "playbackInfo: btoa(JSON.stringify({";
        $output .= "videoId: '$video'})),";
        $output .= "theme: '$vdo_player_theme',";
        $output .= "container: document.querySelector('#vdo$OTP'),});";
        $output .= "</script>";
    }
    return $output;
}

add_shortcode('vdo', 'vdo_shortcode');

/// adding the settings link
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'vdo_settings_link');

function vdo_settings_link($links)
{
    $settings_link = '<a href="options-general.php?page=vdocipher">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/// add the menu item and register settings
if (is_admin()) { // admin actions
    add_action('admin_menu', 'vdo_menu');
    add_action('admin_init', 'register_vdo_settings');
} else {
      // non-admin enqueues, actions, and filters
}
function vdo_menu()
{
    add_menu_page('VdoCipher Options', 'VdoCipher', 'manage_options', 'vdocipher', 'vdo_options');
}
function vdo_options()
{
    if (!get_option('vdo_default_height')) {
        update_option('vdo_default_height', '360');
    }
    if (!get_option('vdo_default_width')) {
        update_option('vdo_default_width', '640');
    }
    $vdo_client_key = get_option('vdo_client_key');
    if (!$vdo_client_key && strlen($vdo_client_key) != 64) {
        vdo_show_form_client_key();
        return "";
    }
    if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    include('include/options.php');
}
function register_vdo_settings()
{
 // whitelist options
    register_setting('vdo_option-group', 'vdo_client_key');
    register_setting('vdo_option-group', 'vdo_default_height');
    register_setting('vdo_option-group', 'vdo_default_width');
    register_setting('vdo_option-group', 'vdo_annotate_code');
    register_setting('vdo_option-group', 'vdo_embed_version');
    register_setting('vdo_option-group', 'vdo_player_theme');
}

/// adding a section for asking for the client key
function vdo_show_form_client_key()
{
    include('include/setting_form.php');
}
register_deactivation_hook(__FILE__, 'vdo_deactivate');
function vdo_deactivate()
{
    delete_option('vdo_client_key');
    delete_option('vdo_default_width');
    delete_option('vdo_default_height');
    delete_option('vdo_annotate_code');
    delete_option('vdo_embed_version');
    delete_option('vdo_player_theme');
}
function vdo_activate()
{
    if ((get_option('vdo_default_height')) == false) {
        update_option('vdo_default_height', '360');
    }
    if ((get_option('vdo_default_width')) == false) {
        update_option('vdo_default_width', '640');
    }
    //https://stackoverflow.com/a/2173318/5022684
    if ((get_option('vdo_embed_version')) == false) {
        update_option('vdo_embed_version', '0.5');
    }
    if ((get_option('vdo_player_theme')) == false) {
        update_option('vdo_player_theme','9ae8bbe8dd964ddc9bdb932cca1cb59a');
    }
}
register_activation_hook(__FILE__, 'vdo_activate');
function vdo_admin_notice()
{
    if ((!get_option('vdo_client_key') || strlen(get_option('vdo_client_key')) != 64)
        && basename($_SERVER['PHP_SELF']) == "plugins.php"
    ) {
        ?>
        <div class="error">
            <p>
            The VdoCipher plugin is not ready.
            <a href="options-general.php?page=vdocipher">Click here to configure</a>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'vdo_admin_notice');
