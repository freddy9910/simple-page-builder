# Simple Page Builder

A powerful WordPress plugin that enables bulk page creation via a secure REST API endpoint, accessible from external applications with advanced authentication and webhook notifications.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

## 🚀 Features

### ✨ Core Features
- **🔐 Secure API Key Authentication** - Production-ready API key system with proper hashing
- **📝 Bulk Page Creation** - Create multiple WordPress pages in a single API request
- **🔔 Webhook Notifications** - Real-time notifications with signature verification
- **📊 Comprehensive Admin Interface** - Full management dashboard with analytics
- **🛡️ Rate Limiting** - Configurable request limits to prevent abuse
- **📈 Activity Logging** - Detailed request logging and monitoring
- **📚 Built-in Documentation** - Complete API documentation within WordPress admin

### 🔒 Security Features
- **API Key Hashing** - Keys stored securely using WordPress password hashing
- **Rate Limiting** - Configurable requests per hour per API key
- **Request Logging** - Complete audit trail of all API requests
- **Webhook Signatures** - HMAC-SHA256 signed webhook payloads
- **IP Tracking** - Monitor request sources for security analysis

### 📱 External Access
- **REST API Endpoint** - Accessible from any external application
- **No WordPress Login Required** - API key authentication only
- **Cross-Origin Ready** - CORS-compatible for web applications
- **Mobile App Friendly** - Perfect for mobile app integrations

## 📋 Requirements

- **WordPress:** 5.0 or higher
- **PHP:** 7.4 or higher
- **Database:** MySQL 5.6+ or MariaDB 10.1+
- **Permissions:** Administrator role for plugin management

## 🔧 Installation

### 1. Download and Install

1. Download or clone this repository
2. Upload the plugin folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin interface

```bash
# Clone the repository
git clone https://github.com/freddy9910/simple-page-builder.git
cd simple-page-builder
```

### 2. Initial Setup

1. Navigate to **Tools → Page Builder** in WordPress admin
2. Go to the **Settings** tab
3. Configure your webhook URL (optional)
4. Set rate limiting preferences
5. Generate your first API key in the **API Keys** tab

## 🔑 API Key Management

### Generating API Keys

1. Go to **Tools → Page Builder → API Keys**
2. Click **Generate New API Key**
3. Enter a friendly name (e.g., "Production Server", "Mobile App")
4. Choose expiration period (30, 60, 90 days, or never)
5. **Save the API key immediately** - it cannot be retrieved later

### API Key Features

- **Secure Storage**: Keys are hashed using WordPress password hashing
- **Preview Only**: Only first 8 characters shown after generation
- **Usage Tracking**: Monitor request count and last used date
- **Instant Revocation**: Revoke keys immediately when needed
- **Expiration Support**: Set automatic expiration dates

## 📡 API Usage

### Base URL
```
https://yourdomain.com/wp-json/pagebuilder/v1/
```

### Authentication
Include your API key in the request header:
```
X-API-Key: your_api_key_here
```

### Create Pages Endpoint

**URL:** `POST /wp-json/pagebuilder/v1/create-pages`

#### Request Example

```bash
curl -X POST "https://yourdomain.com/wp-json/pagebuilder/v1/create-pages" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY_HERE" \
  -d '{
    "pages": [
      {
        "title": "About Us",
        "content": "<p>This is our about page with <strong>HTML content</strong>.</p>",
        "slug": "about-us",
        "status": "publish"
      },
      {
        "title": "Contact",
        "content": "<p>Contact us at contact@example.com</p>",
        "slug": "contact", 
        "status": "draft",
        "parent_id": 123
      }
    ],
    "webhook_url": "https://example.com/webhook"
  }'
```

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `pages` | array | Yes | Array of page objects to create |
| `pages[].title` | string | Yes | Page title |
| `pages[].content` | string | No | Page content (HTML allowed) |
| `pages[].slug` | string | No | Custom URL slug |
| `pages[].status` | string | No | Page status (`publish`, `draft`, `private`) |
| `pages[].parent_id` | integer | No | Parent page ID for hierarchical pages |
| `webhook_url` | string | No | Override default webhook URL for this request |

#### Response Example

```json
{
  "success": true,
  "request_id": "req_abc123xyz",
  "total_requested": 2,
  "total_created": 2,
  "total_errors": 0,
  "created_pages": [
    {
      "id": 123,
      "title": "About Us",
      "url": "https://yourdomain.com/about-us",
      "status": "publish"
    },
    {
      "id": 124,
      "title": "Contact", 
      "url": "https://yourdomain.com/contact",
      "status": "draft"
    }
  ],
  "errors": [],
  "processing_time": 0.245
}
```

### Health Check Endpoint

**URL:** `GET /wp-json/pagebuilder/v1/health`

```bash
curl "https://yourdomain.com/wp-json/pagebuilder/v1/health"
```

Response:
```json
{
  "status": "healthy",
  "version": "1.0.0",
  "timestamp": "2025-11-24T14:30:00+00:00",
  "api_enabled": true
}
```

