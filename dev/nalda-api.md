# Nalda API Documentation

**Version:** 1.0  
**Base URL:** `https://licence-manager.jonakyds.com/api/v1`

## Overview

The Nalda API provides endpoints for SFTP credential validation and CSV file upload management. CSV upload requests are processed asynchronously and uploaded to the specified SFTP server.

### Authentication

All Nalda API endpoints require license key authentication via the `X-License-Key` header.

| Header | Value | Required |
|--------|-------|----------|
| `X-License-Key` | Your valid license key | Yes |
| `Content-Type` | `multipart/form-data` (for uploads) or `application/json` | Yes |
| `Accept` | `application/json` | Recommended |

### Rate Limiting

All endpoints are rate-limited to **60 requests per minute** per IP address.

---

## Endpoints

### 1. Validate SFTP Credentials

Test SFTP connection credentials without performing any file operations. Useful for validating credentials before submitting a CSV upload request.

**Endpoint:** `POST /sftp/validate`

#### Request Headers

| Header | Value | Required | Description |
|--------|-------|----------|-------------|
| `X-License-Key` | string | Yes | A valid, active license key |
| `Content-Type` | `application/json` | Yes | Request content type |

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `sftp_host` | string | Yes | The SFTP server hostname or IP (max 255 chars) |
| `sftp_port` | integer | Yes | The SFTP server port (1-65535) |
| `sftp_username` | string | Yes | The SFTP username (max 255 chars) |
| `sftp_password` | string | Yes | The SFTP password (max 255 chars) |

#### Example Request

```bash
curl -X POST https://licence-manager.jonakyds.com/api/v1/sftp/validate \
  -H "Content-Type: application/json" \
  -H "X-License-Key: XXXX-XXXX-XXXX-XXXX" \
  -d '{
    "sftp_host": "sftp.example.com",
    "sftp_port": 22,
    "sftp_username": "myuser",
    "sftp_password": "mypassword"
  }'
```

#### Success Response (200 OK)

```json
{
  "message": "SFTP credentials are valid.",
  "data": {
    "host": "sftp.example.com",
    "port": 22,
    "username": "myuser"
  }
}
```

#### Error Responses

##### Authentication Error (401 Unauthorized)

```json
{
  "message": "X-License-Key header is required."
}
```

```json
{
  "message": "Invalid license key."
}
```

##### License Error (403 Forbidden)

```json
{
  "message": "License has expired."
}
```

```json
{
  "message": "License is not active."
}
```

##### SFTP Connection Error (422 Unprocessable Entity)

```json
{
  "message": "SFTP authentication failed. Invalid username or password."
}
```

```json
{
  "message": "Unable to connect to SFTP server.",
  "error": "Connection timed out"
}
```

##### Field Validation Errors (422 Unprocessable Entity)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "sftp_host": ["SFTP host is required."],
    "sftp_port": ["SFTP port must be a valid integer."]
  }
}
```

#### Possible Validation Error Messages

| Field | Message |
|-------|---------|
| `sftp_host` | `SFTP host is required.` |
| `sftp_host` | `SFTP host must not exceed 255 characters.` |
| `sftp_port` | `SFTP port is required.` |
| `sftp_port` | `SFTP port must be a valid integer.` |
| `sftp_port` | `SFTP port must be at least 1.` |
| `sftp_port` | `SFTP port must not exceed 65535.` |
| `sftp_username` | `SFTP username is required.` |
| `sftp_username` | `SFTP username must not exceed 255 characters.` |
| `sftp_password` | `SFTP password is required.` |
| `sftp_password` | `SFTP password must not exceed 255 characters.` |

---

### 2. Create CSV Upload Request

Submit a new CSV file upload request with SFTP destination details.

**Endpoint:** `POST /nalda/csv-upload`

#### Request Headers

| Header | Value | Required | Description |
|--------|-------|----------|-------------|
| `X-License-Key` | string | Yes | A valid, active license key |
| `Content-Type` | `multipart/form-data` | Yes | Required for file upload |

#### Request Body (Form Data)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `domain` | string | Yes | The domain associated with this request (max 255 chars) |
| `sftp_host` | string | Yes | The SFTP server hostname or IP (max 255 chars) |
| `sftp_port` | integer | No | The SFTP server port (1-65535, defaults to 22) |
| `sftp_username` | string | Yes | The SFTP username (max 255 chars) |
| `sftp_password` | string | Yes | The SFTP password (max 255 chars) |
| `csv` | file | Yes | The CSV file to upload (max 10MB, .csv or .txt) |

#### Example Request

```bash
curl -X POST https://licence-manager.jonakyds.com/api/v1/nalda/csv-upload \
  -H "X-License-Key: XXXX-XXXX-XXXX-XXXX" \
  -F "domain=example.com" \
  -F "sftp_host=sftp.example.com" \
  -F "sftp_port=22" \
  -F "sftp_username=myuser" \
  -F "sftp_password=mypassword" \
  -F "csv=@/path/to/file.csv"
