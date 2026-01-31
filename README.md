# Research Access Gate v2.0.2

**Site-wide login/registration modal for research chemical e-commerce with 21+ Terms acceptance.**

Built for **Premier Bio Labs** following **January 2026 WordPress best practices**.

---

## 🚀 What's New in v2.0.2

This release adds enterprise-grade security features:

| Feature | Description |
|---------|-------------|
| **Rate Limiting** | Brute force protection for login/register endpoints |
| **CAPTCHA Integration** | Support for reCAPTCHA v2/v3, hCaptcha, Cloudflare Turnstile |
| **Security Headers** | Content Security Policy (CSP) and other hardening headers |
| **Configurable License Salt** | Move secret to wp-config.php for better security |

---

## 📋 Requirements

- **WordPress:** 6.4+
- **PHP:** 8.0+ (8.3 recommended)
- **WooCommerce:** 8.0+ (optional, for checkout integration)
- **MySQL:** 8.0+ or MariaDB 10.6+

---

## 🔧 Installation

### Standard Installation

1. Upload `research-access-gate` folder to `/wp-content/plugins/`
2. Activate via **Plugins** menu
3. Configure at **Settings → Access Gate**

### MU-Plugin Installation (Recommended for Caching)

If using WP Rocket, LiteSpeed Cache, or similar:

1. Go to **Settings → Access Gate**
2. Click **"Install MU-Plugin"** in the sidebar
3. This ensures the gate loads before cached pages

---

## 🔒 Security Configuration

### License Salt (Recommended)

For production environments, define your license salt in `wp-config.php`:

```php
define('RAG_LICENSE_SALT', 'your-unique-random-string-here');
```

Generate a secure salt using: `wp_generate_password(64, true, true)`

### Rate Limiting

Rate limiting is enabled by default. Configure via `wp-config.php`:

```php
// Max failed attempts before lockout (default: 5)
define('RAG_RATE_LIMIT_ATTEMPTS', 5);

// Lockout duration in seconds (default: 900 = 15 minutes)
define('RAG_RATE_LIMIT_LOCKOUT', 900);

// Time before attempts reset (default: 3600 = 1 hour)
define('RAG_RATE_LIMIT_DECAY', 3600);

// Disable rate limiting entirely (not recommended)
define('RAG_RATE_LIMIT_ENABLED', true);
```

### CAPTCHA Integration

Supports multiple providers. Configure in **Settings → Access Gate → Security** or via `wp-config.php`:

```php
// CAPTCHA keys (recommended for security)
define('RAG_CAPTCHA_SITE_KEY', 'your-site-key');
define('RAG_CAPTCHA_SECRET_KEY', 'your-secret-key');
```

**Supported Providers:**
- Google reCAPTCHA v2 (Checkbox)
- Google reCAPTCHA v3 (Invisible)
- hCaptcha
- Cloudflare Turnstile

### Security Headers

Enable Content Security Policy and other security headers in settings. Start with **Report-Only mode** to test before enforcing.

