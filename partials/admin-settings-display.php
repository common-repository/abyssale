<style>
    .submit-button {
        margin-left: auto!important;
        margin-right: 20px!important;
        display: block!important;
    }
    .form-wrap {
        width: 540px!important;
    }
</style>

<div class="wrap form-wrap">
    <h2>Abyssale Settings</h2>

    <?php if (isset($_GET['updated'])) {
        echo "<div class='updated'><p>Settings successfully updated</p></div>";
    } ?>

    <?php if (isset($_GET['error']) && sanitize_text_field($_GET['error']) == "api_key") {
        echo "<div class='error'><p>We can't call Abyssale. Wrong api key.</p></div>";
    } ?>

    <?php if (isset($_GET['error']) && sanitize_text_field($_GET['error']) == "template") {
        echo "<div class='error'><p>The template doesn't exist anymore</p></div>";
    } ?>

    <?php if (isset($_GET['error']) && sanitize_text_field($_GET['error']) == "elements") {
        echo "<div class='error'><p>Some elements of your template doesn't exists anymore</p></div>";
    } ?>

    <form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
        <?php
        settings_fields('abyssale_plugin_settings');
        do_settings_sections('abyssale_plugin');
        ?>
        <input
                type="submit"
                name="submit"
                class="button button-primary submit-button"
                value="<?php esc_attr_e('Save settings'); ?>"
        />
    </form>
</div>

<div class="wrap form-wrap">
    <h2>Template settings</h2>

    <?php
        if ($this->apiKey != null && !isset($_GET['error'])) {
            ?>
        <form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
            <?php
            settings_fields('abyssale_plugin_template_settings');
            do_settings_sections('abyssale_template_settings'); ?>
            <input
                    type="submit"
                    name="submit"
                    class="button button-primary submit-button"
                    value="<?php esc_attr_e('Save settings'); ?>"
            />
        </form>
    <?php
        } else { ?>
            <div>You need to fill your api key first.</div>
    <?php } ?>
</div>

<div class="wrap form-wrap">
    <?php
        $settingsOptions = get_option('abyssale_plugin_template_settings');
        if (isset($settingsOptions["template_id"]) && ($settingsOptions["template_id"] != null ||  $settingsOptions["template_id"] != "") && !isset($_GET['error'])) {
            ?>
        <h2>Test your integration</h2>
        <p>You can test your template with dummy fields we have filled for you</p>
        <ol>
            <li>Wordpress Title: Title example</li>
            <li>Wordpress Author: Author name</li>
            <li>Wordpress post date: The current date</li>
            <li>Wordpress feature image: the abyssale logo</li>
        </ol>
        <p>You should see those information on your image depending on the fields you have mapped.</p>
        <form action="options.php" method="post">
            <?php
            settings_fields('abyssale_plugin_integration_test');
            do_settings_sections('abyssale_integration_test'); ?>
            <input
                    type="submit"
                    name="submit"
                    class="button button-primary submit-button"
                    value="<?php esc_attr_e('Test integration'); ?>"
            />
        </form>
    <?php
        } ?>
</div>

<div class="wrap">
    <?php if (isset($_GET['settings-updated'])) {
            $this->callAbyssaleApiAsATest();
        } ?>
</div>

<script>
    jQuery(document).ready( function() {
        jQuery("#template_id").click( function(e) {
            e.preventDefault();
            const templateId = jQuery(this).val();
            if (templateId) {

            }
            jQuery.ajax({
                type : "GET",
                dataType : "json",
                url : "https://api.abyssale.com/templates/" + templateId,
                headers: {
                    "x-api-key": '<?php $options = get_option('abyssale_plugin_settings'); echo $options["api_key"];?>',
                    "Content-Type": "application/json"
                },
                beforeSend: function() {
                    for (const id of ["#title", "#author_name", "#image", "#creation_date"]) {
                        jQuery(id).find('option').remove().end().append('<option value="none">None</option>').val('none')
                    }

                    jQuery("#format").find('option').remove().end().append('<option value="default">Default</option>').val('default')
                },
                success: function(response) {
                    if("template_id" in response) {
                        for (const templateElement of response["elements"]) {
                            jQuery("#title")[0].options.add( new Option(templateElement["name"],templateElement["name"]) )
                            jQuery("#author_name")[0].options.add( new Option(templateElement["name"],templateElement["name"]) )
                            jQuery("#image")[0].options.add( new Option(templateElement["name"],templateElement["name"]) )
                            jQuery("#creation_date")[0].options.add( new Option(templateElement["name"],templateElement["name"]) )
                        }

                        jQuery("#format").find('option').remove().end();
                        for (const format of response["formats"]) {
                            jQuery("#format")[0].options.add( new Option(format["id"],format["id"]) )
                        }
                    }
                    else {
                        console.log("an error occurred")
                    }
                }
            })

        })
    })
</script>