```

#### Success Response (201 Created)

```json
{
  "message": "CSV upload request created successfully.",
  "data": {
    "id": 1,
    "domain": "example.com",
    "sftp_host": "sftp.example.com",
    "sftp_port": 22,
    "sftp_username": "myuser",
    "status": "pending",
    "status_label": "Pending",
    "processed_at": null,
    "error_message": null,
    "csv_url": "https://licence-manager.jonakyds.com/storage/1/file.csv",
    "created_at": "2026-01-03T12:00:00+00:00",
    "updated_at": "2026-01-03T12:00:00+00:00"
  }
}
```

#### Error Responses

##### Authentication Error (401 Unauthorized)

```json
{
  "message": "X-License-Key header is required."
}
```

```json
{
  "message": "Invalid license key."
}
```

##### License Error (403 Forbidden)

```json
{
  "message": "License has expired."
}
```

```json
{
  "message": "License is not active."
}
```

##### Validation Error (422 Unprocessable Entity)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "domain": ["Domain is required."],
    "csv": ["CSV file is required."]
  }
}
```

#### Possible Validation Error Messages

| Field | Message | Description |
|-------|---------|-------------|
| `domain` | `Domain is required.` | Domain was not provided |
| `sftp_host` | `SFTP host is required.` | SFTP host was not provided |
| `sftp_username` | `SFTP username is required.` | SFTP username was not provided |
| `sftp_password` | `SFTP password is required.` | SFTP password was not provided |
| `csv` | `CSV file is required.` | No file was uploaded |
| `csv` | `File must be a CSV file.` | Invalid file type (must be .csv or .txt) |
| `csv` | `CSV file must not exceed 10MB.` | File size exceeds 10MB limit |

---

### 2. List CSV Upload Requests

Get a paginated list of all CSV upload requests for the authenticated license.

**Endpoint:** `GET /nalda/csv-uploads`

#### Request Headers

| Header | Value | Required | Description |
|--------|-------|----------|-------------|
| `X-License-Key` | string | Yes | A valid, active license key |
| `Accept` | `application/json` | Recommended | Response content type |

#### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `per_page` | integer | No | 15 | Number of results per page |
| `page` | integer | No | 1 | Page number |

#### Example Request

```bash
curl -X GET "https://licence-manager.jonakyds.com/api/v1/nalda/csv-uploads?per_page=10&page=1" \
  -H "X-License-Key: XXXX-XXXX-XXXX-XXXX" \
  -H "Accept: application/json"
```

#### Success Response (200 OK)