## 🔔 Webhook Notifications

### Setup

1. Go to **Tools → Page Builder → Settings**
2. Enter your webhook URL
3. Configure webhook secret (auto-generated)
4. Test the webhook connection

### Webhook Payload

When pages are successfully created, a webhook notification is sent:

```json
{
  "event": "pages_created",
  "timestamp": "2025-11-24T14:30:00Z",
  "request_id": "req_abc123xyz",
  "api_key_name": "Production Server",
  "total_pages": 2,
  "pages": [
    {
      "id": 123,
      "title": "About Us",
      "url": "https://yourdomain.com/about-us",
      "status": "publish"
    },
    {
      "id": 124,
      "title": "Contact",
      "url": "https://yourdomain.com/contact", 
      "status": "draft"
    }
  ]
}
```

### Webhook Security

Webhooks include a signature header for verification:

```
X-Webhook-Signature: sha256=hash_of_payload
```

#### Verify Signature (PHP Example)

```php
function verify_webhook_signature($payload, $signature, $secret) {
    $expected_signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected_signature, $signature);
}

// Usage
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$secret = 'your_webhook_secret';

if (verify_webhook_signature($payload, $signature, $secret)) {
    // Process webhook
    $data = json_decode($payload, true);
} else {
    http_response_code(401);
    exit('Invalid signature');
}
```

#### Verify Signature (Node.js Example)

```javascript
const crypto = require('crypto');

function verifyWebhookSignature(payload, signature, secret) {
    const expectedSignature = 'sha256=' + crypto
        .createHmac('sha256', secret)
        .update(payload)
        .digest('hex');
        
    return crypto.timingSafeEqual(
        Buffer.from(signature),
        Buffer.from(expectedSignature)
    );
}

// Usage in Express.js
app.post('/webhook', (req, res) => {
    const payload = JSON.stringify(req.body);
    const signature = req.headers['x-webhook-signature'];
    const secret = 'your_webhook_secret';
    
    if (verifyWebhookSignature(payload, signature, secret)) {
        // Process webhook
        console.log('Pages created:', req.body.pages);
        res.status(200).send('OK');
    } else {
        res.status(401).send('Invalid signature');
    }
});
```

## 📊 Admin Dashboard

### API Keys Tab
- Generate new API keys with custom names and expiration
- View all existing keys with usage statistics
- Revoke keys instantly
- Track last used date and request count

### Activity Log Tab
- Real-time API request monitoring
- Success/failure statistics
- Response time analytics
- Export logs as CSV
- Filter by date range, status, or API key

### Created Pages Tab
- View all pages created via API
- Direct links to created pages
- Creation timestamps and API key attribution
- Page status tracking

### Settings Tab
- Enable/disable API access globally
- Configure rate limiting (requests per hour)
- Set webhook URL and secret
- Test webhook connectivity
- Default expiration settings

### Documentation Tab
- Complete API documentation
- Live endpoint URLs
- Copy-paste cURL examples
- Parameter reference
- Response examples

## ⚡ Rate Limiting

### Configuration
- Set in **Settings** tab (default: 100 requests/hour per API key)
- Set to 0 for unlimited requests
- Applied per API key individually

