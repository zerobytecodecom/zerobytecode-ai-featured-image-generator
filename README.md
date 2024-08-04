# zerobytecode-ai-featured-image-generator
 One-click generate and set WordPress post featured image using OpenAI's Dall-E 3.

---
This plugin can help you generate a featured image directly from the post editor screen just with one click.

The plugin has a settings options with two fields:
- OpenAI API Key
- Content Template (the prompt)

By default, the plugin will use the post's excerpt as part of the prompt. To change the user input from post's excerpt, just change `$excerpt` in the following line to whatever you like, whether static or dynamic value like post's title, etc.

```
// Generate image prompt using OpenAI chat completions
    $prompt_response = wp_remote_post(
        'https://api.openai.com/v1/chat/completions',
        [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => sanitize_textarea_field($content_template),
                    ],
                    [
                        'role' => 'user',
                        'content' => sanitize_text_field($excerpt),
                    ],
                ],
            ]),
            'timeout' => 60,
        ]
    );
```

You can read the complete guide to [generate featured image using Dall-E 3](https://zerobytecode.com/create-a-wordpress-plugin-to-generate-featured-images-using-ai/).
