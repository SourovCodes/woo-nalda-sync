# License Manager API Documentation

**Version:** 1.0  
**Base URL:** `https://licence-manager.jonakyds.com/api/v1`

## Overview

The License Manager API provides endpoints for managing software license activation, validation, and status checking. All endpoints accept `POST` requests with JSON body and return JSON responses.

### Authentication

No token-based authentication is required. The license key itself serves as the authentication mechanism.

### Rate Limiting

All endpoints are rate-limited to **60 requests per minute** per IP address.

### Headers

| Header | Value | Required |
|--------|-------|----------|
| `Content-Type` | `application/json` | Yes |
| `Accept` | `application/json` | Recommended |

---

## Endpoints

### 1. Activate License

Activate a license key on a specific domain.

**Endpoint:** `POST /license/activate`

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `license_key` | string | Yes | The license key to activate (max 50 chars) |
| `domain` | string | Yes | The domain to activate the license on (max 255 chars) |
| `product_slug` | string | Yes | The product identifier/slug (max 100 chars) |

#### Example Request

```bash
curl -X POST https://licence-manager.jonakyds.com/api/v1/license/activate \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "XXXX-XXXX-XXXX-XXXX",
    "domain": "example.com",
    "product_slug": "my-product"
  }'
```

#### Success Response (200 OK)

```json
{
  "message": "License activated successfully.",
  "data": {
    "license_key": "XXXX-XXXX-XXXX-XXXX",
    "status": "active",
    "activated_at": "2026-01-01T12:00:00+00:00",
    "expires_at": "2027-01-01T12:00:00+00:00",
    "days_remaining": 365,
    "domain": "example.com"
  }
}
```

#### Error Response (422 Unprocessable Entity)

```json
{
  "message": "License key not found."
}
```

#### Possible Error Messages

| Message | Description |
|---------|-------------|
| `License key not found.` | The provided license key does not exist |
| `License is not valid for this product.` | The license belongs to a different product |
| `License has been revoked.` | The license has been revoked by an administrator |
| `License has expired.` | The license validity period has ended |
| `Invalid domain format.` | The domain format is not valid |
| `Maximum domain changes reached. Contact support.` | No more domain changes are allowed |

---

### 2. Validate License

Check if a license is valid and active on a specific domain.

**Endpoint:** `POST /license/validate`

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `license_key` | string | Yes | The license key to validate (max 50 chars) |
| `domain` | string | Yes | The domain to check activation for (max 255 chars) |
| `product_slug` | string | Yes | The product identifier/slug (max 100 chars) |

#### Example Request

```bash
curl -X POST https://licence-manager.jonakyds.com/api/v1/license/validate \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "XXXX-XXXX-XXXX-XXXX",
    "domain": "example.com",
    "product_slug": "my-product"
  }'
```

#### Success Response (200 OK)

```json
{
  "message": "License is valid.",
  "expires_at": "2027-01-01T12:00:00+00:00",
  "days_remaining": 365
}
```

#### Error Response (422 Unprocessable Entity)

```json
{
  "message": "License is not active on this domain."
}
```

#### Possible Error Messages

| Message | Description |
|---------|-------------|
| `License key not found.` | The provided license key does not exist |
| `License is not valid for this product.` | The license belongs to a different product |
| `License has been revoked.` | The license has been revoked by an administrator |
| `License has expired.` | The license validity period has ended |
| `Invalid domain format.` | The domain format is not valid |
| `License is not activated.` | The license has not been activated on any domain |
| `License is not active on this domain.` | The license is active on a different domain |

---

### 3. Deactivate License

Deactivate a license from a specific domain.

**Endpoint:** `POST /license/deactivate`

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `license_key` | string | Yes | The license key to deactivate (max 50 chars) |
| `domain` | string | Yes | The domain to deactivate from (max 255 chars) |
| `product_slug` | string | Yes | The product identifier/slug (max 100 chars) |
| `reason` | string | No | Optional reason for deactivation (max 255 chars) |

#### Example Request

```bash
curl -X POST https://licence-manager.jonakyds.com/api/v1/license/deactivate \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "XXXX-XXXX-XXXX-XXXX",
    "domain": "example.com",
    "product_slug": "my-product",
    "reason": "Migrating to new domain"
  }'
```

#### Success Response (200 OK)

```json
{
  "message": "License deactivated successfully."
}
```

#### Error Response (422 Unprocessable Entity)

```json
{
  "message": "No active license found on this domain."
}
```

#### Possible Error Messages

| Message | Description |
|---------|-------------|
| `License key not found.` | The provided license key does not exist |
| `License is not valid for this product.` | The license belongs to a different product |
| `Invalid domain format.` | The domain format is not valid |
| `No active license found on this domain.` | The license is not currently active on this domain |