**Headers Added:**
- `Content-Security-Policy` - Prevents XSS and data injection
- `X-Frame-Options: SAMEORIGIN` - Prevents clickjacking
- `X-Content-Type-Options: nosniff` - Prevents MIME sniffing
- `X-XSS-Protection: 1; mode=block` - Legacy XSS protection
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy` - Disables unnecessary browser features

---

## ⚙️ Configuration

### General Settings

| Setting | Description |
|---------|-------------|
| **Enable Gate** | Toggle login/register modal for non-logged-in users |
| **Company Name** | Used in Terms content via `{{company_name}}` placeholder |
| **Logo URL** | Displayed at top of modal |

### Modal Content

- **Modal Title:** Header text (e.g., "Research Access Only")
- **Modal Description:** Explanatory text below title
- **Terms Title:** Section header in Terms box
- **Terms Content:** Full Terms text (supports HTML, `{{company_name}}`)
- **Checkbox Text:** Agreement confirmation text

### Registration Fields

- **Require Phone:** Make phone number mandatory
- **Require Business Type:** Make business dropdown mandatory
- **Business Types:** One per line (e.g., Researcher, Distributor, Clinic)

### Page Settings

- **Terms Page Slug:** Fallback URL if WooCommerce T&C not set
- **Contact Page Slug:** "Need help?" link destination
- **Excluded Pages:** Page slugs where gate won't show (one per line)

### Colors

Customize modal appearance with color pickers:
- Primary / Secondary (gradients)
- Background / Text / Muted / Error

---

## 🔌 REST API Endpoints

v2.0 uses WordPress REST API instead of admin-ajax for 15-20% faster response times.

### Public Endpoints

```
POST /wp-json/rag/v1/login
POST /wp-json/rag/v1/register
```

**Rate Limiting:** Both endpoints are protected by rate limiting. After 5 failed attempts, the IP is locked out for 15 minutes.

### Admin Endpoints (requires `manage_options`)

```
POST /wp-json/rag/v1/mu-plugin/install
DELETE /wp-json/rag/v1/mu-plugin/remove
POST /wp-json/rag/v1/license/activate
DELETE /wp-json/rag/v1/license/deactivate
GET /wp-json/rag/v1/license/status
```

---

## 🏗️ Architecture

```
research-access-gate/
├── research-access-gate.php    # Main plugin file
├── uninstall.php               # Cleanup on deletion
├── includes/
│   ├── Plugin.php              # Main singleton class
│   ├── Settings.php            # Settings with transient cache
│   ├── RestApi.php             # REST API handlers
│   ├── Frontend.php            # Modal rendering
│   ├── Admin.php               # Settings page
│   ├── MuPlugin.php            # MU-Plugin manager
│   ├── Helpers.php             # Utility functions
│   ├── License.php             # License validation
│   ├── RateLimiter.php         # Brute force protection
│   ├── Captcha.php             # CAPTCHA integration
│   └── SecurityHeaders.php     # CSP and security headers
├── templates/
│   ├── modal.php               # Frontend modal HTML + JS
│   ├── styles.php              # Dynamic CSS
│   └── admin-page.php          # Admin settings page
└── assets/
    ├── admin.css               # Admin styles
    └── admin.js                # Admin JavaScript
```

### Namespace

```php
namespace PremierBioLabs\ResearchAccessGate;
```

---

## 📝 WooCommerce Integration

### HPOS Compatibility

Declares compatibility with:
- `custom_order_tables` (High-Performance Order Storage)
- `cart_checkout_blocks` (Block-based checkout)

### Checkout Terms

- Modifies checkout Terms checkbox text
- Validates Terms acceptance before order
- Stores acceptance metadata on orders:
  - `rag_terms_accepted`: yes/no
  - `rag_terms_accepted_at`: timestamp
  - `rag_terms_accepted_ip`: client IP
  - `rag_terms_version`: plugin version

---

## 🔒 Security Features

### v2.0.2 Security Enhancements

| Feature | Protection |
|---------|------------|
| **Rate Limiting** | Prevents brute force attacks on login/register |
| **CAPTCHA** | Blocks automated bot submissions |
| **CSP Headers** | Prevents XSS and code injection |
| **Open Redirect Fix** | Validates all redirect URLs |
| **Path Traversal Fix** | Validates MU-Plugin installation paths |

### Core Security (All Versions)

- **CSRF Protection:** Nonce verification on all forms
- **Input Sanitization:** All user input sanitized
- **Output Escaping:** All output escaped
- **Capability Checks:** Admin endpoints require `manage_options`
- **IP Tracking:** Client IP stored for audit trail (Cloudflare-aware)

---

## 🎛️ Filters & Hooks

### Rate Limiting

```php
// Customize rate limiter configuration
add_filter('rag_rate_limiter_config', function($config) {
    $config['max_attempts'] = 3;  // Stricter limit
    return $config;
});
```

### Security Headers

```php
// Add custom CSP directives
add_filter('rag_csp_directives', function($directives) {
    $directives['script-src'][] = 'https://your-cdn.com';
    return $directives;
});

