<?php

class AbyssaleApi
{
    protected $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    protected $cachedTemplate = array();

    public function getTemplates()
    {
        if ($this->apiKey == null) {
            return null;
        }

        $response = wp_remote_get("https://api.abyssale.com/templates", array(
            'headers'     => array(
                "x-api-key" => $this->apiKey,
                'Content-Type' => 'application/json'
            ),
        ));

        if (is_wp_error($response) || $response["response"]["code"] != 200) {
            $formattedResponse = array(
                "is_error" => true,
                "error" => json_decode($response["body"], true)
            );
        } else {
            $body = json_decode($response["body"], true);
            $formattedResponse = array(
                "is_error" => false,
                "templates" => $body
            );
        }

        return $formattedResponse;
    }

    public function getTemplate($id, $cache = false)
    {
        if ($cache == true && count($this->cachedTemplate) >0) {
            return $this->cachedTemplate;
        }

        $options = get_option('abyssale_plugin_settings');
        if (!isset($options["api_key"]) || $options["api_key"] == null) {
            return null;
        }

        $response = wp_remote_get("https://api.abyssale.com/templates/".$id, array(
            'headers'     => array(
                "x-api-key" => $options["api_key"],
                'Content-Type' => 'application/json'
            ),
        ));

        if (is_wp_error($response)) {
            $formattedResponse = array(
                "is_error" => true,
                "error" => $response->get_error_message()
            );
        } else {
            $body = json_decode($response["body"], true);
            $formattedResponse = array(
                "is_error" => false,
                "template" => $body
            );
        }

        $this->cachedTemplate = $formattedResponse;

        return $formattedResponse;
    }

    public function callAbyssaleApi($templateId, $format, $title, $authorName, $creationDate, $image, $options)
    {
        if ($templateId == null) {
            return null;
        }

        if ($this->apiKey == null) {
            return null;
        }
        $requestBody = array(
            "elements" => array()
        );

        if ($format != null && $format != "") {
            $requestBody["template_format_name"] = $format;
        }

        if (isset($options["title"]) && $options["title"] != "" && ($title != "" || $title != null)) {
            $requestBody["elements"][$options["title"]] = array(
                "payload" => $title,
            );
        }

        if (isset($options["author_name"]) && $options["author_name"] != "") {
            $requestBody["elements"][$options["author_name"]] = array(
                "payload" => $authorName,
            );
        }

        if (isset($options["creation_date"]) && $options["creation_date"] != "") {
            $requestBody["elements"][$options["creation_date"]] = array(
                "payload" => $creationDate,
            );
        }

        if (isset($options["image"]) && $options["image"] != "" && ($image != "" || $image != null)) {
            $requestBody["elements"][$options["image"]] = array(
                "image_url" => $image,
            );
        }

        $response = wp_remote_post(
            "https://api.abyssale.com/banner-builder/" . $templateId . "/generate",
            array(
                'method'      => 'POST',
                'timeout'     => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking'    => true,
                'data_format' => 'body',
                'headers'     => array(
                    "x-api-key" => $this->apiKey,
                    'Content-Type' => 'application/json',
                    "X-Referer" => "wordpress"
                ),
                'body'        => json_encode($requestBody),
                'cookies'     => array()
            )
        );

        if (is_wp_error($response) || $response["response"]["code"] != 200) {
            $body = json_decode($response["body"], true);

            $formattedResponse = array(
                "is_error" => true,
                "error" => $body["message"]
            );
        } else {
            $body = json_decode($response["body"], true);
            $formattedResponse = array(
                "is_error" => false,
                "file" => $body["image"]["url"]
            );
        }

        return $formattedResponse;
    }
}