```json
{
  "data": [
    {
      "id": 3,
      "domain": "example.com",
      "sftp_host": "sftp.example.com",
      "sftp_port": 22,
      "sftp_username": "myuser",
      "status": "processed",
      "status_label": "Processed",
      "processed_at": "2026-01-03T12:05:00+00:00",
      "error_message": null,
      "csv_url": "https://licence-manager.jonakyds.com/storage/3/file.csv",
      "created_at": "2026-01-03T12:00:00+00:00",
      "updated_at": "2026-01-03T12:05:00+00:00"
    },
    {
      "id": 2,
      "domain": "example.com",
      "sftp_host": "sftp.example.com",
      "sftp_port": 22,
      "sftp_username": "myuser",
      "status": "failed",
      "status_label": "Failed",
      "processed_at": "2026-01-02T15:30:00+00:00",
      "error_message": "SFTP connection timeout",
      "csv_url": "https://licence-manager.jonakyds.com/storage/2/file.csv",
      "created_at": "2026-01-02T15:00:00+00:00",
      "updated_at": "2026-01-02T15:30:00+00:00"
    }
  ],
  "links": {
    "first": "https://licence-manager.jonakyds.com/api/v1/nalda/csv-uploads?page=1",
    "last": "https://licence-manager.jonakyds.com/api/v1/nalda/csv-uploads?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "to": 2,
    "total": 2
  }
}
```

#### Error Responses

##### Authentication Error (401 Unauthorized)

```json
{
  "message": "X-License-Key header is required."
}
```

```json
{
  "message": "Invalid license key."
}
```

##### License Error (403 Forbidden)

```json
{
  "message": "License has expired."
}
```

```json
{
  "message": "License is not active."
}
```

---

## Upload Request Status

Upload requests can have one of the following statuses:

| Status | Label | Description |
|--------|-------|-------------|
| `pending` | Pending | Request has been created and is waiting to be processed |
| `processing` | Processing | Request is currently being processed |
| `processed` | Processed | File has been successfully uploaded to the SFTP server |
| `failed` | Failed | Upload failed (check `error_message` for details) |

---

## Response Data Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Unique identifier for the upload request |
| `domain` | string | The domain associated with the request |
| `sftp_host` | string | The SFTP server hostname |
| `sftp_port` | integer | The SFTP server port |
| `sftp_username` | string | The SFTP username |
| `status` | string | Current status (`pending`, `processing`, `processed`, `failed`) |
| `status_label` | string | Human-readable status label |
| `processed_at` | string\|null | ISO 8601 timestamp when processing completed |
| `error_message` | string\|null | Error details if status is `failed` |
| `csv_url` | string | URL to download the uploaded CSV file |
| `created_at` | string | ISO 8601 timestamp when request was created |
| `updated_at` | string | ISO 8601 timestamp when request was last updated |

---

## Common Use Cases

### Upload a CSV File and Monitor Status

```bash
# Step 1: Create an upload request
curl -X POST https://licence-manager.jonakyds.com/api/v1/nalda/csv-upload \
  -H "X-License-Key: XXXX-XXXX-XXXX-XXXX" \
  -F "domain=example.com" \
  -F "sftp_host=sftp.example.com" \
  -F "sftp_port=22" \
  -F "sftp_username=myuser" \
  -F "sftp_password=mypassword" \
  -F "csv=@/path/to/data.csv"

# Step 2: Check the status of your uploads
curl -X GET "https://licence-manager.jonakyds.com/api/v1/nalda/csv-uploads" \
  -H "X-License-Key: XXXX-XXXX-XXXX-XXXX" \
  -H "Accept: application/json"
```

### Validate SFTP Credentials Before Upload

Before submitting a CSV upload request, you can validate the SFTP credentials using the [SFTP API](sftp-api.md):

```bash
# Validate SFTP credentials first
curl -X POST https://licence-manager.jonakyds.com/api/v1/sftp/validate \
  -H "Content-Type: application/json" \
  -H "X-License-Key: XXXX-XXXX-XXXX-XXXX" \
  -d '{
    "sftp_host": "sftp.example.com",
    "sftp_port": 22,
    "sftp_username": "myuser",
    "sftp_password": "mypassword"
  }'
```

---

## Security Notes

- SFTP passwords are encrypted at rest in the database
- All requests must be made over HTTPS
- License key authentication ensures only authorized users can access their own upload requests
- Each license can only view and manage its own upload requests
- CSV files are stored securely and associated with the requesting license