// Customize Permissions Policy
add_filter('rag_permissions_policy', function($policies) {
    $policies['fullscreen'] = '(self)';
    return $policies;
});
```

### IP Detection

```php
// Customize trusted proxy headers
add_filter('rag_trusted_proxy_headers', function($headers) {
    // Only trust Cloudflare
    return ['HTTP_CF_CONNECTING_IP'];
});
```

### CAPTCHA

```php
// Fail open if CAPTCHA service is unavailable
add_filter('rag_captcha_fail_open', '__return_true');
```

---

## 🧪 Testing Checklist

Before deploying to production:

- [ ] Enable gate and visit site logged out
- [ ] Test login with valid/invalid credentials
- [ ] Test registration with all required fields
- [ ] Verify Terms checkbox is enforced
- [ ] Check excluded pages are accessible
- [ ] Test WooCommerce checkout Terms
- [ ] Verify colors match your branding
- [ ] Test on mobile devices
- [ ] If using caching, install MU-Plugin
- [ ] Test rate limiting (5 failed logins = lockout)
- [ ] Test CAPTCHA if enabled
- [ ] Enable CSP in report-only mode first

---

## 🐛 Troubleshooting

### Gate not showing

1. Check **Settings → Access Gate → Enable Gate** is ON
2. Verify you're logged out
3. Clear page cache
4. Check excluded pages list

### REST API errors

1. Verify permalink structure is not "Plain"
2. Check for REST API blocking plugins/rules
3. Review server error logs

### Rate limiting issues

1. Check if IP is locked out (wait 15 minutes or clear transients)
2. Verify `RAG_RATE_LIMIT_ENABLED` is not set to false
3. Check object cache if using Redis/Memcached

### CAPTCHA not working

1. Verify site key and secret key are correct
2. Check browser console for JavaScript errors
3. Ensure CSP allows CAPTCHA provider domains

### CSP blocking resources

1. Start with **Report-Only mode** enabled
2. Check browser console for CSP violations
3. Add necessary domains via `rag_csp_directives` filter

---

## 📊 PHP 8.0+ Features Used

- `declare(strict_types=1)` - Strict type checking
- Typed properties - `private Settings $settings`
- Constructor property promotion - `public function __construct(private readonly Settings $settings)`
- Named arguments - Where beneficial
- Match expressions - `match ($key) { ... }`
- Null-safe operator - `$result?->value`
- Union types - `string|null`

---

## 🔄 Upgrade Path

### From v2.0.1

1. Upload new plugin files
2. Deactivate and reactivate plugin
3. Configure new security settings in admin
4. Add `RAG_LICENSE_SALT` to wp-config.php (recommended)

### From v1.x

1. Backup your settings (screenshot or export)
2. Deactivate v1.x
3. Delete v1.x files
4. Upload v2.0.2
5. Activate v2.0.2
6. Verify settings migrated (same option key)
7. Re-install MU-Plugin if used

---

## 📜 Changelog

### 2.0.2 (January 2026) - Security Enhancement Release
- **NEW:** Rate limiting for login/register endpoints (brute force protection)
- **NEW:** CAPTCHA integration (reCAPTCHA v2/v3, hCaptcha, Turnstile)
- **NEW:** Content Security Policy (CSP) headers
- **NEW:** Security headers (X-Frame-Options, X-Content-Type-Options, etc.)
- **NEW:** Configurable license salt via `RAG_LICENSE_SALT` constant
- **NEW:** `rag_rate_limiter_config` filter for customization
- **NEW:** `rag_csp_directives` filter for CSP customization
- **NEW:** `rag_permissions_policy` filter for Permissions Policy
- Added RateLimiter, Captcha, and SecurityHeaders classes

### 2.0.1 (January 2026) - Security Update
- **SECURITY:** Fixed open redirect vulnerability in login/register endpoints
- **SECURITY:** Fixed XSS vulnerability in admin settings page
- **SECURITY:** Added proper REQUEST_URI sanitization in frontend
- **SECURITY:** Added path validation for MU-Plugin installation
- **SECURITY:** Improved IP address validation with filter support
- Added `uninstall.php` for proper cleanup on plugin deletion
- Improved output escaping for Terms content
- Added `rag_trusted_proxy_headers` filter for custom proxy configurations

### 2.0.0 (January 2026)
- Complete rewrite with 2026 WordPress standards
- Switched from admin-ajax to REST API
- Added PSR-4 namespacing
- PHP 8.0+ with typed properties
- Transient caching for settings
- WooCommerce HPOS compatibility declaration
- Conditional asset loading

### 1.0.0 (Initial)
- Original release

---

## 📄 License

GPL v2 or later

---

**Built with ❤️ by Premier Bio Labs**

*For research purposes only. Not for human consumption.*