---

### 4. License Status

Get detailed status information about a license.

**Endpoint:** `POST /license/status`

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `license_key` | string | Yes | The license key to check (max 50 chars) |

#### Example Request

```bash
curl -X POST https://licence-manager.jonakyds.com/api/v1/license/status \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "XXXX-XXXX-XXXX-XXXX"
  }'
```

#### Success Response (200 OK)

```json
{
  "data": {
    "license_key": "XXXX-XXXX-XXXX-XXXX",
    "status": "active",
    "product": {
      "name": "My Product",
      "slug": "my-product",
      "type": "plugin"
    },
    "customer_name": "John Doe",
    "activation": {
      "domain": "example.com",
      "activated_at": "2026-01-01T12:00:00+00:00"
    },
    "activated_at": "2026-01-01T12:00:00+00:00",
    "expires_at": "2027-01-01T12:00:00+00:00",
    "days_remaining": 365,
    "domain_changes": {
      "used": 1,
      "max": 3,
      "remaining": 2
    }
  }
}
```

#### Error Response (404 Not Found)

```json
{
  "message": "License key not found."
}
```

---

## Data Types

### License Status Values

| Status | Description |
|--------|-------------|
| `active` | License is active and valid |
| `expired` | License validity period has ended |
| `revoked` | License has been manually revoked |

### Domain Normalization

The API automatically normalizes domain inputs:

- Removes `http://` and `https://` protocols
- Removes `www.` prefix
- Removes paths, ports, and trailing slashes
- Converts to lowercase

**Examples:**
- `https://www.example.com/path` → `example.com`
- `WWW.EXAMPLE.COM:8080` → `example.com`
- `http://sub.example.com/` → `sub.example.com`

---

## Validation Errors

When request validation fails, the API returns a `422 Unprocessable Entity` response with details:

```json
{
  "message": "License key is required.",
  "errors": {
    "license_key": [
      "License key is required."
    ]
  }
}
```

---

## Integration Example (PHP)

```php
<?php

class LicenseClient
{
    private string $baseUrl;
    private string $productSlug;

    public function __construct(string $baseUrl, string $productSlug)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->productSlug = $productSlug;
    }

    public function activate(string $licenseKey, string $domain): array
    {
        return $this->request('/license/activate', [
            'license_key' => $licenseKey,
            'domain' => $domain,
            'product_slug' => $this->productSlug,
        ]);
    }

    public function validate(string $licenseKey, string $domain): array
    {
        return $this->request('/license/validate', [
            'license_key' => $licenseKey,
            'domain' => $domain,
            'product_slug' => $this->productSlug,
        ]);
    }

    public function deactivate(string $licenseKey, string $domain, ?string $reason = null): array
    {
        return $this->request('/license/deactivate', [
            'license_key' => $licenseKey,
            'domain' => $domain,
            'product_slug' => $this->productSlug,
            'reason' => $reason,
        ]);
    }

    public function status(string $licenseKey): array
    {
        return $this->request('/license/status', [
            'license_key' => $licenseKey,
        ]);
    }

    private function request(string $endpoint, array $data): array
    {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}

// Usage
$client = new LicenseClient('https://licence-manager.jonakyds.com/api/v1', 'my-product');

// Activate license
$result = $client->activate('XXXX-XXXX-XXXX-XXXX', 'example.com');
if (isset($result['data'])) {
    echo "License activated! Expires: " . $result['data']['expires_at'];
}

// Validate license
$result = $client->validate('XXXX-XXXX-XXXX-XXXX', 'example.com');
if (isset($result['expires_at'])) {
    echo "License valid! Days remaining: " . $result['days_remaining'];
}
```

---

## WordPress Integration Example

```php
<?php
/**
 * Plugin Name: My Licensed Plugin
 */

class MyPluginLicense
{
    private const API_URL = 'https://licence-manager.jonakyds.com/api/v1';
    private const PRODUCT_SLUG = 'my-plugin';

    public function validate(): bool
    {
        $licenseKey = get_option('my_plugin_license_key');
        if (empty($licenseKey)) {
            return false;
        }

        $response = wp_remote_post(self::API_URL . '/license/validate', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'license_key' => $licenseKey,
                'domain' => parse_url(home_url(), PHP_URL_HOST),
                'product_slug' => self::PRODUCT_SLUG,
            ]),
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);
        return $status === 200;
    }

    public function activate(string $licenseKey): array
    {
        $response = wp_remote_post(self::API_URL . '/license/activate', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'license_key' => $licenseKey,
                'domain' => parse_url(home_url(), PHP_URL_HOST),
                'product_slug' => self::PRODUCT_SLUG,
            ]),
        ]);

        if (is_wp_error($response)) {
            return ['message' => $response->get_error_message()];
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}
```
