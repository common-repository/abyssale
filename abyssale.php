<?php
/**
 * Plugin Name: Abyssale
 * Description: Create an header image when you create a new blog post.
 * Version: 1.2
 * Author: Abyssale
 * Author URI: https://www.abyssale.com/?utm_source=wp-plugins&utm_campaign=author-uri&utm_medium=wordpress
 */

include_once "common/abyssale-api.php";

class Abyssale
{
    protected $abyssaleApi;
    protected $apiKey;

    public function __construct()
    {
        $options = get_option('abyssale_plugin_settings');
        if (isset($options["api_key"])) {
            $this->apiKey = $options["api_key"];
        }

        $this->abyssaleApi = new AbyssaleApi($this->apiKey);
        $this->registerHook();
    }

    public function registerHook()
    {
        /**
         * Required hook when you install/deactivate/uninstall
         */
        register_activation_hook(__FILE__, array($this, 'onRegisterActivationHook'));
        register_deactivation_hook(__FILE__, array($this, 'onRegisterDeactivationHook'));

        add_action('admin_menu', array($this, 'addPluginAdminMenu'), 9);
        add_action('admin_init', array($this, 'registerAndBuildFields'));
        add_action('admin_post_add_api_key', array($this, 'settingsFormSubmit'));
        add_action('admin_post_add_template', array($this, 'templateFormSubmit'));
        add_action('add_meta_boxes', array($this, 'addMetaBox'));

        add_action('wp_ajax_abyssale_admin_editor', array($this, 'handleAjaxAdminEditor'));
        add_action('wp_ajax_abyssale_admin_attach', array($this, 'handleAjaxAttach'));
    }

    public function addMetaBox()
    {
        add_meta_box('abyssale-meta-box', 'Abyssale', array($this,'renderMetaBox'), 'post', 'side', 'high');
    }

    public function renderMetaBox()
    {
        require_once 'partials/admin-editor.php';
    }

    public function handleAjaxAdminEditor()
    {
        $postId = sanitize_text_field($_POST["post_id"]);
        $post = get_post($postId);
        $settingsOptions = get_option('abyssale_plugin_template_settings');

        $response = $this->abyssaleApi->callAbyssaleApi(
            $settingsOptions["template_id"],
            $settingsOptions["format"],
            sanitize_text_field($_POST["title"]),
            get_the_author_meta('display_name', $post->post_author),
            $post->post_date,
            esc_url_raw($_POST["featured_img_src"]),
            $settingsOptions
        );

        if ($response["is_error"] == false) {
            $id = $this->uploadImage($response["file"], $postId);
            $response["media_id"] = $id;
        }

        echo json_encode($response);
        // Always die in functions echoing ajax content
        die();
    }

    public function handleAjaxAttach()
    {
        $postId = sanitize_text_field($_POST["post_id"]);
        $mediaId = sanitize_text_field($_POST["media_id"]);

        set_post_thumbnail($postId, $mediaId);
        update_post_meta($postId, '_thumbnail_id', $mediaId);

        echo "";
        die();
    }

