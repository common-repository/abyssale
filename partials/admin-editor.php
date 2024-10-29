<?php
wp_enqueue_media();
?>

<div>
    <p>Create image with Abyssale</p>
    <div style="display: flex;justify-content: space-between;">
        <button style="margin-bottom: 20px;" class="components-button is-secondary" type="button" id="create_featured_image">Generate</button>
        <button class="components-button is-secondary" type="button" id="attach_as_featured_image">Feature this image</button>
    </div>
    <div id="abyssale-ajax-wrap-editor"></div>
</div>

<style>
    .loader {
        border: 5px solid #f3f3f3; /* Light grey */
        border-top: 5px solid #3498db; /* Blue */
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 2s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<script>
    jQuery(document).ready( function() {
        jQuery("#attach_as_featured_image").hide();
        var media_id = null;
        const { select } = wp.data;
        const editor = wp.data.select('core/editor');


        jQuery("#create_featured_image").click( function(e) {
            e.preventDefault();
            let featuredImgSrc = null;

            var imageId = editor.getEditedPostAttribute('featured_media');
            if (imageId) {
                var imageObj = select('core').getMedia(imageId);
                if (imageObj) {
                    featuredImgSrc = imageObj.source_url
                }
            }

            jQuery.ajax({
                type : "POST",
                url : ajaxurl,
                data: {
                    "action": "abyssale_admin_editor",
                    "post_id": jQuery("#post_ID").attr("value"),
                    "title": select("core/editor").getEditedPostAttribute( 'title' ),
                    "featured_img_src": featuredImgSrc
                },
                beforeSend: function() {
                    jQuery("#create_featured_image").prop('disabled', true);
                    jQuery("#abyssale-ajax-wrap-editor").empty().append('<div class="loader"></div>')
                },
                success: function(response) {
                    const resp = JSON.parse(response);
                    if (resp["is_error"] === true) {
                        jQuery("#abyssale-ajax-wrap-editor").empty().append(
                            "<div>" + resp["error"] + "</div>"
                        );
                    } else {
                        media_id = resp["media_id"];
                        jQuery("#attach_as_featured_image").show();
                        jQuery("#abyssale-ajax-wrap-editor").empty().append(
                            "<img src='" + resp["file"] + "' />"
                        );
                    }

                },
                error: function (response) {
                    jQuery("#abyssale-ajax-wrap-editor").empty().append(
                        "<div>An error occurred. Please try again.</div>"
                    );

                    media_id = null;
                },
                complete: function() {
                    jQuery("#create_featured_image").prop('disabled', false);
                }
            })
        })

        jQuery("#attach_as_featured_image").click( function(e) {
            e.preventDefault();

            jQuery.ajax({
                type: "POST",
                url: ajaxurl,
                data: {
                    "action": "abyssale_admin_attach",
                    "post_id": jQuery("#post_ID").attr("value"),
                    "media_id": media_id
                },
                beforeSend: function () {
                    jQuery("#attach_as_featured_image").prop('disabled', true);
                },
                success: function (response) {
                    jQuery("#abyssale-ajax-wrap-editor").empty().append(
                        "<div>The image has been attached</div>"
                    );

                    wp.data.dispatch('core/editor').editPost({featured_media: media_id});
                },
                error: function (response) {
                    jQuery("#abyssale-ajax-wrap-editor").empty().append(
                        "<div>An error occurred. Please try again.</div>"
                    );
                },
                complete: function () {
                    jQuery("#attach_as_featured_image").prop('disabled', false);
                }
            })
        })
    })
</script>

