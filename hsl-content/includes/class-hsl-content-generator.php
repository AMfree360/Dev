<?php
if (!defined('ABSPATH')) {
    exit;
}

class HSL_Content_Generator {
    private $api_key;

    public function __construct() {
        $this->api_key = get_option('hsl_content_openai_key', '');
    }

    public function generate_article($keyword) {
        if (empty($this->api_key)) {
            return false; // No API key set
        }

        $prompt = "Write a detailed, informative, and engaging article about $keyword in the style of WikiHow.";
        $response = $this->call_openai_api($prompt);

        if ($response && isset($response['choices'][0]['text'])) {
            return $response['choices'][0]['text'];
        }
        return false;
    }

    private function call_openai_api($prompt) {
        $url = 'https://api.openai.com/v1/completions';
        $args = [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body' => json_encode([
                'model'      => 'text-davinci-003', // Use GPT-3.5 or GPT-4
                'prompt'     => $prompt,
                'max_tokens' => 1000,
            ]),
        ];

        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) {
            return false;
        }
        return json_decode($response['body'], true);
    }
}