    public function uploadImage($file, $postId)
    {
        // Set variables for storage, fix file filename for query strings.
        preg_match('/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $file, $matches);
        if (! $matches) {
            return new WP_Error('image_sideload_failed', __('Invalid image URL'));
        }

        $file_array = array();
        $file_array['name'] = basename($matches[0]);

        // check if download_url exists
        if (! function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // Download file to temp location.
        $file_array['tmp_name'] = download_url($file);

        // If error storing temporarily, return the error.
        if (is_wp_error($file_array['tmp_name'])) {
            return $file_array['tmp_name'];
        }

        // check if download_url exists
        if (! function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }

        $id = media_handle_sideload($file_array, $postId);
        // If error storing permanently, unlink.
        if (is_wp_error($id)) {
            @unlink($file_array['tmp_name']);
        } else {
            return $id;
        }
    }

    public function onRegisterActivationHook()
    {
    }

    public function onRegisterDeactivationHook()
    {
    }

    public function addPluginAdminMenu()
    {
        add_menu_page('abyssale', 'Abyssale', 'administrator', 'abyssale', array($this, 'displayPluginAdminDashboard'), plugin_dir_url(__FILE__) ."imgs/logo-abyssale.png", 26);
    }


    public function displayPluginAdminDashboard()
    {
        require_once 'partials/admin-settings-display.php';
    }

    public function registerAndBuildFields()
    {
        // Settings page
        register_setting(
            'abyssale_plugin_settings',
            'abyssale_plugin_settings',
            array($this, 'validateSettings')
        );

        add_settings_section(
            'section_one',
            '',
            array($this, 'addAbyssaleSection'),
            'abyssale_plugin'
        );

        add_settings_field(
            'api_key',
            'Api Key',
            array($this, 'renderApiKey'),
            'abyssale_plugin',
            'section_one'
        );

        add_settings_field(
            'hidden',
            '',
            array($this,'renderHidden'),
            'abyssale_plugin',
            'section_one',
            array(
                "value" => "add_api_key"
            )
        );

        register_setting(
            'abyssale_plugin_template_settings',
            'abyssale_plugin_template_settings',
            array($this, 'validateTemplateSettings')
        );

        add_settings_section(
            'section_templates',
            '',
            array($this, 'addAbyssaleTemplatesSection'),
            'abyssale_template_settings'
        );

        add_settings_field(
            'template_id',
            'Template Id:',
            array($this, 'renderTemplateId'),
            'abyssale_template_settings',
            'section_templates'
        );

        add_settings_field(
            'format',
            'Template format:',
            array($this, 'renderTemplateFormat'),
            'abyssale_template_settings',
            'section_templates'
        );

        add_settings_field(
            'title',
            'Wordpress title:',
            array($this, 'renderSettingsOptions'),
            'abyssale_template_settings',
            'section_templates',
            array(
                "name" => "title"
            )
        );

        add_settings_field(
            'author_name',
            'Wordpress author name:',
            array($this, 'renderSettingsOptions'),
            'abyssale_template_settings',
            'section_templates',
            array(
                "name" => "author_name"
            )
        );

        add_settings_field(
            'image',
            'Wordpress featured image:',
            array($this, 'renderSettingsOptions'),
            'abyssale_template_settings',
            'section_templates',
            array(
                "name" => "image"
            )
        );

        add_settings_field(
            'creation_date',
            'Wordpress post creation date:',
            array($this, 'renderSettingsOptions'),
            'abyssale_template_settings',
            'section_templates',
            array(
                "name" => "creation_date"
            )
        );

        add_settings_field(
            'hidden_2',
            '',
            array($this,'renderHidden'),
            'abyssale_template_settings',
            'section_templates',
            array(
                "value" => "add_template"
            )
        );

        // Abyssale Dashboard
        register_setting(
            'abyssale_plugin_integration_test',
            'abyssale_plugin_integration_test',
            ''
        );

        add_settings_section(
            'section_tow',
            '',
            array($this, 'addAbyssaleSection2'),
            'abyssale_integration_test'
        );
    }

    public function validateSettings($input)
    {
        $output['api_key'] = sanitize_text_field($input['api_key']);

        return $output;
    }

    public function validateTemplateSettings($input)
    {
        $output['template_id'] = sanitize_text_field($input['template_id']);
        $output['format'] = sanitize_text_field($input['format']);
        $output['title'] = sanitize_text_field($input['title']);
        $output['author_name'] = sanitize_text_field($input['author_name']);
        $output['image'] = sanitize_text_field($input['image']);
        $output['creation_date'] = sanitize_text_field($input['creation_date']);

        return $output;
    }

    public function templateFormSubmit()
    {
        $templateId = sanitize_text_field($_POST["abyssale_plugin_template_settings"]["template_id"]);
        $response = $this->abyssaleApi->getTemplate($templateId);
        if ($response["is_error"] == true) {
            wp_redirect(admin_url('/admin.php?page=abyssale&error=template', 'http'), 301);
            return;
        }

        $format = sanitize_text_field($_POST["abyssale_plugin_template_settings"]["format"]);
        $title = sanitize_text_field($_POST["abyssale_plugin_template_settings"]["title"]);
        $authorName = sanitize_text_field($_POST["abyssale_plugin_template_settings"]["author_name"]);
        $image = sanitize_text_field($_POST["abyssale_plugin_template_settings"]["image"]);
        $creationDate = sanitize_text_field($_POST["abyssale_plugin_template_settings"]["creation_date"]);

        $templateElements = $response["template"]["elements"];
        if (Abyssale::verifyNameExistsInElements($title,$templateElements) == false ||
            Abyssale::verifyNameExistsInElements($authorName,$templateElements) == false ||
            Abyssale::verifyNameExistsInElements($image,$templateElements) == false ||
            Abyssale::verifyNameExistsInElements($creationDate,$templateElements) == false) {
            wp_redirect(admin_url('/admin.php?page=abyssale&error=elements', 'http'), 301);
            return;
        }

        update_option(
            'abyssale_plugin_template_settings',
            array(
                "template_id" => $templateId,
                "title" => $title,
                "author_name" => $authorName,
                "image" => $image,
                "creation_date" => $creationDate,
                "format" => $format,
            )
        );

        wp_redirect(admin_url('/admin.php?page=abyssale&updated=true', 'http'), 301);
    }

    public static function verifyNameExistsInElements($value, $templateElements)
    {
        if ($value == "none" || $value == "") {
            return true;
        }

        foreach ($templateElements as $element) {
            if ($element["name"] == $value) {
                return true;
            }
        }

        return false;
    }

    public function settingsFormSubmit()
    {
        $apiKey = sanitize_text_field($_POST["abyssale_plugin_settings"]["api_key"]);
        $api = new AbyssaleApi($apiKey);
        $response = $api->getTemplates();

        if ($response == null || $response["is_error"] == true) {
            update_option(
                'abyssale_plugin_settings',
                array(
                    "api_key" => null
                )
            );
            wp_redirect(admin_url('/admin.php?page=abyssale&error=api_key', 'http'), 301);
            return;
        }

        update_option(
            'abyssale_plugin_settings',
            array(
                    "api_key" => $apiKey
            )
        );

        wp_redirect(admin_url('/admin.php?page=abyssale&updated=true', 'http'), 301);
    }

    public function addAbyssaleSection()
    {
        echo '<h3>With Abyssale, you will be able to create featured image on the fly!</h3>';
        echo '<p>Fill the following fields in order to create image when you post a new blog article.</p>';
        echo'<p>You can find in your: <a href="'.esc_url("https://app.abyssale.com/settings/api/key").'">account settings->API</a></p>';
        echo'<p>You don\'t have an Abyssale account yet <a href="'.esc_url("https://app.abyssale.com/register").'">register here</a></p>';
    }

    public function addAbyssaleSection2()
    {
    }

    public function addAbyssaleTemplatesSection()
    {
        require_once "partials/template-section.php";
    }

    public function renderApiKey()
    {
        $options = get_option('abyssale_plugin_settings');

        printf(
            '<input  style="width:300px" type="password" name="%s" value="%s" />',
            esc_attr('abyssale_plugin_settings[api_key]'),
            esc_attr($options['api_key'])
        );
    }

    public function renderTemplateId()
    {
        $optionsApikey = get_option('abyssale_plugin_settings');
        if ($optionsApikey["api_key"] == null) {
            echo '<div>You need to fill the api key first</div>';
            return;
        }

        $response = $this->abyssaleApi->getTemplates();
        if ($response == null || $response["is_error"] == true) {
            echo '<div>An error occurred when loading templates. Please verify your api key.</div>';
            echo esc_attr($response["error"]);
            return;
        }

        $options = get_option('abyssale_plugin_template_settings');
        printf('<select style="width:300px" id="template_id" name="%s" value="%s">', esc_attr('abyssale_plugin_template_settings[template_id]'), esc_attr($options['template_id']));
        foreach ($response["templates"] as $template) {
            if ($template["id"] == $options['template_id']) {
                echo '<option selected="selected" value="'.esc_attr($template["id"]).'">'.esc_attr($template["name"]).'</option>';
            } else {
                echo '<option value="'.esc_attr($template["id"]).'">'.esc_attr($template["name"]).'</option>';
            }
        }
        echo '</select>';
    }

    public function renderSettingsOptions($args)
    {
        if (!$args["name"]) {
            throw new Exception("The name has not been filled in the args section");
        }

        $templateElements = array();
        $optionsApikey = get_option('abyssale_plugin_settings');
        $optionsTemplate = get_option('abyssale_plugin_template_settings');
        if ($optionsApikey["api_key"] != null && $optionsTemplate["template_id"] != null) {
            $response = $this->abyssaleApi->getTemplate($optionsTemplate["template_id"], false);
            if ($response["is_error"] == true) {
                throw new Exception("An error occurred when retrieving the template.");
            }

            $templateElements = $response["template"]["elements"];
        }

        $options = get_option('abyssale_plugin_template_settings');
        printf('<select style="width:300px" id="%s" name="%s" value="%s">', $args["name"], esc_attr('abyssale_plugin_template_settings['.$args["name"].']'), esc_attr($options[$args["name"]]));
        echo '<option value="">None</option>';
        foreach ($templateElements as $element) {
            if ($element["name"] == $options[$args["name"]]) {
                echo '<option selected="selected" value="'.esc_attr($element["name"]).'">'.esc_attr($element["name"]).'</option>';
            } else {
                echo '<option value="'.esc_attr($element["name"]).'">'.esc_attr($element["name"]).'</option>';
            }
        }
        echo '</select>';
    }

    public function renderTemplateFormat($args)
    {
        $templateFormats = array();
        $optionsApikey = get_option('abyssale_plugin_settings');
        $optionsTemplate = get_option('abyssale_plugin_template_settings');
        if ($optionsApikey["api_key"] != null && $optionsTemplate["template_id"] != null) {
            $response = $this->abyssaleApi->getTemplate($optionsTemplate["template_id"], false);
            if ($response["is_error"] == true) {
                throw new Exception("An error occurred when retrieving the template.");
            }

            $templateFormats = $response["template"]["formats"];
        }

        $options = get_option('abyssale_plugin_template_settings');
        printf('<select style="width:300px" id="format" name="%s" value="%s">',  esc_attr('abyssale_plugin_template_settings[format]'), esc_attr($options["format"]));
        foreach ($templateFormats as $format) {
            if ($format["id"] == $options["format"]) {
                echo '<option selected="selected" value="'.esc_attr($format["id"]).'">'.esc_attr($format["id"]).'</option>';
            } else {
                echo '<option value="'.esc_attr($format["id"]).'">'.esc_attr($format["id"]).'</option>';
            }
        }
        echo '</select>';
    }

    public function renderHidden($args)
    {
        printf('<input type="hidden" name="action" value="' . esc_attr($args["value"]) . '" />');
    }

    public function callAbyssaleApiAsATest()
    {
        $options = get_option('abyssale_plugin_settings');
        $settingsOptions = get_option('abyssale_plugin_template_settings');

        if (!isset($settingsOptions["template_id"]) || $settingsOptions["template_id"] == null) {
            echo "The template id has not been filled";

            return;
        }

        if (!isset($options["api_key"]) || $options["api_key"] == null) {
            echo "The api_key has not been filled";

            return;
        }

        $now = new DateTime("now");
        $response = $this->abyssaleApi->callAbyssaleApi(
            $settingsOptions["template_id"],
            $settingsOptions["format"],
            "Title example",
            "Author name",
            $now->format("Y-m-d H:i"),
            "https://www.abyssale.com/img/favicon/favicon-96x96.png", //@todo Change the default image
            $settingsOptions
        );
        if ($response["is_error"] == true) {
            echo "Something went wrong:" . $response['error'];
        } else {
            printf("<img style='max-height:800px; max-width: 800px; margin-top: 20px' src='%s'/>", esc_url($response["file"]));
        }
    }
}

$abyssale = new Abyssale();

// Uninstall can only be static
function AbyssaleOnRegisterUninstallHook()
{
}

register_uninstall_hook(__FILE__, 'AbyssaleOnRegisterUninstallHook');
