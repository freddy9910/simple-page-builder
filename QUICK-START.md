# Quick Setup Guide for Simple Page Builder

This is a quick start guide to get your Simple Page Builder plugin up and running in minutes.

## 🚀 Installation Steps

### 1. Install Plugin
1. Upload the `simple-page-builder` folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress admin under Plugins
3. Check that database tables are created automatically

### 2. Initial Configuration  
1. Go to **Tools → Page Builder**
2. Click the **Settings** tab
3. Configure basic settings:
   - ✅ Enable API access
   - Set rate limit (default: 100 requests/hour)
   - Enter webhook URL (optional)
   - Generate webhook secret (auto-created)

### 3. Generate Your First API Key
1. Go to **API Keys** tab
2. Click **Generate New API Key**
3. Enter name: "Test Key"
4. Choose expiration: 90 days
5. Click **Generate**
6. **IMPORTANT**: Copy and save the key immediately!

## ⚡ Quick Test

### Test the API with cURL:

```bash
curl -X POST "https://yourdomain.com/wp-json/pagebuilder/v1/create-pages" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY_HERE" \
  -d '{
    "pages": [
      {
        "title": "Test Page",
        "content": "<p>This page was created via API!</p>",
        "status": "publish"
      }
    ]
  }'
```

### Expected Response:
```json
{
  "success": true,
  "request_id": "req_abc123xyz", 
  "total_requested": 1,
  "total_created": 1,
  "total_errors": 0,
  "created_pages": [
    {
      "id": 123,
      "title": "Test Page",
      "url": "https://yourdomain.com/test-page",
      "status": "publish"
    }
  ]
}
```

## 🔍 Verify Setup

### Check in WordPress Admin:
1. **API Keys tab**: See your key with usage stats
2. **Activity Log tab**: View the API request 
3. **Created Pages tab**: See your new page
4. **Frontend**: Visit the created page URL

### Health Check:
```bash
curl "https://yourdomain.com/wp-json/pagebuilder/v1/health"
```

Should return:
```json
{
  "status": "healthy",
  "version": "1.0.0",
  "api_enabled": true
}
```

## 🔧 Troubleshooting

### Common Issues:

**❌ 401 Unauthorized**
- Check API key in header: `X-API-Key: your_key`
- Verify key hasn't expired or been revoked

**❌ 503 Service Unavailable** 
- API may be disabled in Settings
- Check Settings tab → Enable API access

**❌ 429 Too Many Requests**
- Rate limit exceeded
- Wait or increase limit in Settings

**❌ 400 Bad Request**
- Check JSON format
- Ensure "title" field is included
- Verify page data structure

### Debug Mode:
Add to wp-config.php:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 📱 Integration Examples

### PHP Example:
```php
$api_key = 'your_api_key_here';
$url = 'https://yourdomain.com/wp-json/pagebuilder/v1/create-pages';

$data = [
    'pages' => [
        [
            'title' => 'New Page',
            'content' => '<p>Page content here</p>',
            'status' => 'publish'
        ]
    ]
];

$response = wp_remote_post($url, [
    'headers' => [
        'Content-Type' => 'application/json',
        'X-API-Key' => $api_key
    ],
    'body' => wp_json_encode($data)
]);
```

### JavaScript Example:
```javascript
const apiKey = 'your_api_key_here';
const url = 'https://yourdomain.com/wp-json/pagebuilder/v1/create-pages';

const data = {
    pages: [
        {
            title: 'New Page',
            content: '<p>Page content here</p>',
            status: 'publish'
        }
    ]
};

fetch(url, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-API-Key': apiKey
    },
    body: JSON.stringify(data)
})
.then(response => response.json())
.then(data => console.log(data));
```

## 🎯 Next Steps

1. **Webhook Setup**: Configure webhook URL to receive notifications
2. **Multiple API Keys**: Generate different keys for different applications  
3. **Rate Limiting**: Adjust limits based on your needs
4. **Monitoring**: Use Activity Log to track usage
5. **Integration**: Connect your apps using the API

## 📞 Need Help?

- **Documentation**: Check Tools → Page Builder → Documentation tab
- **Activity Logs**: Review API request logs for errors
- **Support**: Contact wordpress@thewebops.com

Happy building! 🚀