### Response Headers
Rate limit information included in all API responses:

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 85
X-RateLimit-Reset: 1732467600
```

### Rate Limit Exceeded Response
```json
{
  "code": "rate_limit_exceeded",
  "message": "Rate limit exceeded.",
  "data": {
    "status": 429
  }
}
```

## 📈 Monitoring & Analytics

### Request Statistics
- Total requests (last 30 days)
- Success/failure rates
- Average response time
- Total pages created
- Daily request trends

### Activity Logging
- All API requests logged automatically
- IP address tracking
- User agent information
- Request/response data
- Error details
- Performance metrics

### Data Export
- Export activity logs as CSV
- Filter by date range, status, or API key
- Perfect for analytics and reporting

## 🔍 Error Handling

### Common HTTP Status Codes

| Status | Description |
|--------|-------------|
| `200` | Success - Pages created successfully |
| `400` | Bad Request - Invalid request data |
| `401` | Unauthorized - Invalid or missing API key |
| `429` | Too Many Requests - Rate limit exceeded |
| `503` | Service Unavailable - API temporarily disabled |

### Error Response Format
```json
{
  "code": "error_code",
  "message": "Human readable error message",
  "data": {
    "status": 400,
    "additional_info": "Extra details"
  }
}
```

### Common Error Codes

- `missing_api_key` - API key not provided in headers
- `invalid_api_key` - API key not found or revoked
- `rate_limit_exceeded` - Too many requests
- `api_disabled` - API access disabled in settings
- `invalid_pages_data` - Invalid page data format

## 🛠️ Development

### Plugin Structure
```
simple-page-builder/
├── simple-page-builder.php     # Main plugin file
├── includes/                   # Core classes
│   ├── class-spb-database.php     # Database management
│   ├── class-spb-api-keys.php     # API key management
│   ├── class-spb-rest-api.php     # REST API endpoints
│   ├── class-spb-webhook.php      # Webhook system
│   ├── class-spb-logger.php       # Request logging
│   └── class-spb-rate-limiter.php # Rate limiting
├── admin/                      # Admin interface
│   └── class-spb-admin.php        # Admin dashboard
├── assets/                     # Static files
│   ├── css/admin.css              # Admin styles
│   └── js/admin.js                # Admin JavaScript
└── README.md                   # This file
```

### Database Schema

The plugin creates 4 custom tables:

1. **`wp_spb_api_keys`** - API key storage with hashing
2. **`wp_spb_api_logs`** - Request activity logging  
3. **`wp_spb_created_pages`** - Pages created via API
4. **`wp_spb_webhook_logs`** - Webhook delivery logs

### WordPress Hooks

#### Actions
- `spb_send_webhook` - Scheduled webhook delivery
- `spb_cleanup_logs` - Daily log cleanup

#### Filters
- `spb_api_key_expiration_days` - Default API key expiration
- `spb_rate_limit_per_hour` - Rate limit override
- `spb_webhook_retry_attempts` - Webhook retry count

### Adding Custom Permissions

```php
// Example: Add custom permission for API key creation
add_filter('spb_api_key_permissions', function($permissions) {
    $permissions[] = 'custom_permission';
    return $permissions;
});
```

## 🧪 Testing

### Manual Testing

1. **API Key Generation**
   - Generate keys with different expiration settings
   - Verify keys are hashed in database
   - Test key revocation

2. **API Endpoint Testing**
   - Test with valid/invalid API keys
   - Test rate limiting
   - Test with various page data formats

3. **Webhook Testing**
   - Use webhook test feature in admin
   - Verify signature generation
   - Test retry logic with failing endpoints

### Integration Testing

```bash
# Test page creation
curl -X POST "https://yourdomain.com/wp-json/pagebuilder/v1/create-pages" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: test_key_here" \
  -d '{"pages":[{"title":"Test Page","content":"Test content"}]}'

# Test health check
curl "https://yourdomain.com/wp-json/pagebuilder/v1/health"
```

## 🔐 Security Considerations

### Best Practices

1. **API Key Security**
   - Store API keys securely on client side
   - Use environment variables, never hardcode
   - Rotate keys regularly
   - Set appropriate expiration dates

2. **Server Security**
   - Use HTTPS only for API requests
   - Configure proper CORS headers
   - Monitor for unusual request patterns
   - Set reasonable rate limits

3. **Webhook Security**
   - Always verify webhook signatures
   - Use HTTPS endpoints only
   - Implement proper error handling
   - Log webhook delivery attempts

### Security Headers

The plugin automatically sets security headers:

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1732467600
```

## 🐛 Troubleshooting

### Common Issues

#### API Key Not Working
1. Check if API is enabled in Settings
2. Verify API key hasn't expired
3. Check if key was revoked
4. Ensure correct header format: `X-API-Key: your_key`

#### Rate Limit Issues
1. Check current rate limit in Settings
2. Wait for rate limit reset (shown in response headers)
3. Consider increasing rate limit
4. Use multiple API keys for higher throughput

#### Webhook Not Receiving
1. Test webhook URL in Settings tab
2. Check webhook URL accessibility
3. Verify webhook secret configuration
4. Review webhook logs for delivery attempts

#### Pages Not Created
1. Check API request format
2. Verify required fields (title)
3. Review activity logs for errors
4. Check WordPress user permissions

### Debug Mode

Enable WordPress debug mode for detailed error logging:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Log Locations

- **WordPress Logs**: `/wp-content/debug.log`
- **Plugin Logs**: Admin dashboard → Activity Log tab
- **Webhook Logs**: Admin dashboard → Activity Log tab

## 📞 Support

### Getting Help

1. **Documentation**: Check the built-in documentation tab
2. **Activity Logs**: Review logs for error details  
3. **WordPress Forums**: Search WordPress.org support forums
4. **GitHub Issues**: Create an issue for bugs/features

### Reporting Issues

When reporting issues, include:

- WordPress version
- PHP version  
- Plugin version
- Error messages
- Steps to reproduce
- Sample API requests

## 🔄 Changelog

### Version 1.0.0 (2025-11-24)
- Initial release
- API key authentication system
- Bulk page creation endpoint
- Webhook notification system
- Comprehensive admin dashboard
- Rate limiting and security features
- Complete documentation

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## 🤝 Contributing

We welcome contributions! Please feel free to submit issues and pull requests.

### Development Setup

1. Clone the repository
2. Install on a WordPress development site
3. Enable WP_DEBUG for development
4. Follow WordPress coding standards
5. Test thoroughly before submitting

### Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- Use proper sanitization and validation
- Document all functions and classes
- Write meaningful commit messages

---

**Made with ❤️ for WordPress developers who need powerful API integration**

For questions or support, contact: wordpress@thewebops.com