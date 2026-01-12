# CEMDB (Cisco Error Message Database) Integration

## Overview

The Thold plugin now includes integration with Cisco's Error Message Database (CEMDB) to provide enriched alert information for Cisco devices. When enabled, threshold alerts containing Cisco error messages will be automatically augmented with detailed explanations and recommended actions from CEMDB.

## Features

- **Automatic Error Detection**: Parses Cisco error message patterns (e.g., `%FACILITY-SEVERITY-MNEMONIC`) from threshold alerts
- **API Integration**: Queries the Cisco CEMDB API for detailed error information
- **Intelligent Caching**: Reduces API calls by caching CEMDB responses
- **Seamless Enrichment**: Automatically appends detailed error information to alert messages
- **Configurable**: Fully configurable via Cacti's settings interface

## Requirements

1. **Cacti 1.2.25 or higher**
2. **Thold plugin 1.8.2 or higher**
3. **Cisco API Key**: Required for accessing CEMDB API
   - Obtain from: https://developer.cisco.com/
4. **PHP cURL extension**: Required for API communication

## Configuration

### Step 1: Obtain Cisco API Key

1. Visit https://developer.cisco.com/
2. Create an account or log in
3. Navigate to "My Apps & Keys"
4. Create a new application
5. Copy the API key

### Step 2: Enable CEMDB Integration

1. Log in to Cacti as an administrator
2. Navigate to **Configuration > Settings**
3. Select the **Thresholds** tab
4. Scroll to the **CEMDB (Cisco Error Message Database) Integration** section
5. Configure the following settings:

   - **Enable CEMDB Integration**: Check this box to enable CEMDB lookups
   - **CEMDB API Endpoint**: Default is `https://api.cisco.com/bug/v3.0/bugs/bug_ids` (usually no need to change)
   - **CEMDB API Key**: Enter your Cisco API key obtained in Step 1
   - **CEMDB API Timeout**: API request timeout in seconds (default: 5)
   - **CEMDB Cache TTL**: How long to cache CEMDB responses in seconds (default: 86400 = 24 hours)

6. Click **Save**

### Step 3: Test Configuration

1. Trigger a threshold alert on a Cisco device that generates error messages
2. Check the alert email/notification for enriched content
3. Verify CEMDB information is appended to the alert

## How It Works

### Error Message Detection

The plugin automatically detects Cisco error messages in threshold alerts using pattern matching. It recognizes the standard Cisco error format:

```
%FACILITY-SEVERITY-MNEMONIC
```

**Examples:**
- `%SYS-5-CONFIG_I`
- `%LINK-3-UPDOWN`
- `%LINEPROTO-5-UPDOWN`

### Enrichment Process

1. **Alert Generation**: When a threshold is breached, an alert message is generated
2. **Pattern Matching**: The message is scanned for Cisco error codes
3. **Cache Check**: The plugin checks if error information is already cached
4. **API Query**: If not cached, queries the CEMDB API
5. **Enrichment**: Appends detailed error information to the alert message
6. **Caching**: Stores the response for future use

### Alert Enhancement

When CEMDB information is available, the following details are added to alerts:

- **Description**: Brief description of the error
- **Explanation**: Detailed explanation of what caused the error
- **Recommended Action**: Steps to resolve the issue

**Example Enriched Alert:**

```html
<html>
<body>
An alert has been issued that requires your attention.
<br><br>
<b>Device</b>: router01 (192.168.1.1)
<br>
<b>Message</b>: Interface GigabitEthernet0/1 %LINK-3-UPDOWN: changed state to down
<br><br>

<div class="cemdb-info">
<h4>Cisco Error Details: %LINK-3-UPDOWN</h4>
<p><strong>Description:</strong> Interface status changed</p>
<p><strong>Explanation:</strong> The interface changed state to administratively down or physically down</p>
<p><strong>Recommended Action:</strong> Check the interface configuration and physical connectivity. Use 'show interface' command for details.</p>
</div>

</body>
</html>
```

## Cache Management

### Automatic Cleanup

The CEMDB cache is automatically cleaned up during regular Thold maintenance cycles. Cached entries older than the configured TTL (Time To Live) are removed to save database space and ensure fresh data.

### Manual Cache Cleanup

To manually clear the CEMDB cache:

```sql
DELETE FROM plugin_thold_cemdb_cache;
```

Or to clear specific error codes:

```sql
DELETE FROM plugin_thold_cemdb_cache WHERE error_code = '%LINK-3-UPDOWN';
```

## Performance Considerations

### API Rate Limiting

Cisco's API may have rate limits. The caching mechanism helps minimize API calls:

- Default cache TTL: 24 hours
- Increase TTL for better performance
- Decrease TTL for more up-to-date information

### Timeout Settings

The default API timeout is 5 seconds. Adjust based on your network:

- **Fast networks**: Can reduce to 3 seconds
- **Slow/high-latency networks**: Increase to 10-15 seconds

### Database Impact

The cache table stores error information. Monitor the table size:

```sql
SELECT COUNT(*) FROM plugin_thold_cemdb_cache;
```

Regular cleanup via the TTL mechanism keeps the table size manageable.

## Troubleshooting

### CEMDB Enrichment Not Working

**Check the following:**

1. **CEMDB Enabled**: Verify integration is enabled in Settings
2. **API Key**: Ensure a valid Cisco API key is configured
3. **Error Pattern**: Verify alerts contain Cisco error message format
4. **Cacti Logs**: Check logs for CEMDB-related errors:
   ```
   tail -f /var/www/html/cacti/log/cacti.log | grep CEMDB
   ```

### Common Log Messages

- `CEMDB: API key not configured` - Add your API key in settings
- `CEMDB: API request failed` - Check network connectivity to api.cisco.com
- `CEMDB: API returned HTTP 401` - Invalid API key
- `CEMDB: API returned HTTP 429` - Rate limit exceeded, increase cache TTL

### Testing API Connectivity

Test API access from command line:

```bash
curl -H "X-Auth-Token: YOUR_API_KEY" \
     "https://api.cisco.com/bug/v3.0/bugs/bug_ids?error_code=%25LINK-3-UPDOWN"
```

## Security Considerations

### API Key Protection

- Store API keys securely
- Restrict Cacti admin access
- Never share API keys in logs or emails

### SSL/TLS Verification

The integration verifies SSL certificates by default. If using a custom endpoint, ensure valid SSL certificates are in place.

## Limitations

1. **Cisco Devices Only**: CEMDB integration only works with Cisco devices
2. **Error Pattern Required**: Alerts must contain standard Cisco error message format
3. **API Availability**: Requires internet access to Cisco's API
4. **API Quota**: Subject to Cisco's API rate limits and quotas

## Support

For issues or questions:

1. Check Cacti forums: https://forums.cacti.net/
2. File an issue on GitHub: https://github.com/Cacti/plugin_thold
3. Review Cacti documentation: https://docs.cacti.net/

## License

CEMDB integration is part of the Thold plugin and follows the same GPL v2 license.

---

Copyright (C) 2004-2025 The Cacti Group
