<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2025 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * CEMDB (Cisco Error Message Database) Integration
 * 
 * This module provides integration with Cisco's Error Message Database
 * to enrich threshold alerts with detailed error information and
 * recommended actions for Cisco devices.
 */

/**
 * thold_cemdb_parse_error_message - Parse a message for Cisco error codes
 *
 * @param string $message - The error message to parse
 *
 * @return array - Array of detected error codes
 */
function thold_cemdb_parse_error_message($message) {
	$error_codes = array();
	
	// Common Cisco error message patterns
	// Format: %FACILITY-SEVERITY-MNEMONIC
	if (preg_match_all('/%([A-Z0-9_]+)-(\d+)-([A-Z0-9_]+)/', $message, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			$error_codes[] = array(
				'full_code' => $match[0],
				'facility' => $match[1],
				'severity' => $match[2],
				'mnemonic' => $match[3]
			);
		}
	}
	
	return $error_codes;
}

/**
 * thold_cemdb_lookup - Query CEMDB for error code information
 *
 * @param string $error_code - The error code to look up
 * @param string $platform - Optional platform/product identifier
 *
 * @return array|false - CEMDB information or false on failure
 */
function thold_cemdb_lookup($error_code, $platform = '') {
	// Check if CEMDB is enabled
	if (read_config_option('thold_cemdb_enabled') != 'on') {
		return false;
	}
	
	// Check cache first
	$cached = thold_cemdb_get_cached($error_code, $platform);
	if ($cached !== false) {
		return $cached;
	}
	
	// Get CEMDB endpoint from settings
	$endpoint = read_config_option('thold_cemdb_endpoint');
	if (empty($endpoint)) {
		// Default to Cisco's public CEMDB API
		$endpoint = 'https://api.cisco.com/bug/v3.0/bugs/bug_ids';
	}
	
	// Prepare API request
	$api_key = read_config_option('thold_cemdb_api_key');
	if (empty($api_key)) {
		cacti_log('CEMDB: API key not configured', false, 'THOLD');
		return false;
	}
	
	// Query CEMDB API
	$result = thold_cemdb_api_query($endpoint, $error_code, $platform, $api_key);
	
	// Cache the result
	if ($result !== false) {
		thold_cemdb_cache_result($error_code, $platform, $result);
	}
	
	return $result;
}

/**
 * thold_cemdb_api_query - Query the CEMDB API
 *
 * @param string $endpoint - API endpoint URL
 * @param string $error_code - The error code to look up
 * @param string $platform - Platform/product identifier
 * @param string $api_key - API authentication key
 *
 * @return array|false - API response or false on failure
 */
function thold_cemdb_api_query($endpoint, $error_code, $platform, $api_key) {
	$timeout = read_config_option('thold_cemdb_timeout', true);
	if (empty($timeout) || $timeout < 1) {
		$timeout = 5; // Default 5 second timeout
	}
	
	// Build query parameters
	$params = array(
		'error_code' => $error_code
	);
	
	if (!empty($platform)) {
		$params['platform'] = $platform;
	}
	
	// Prepare headers
	$headers = array(
		'Accept: application/json',
		'Content-Type: application/json',
		'X-Auth-Token: ' . $api_key
	);
	
	// Initialize cURL
	$ch = curl_init();
	
	curl_setopt($ch, CURLOPT_URL, $endpoint . '?' . http_build_query($params));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	
	// Execute request
	$response = curl_exec($ch);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$error = curl_error($ch);
	
	curl_close($ch);
	
	// Handle errors
	if ($response === false) {
		cacti_log('CEMDB: API request failed - ' . $error, false, 'THOLD');
		return false;
	}
	
	if ($http_code != 200) {
		cacti_log('CEMDB: API returned HTTP ' . $http_code, false, 'THOLD');
		return false;
	}
	
	// Parse JSON response
	$data = json_decode($response, true);
	if ($data === null) {
		cacti_log('CEMDB: Failed to parse API response', false, 'THOLD');
		return false;
	}
	
	return $data;
}

/**
 * thold_cemdb_get_cached - Retrieve cached CEMDB data
 *
 * @param string $error_code - The error code
 * @param string $platform - Platform identifier
 *
 * @return array|false - Cached data or false if not found/expired
 */
function thold_cemdb_get_cached($error_code, $platform = '') {
	$cache_ttl = read_config_option('thold_cemdb_cache_ttl', true);
	if (empty($cache_ttl) || $cache_ttl < 1) {
		$cache_ttl = 86400; // Default 24 hours
	}
	
	$cached = db_fetch_row_prepared('SELECT * FROM plugin_thold_cemdb_cache
		WHERE error_code = ?
		AND platform = ?
		AND timestamp > ?',
		array($error_code, $platform, time() - $cache_ttl));
	
	if ($cached) {
		$decoded = json_decode($cached['data'], true);
		
		// Check for JSON decode errors
		if (json_last_error() !== JSON_ERROR_NONE) {
			cacti_log('CEMDB: Failed to decode cached data - ' . json_last_error_msg(), false, 'THOLD');
			// Delete corrupted cache entry
			db_execute_prepared('DELETE FROM plugin_thold_cemdb_cache WHERE error_code = ? AND platform = ?',
				array($error_code, $platform));
			return false;
		}
		
		return $decoded;
	}
	
	return false;
}

/**
 * thold_cemdb_cache_result - Cache CEMDB lookup result
 *
 * @param string $error_code - The error code
 * @param string $platform - Platform identifier
 * @param array $data - Data to cache
 *
 * @return bool - True on success, false on failure
 */
function thold_cemdb_cache_result($error_code, $platform, $data) {
	$json_data = json_encode($data);
	
	// Check for JSON encode errors
	if ($json_data === false) {
		cacti_log('CEMDB: Failed to encode data for caching - ' . json_last_error_msg(), false, 'THOLD');
		return false;
	}
	
	db_execute_prepared('REPLACE INTO plugin_thold_cemdb_cache
		(error_code, platform, data, timestamp)
		VALUES (?, ?, ?, ?)',
		array($error_code, $platform, $json_data, time()));
	
	return true;
}

/**
 * thold_cemdb_enrich_alert - Enrich alert message with CEMDB information
 *
 * @param string $message - Original alert message
 * @param array $thold_data - Threshold data
 *
 * @return string - Enriched message
 */
function thold_cemdb_enrich_alert($message, $thold_data) {
	// Check if CEMDB enrichment is enabled
	if (read_config_option('thold_cemdb_enabled') != 'on') {
		return $message;
	}
	
	// Only enrich if threshold is enabled for CEMDB
	if (isset($thold_data['cemdb_enabled']) && $thold_data['cemdb_enabled'] != 'on') {
		return $message;
	}
	
	// Parse error codes from the message
	$error_codes = thold_cemdb_parse_error_message($message);
	
	if (empty($error_codes)) {
		return $message;
	}
	
	// Get device platform if available
	$platform = '';
	if (isset($thold_data['host_id']) && $thold_data['host_id'] > 0) {
		$platform = db_fetch_cell_prepared('SELECT snmp_sysDescr 
			FROM host 
			WHERE id = ?',
			array($thold_data['host_id']));
	}
	
	// Build CEMDB enrichment
	$cemdb_info = '';
	
	foreach ($error_codes as $error) {
		$lookup = thold_cemdb_lookup($error['full_code'], $platform);
		
		if ($lookup !== false && !empty($lookup)) {
			$cemdb_info .= '<div class="cemdb-info">';
			$cemdb_info .= '<h4>' . __('Cisco Error Details: ', 'thold') . htmlspecialchars($error['full_code']) . '</h4>';
			
			if (isset($lookup['description'])) {
				$cemdb_info .= '<p><strong>' . __('Description:', 'thold') . '</strong> ' . htmlspecialchars($lookup['description']) . '</p>';
			}
			
			if (isset($lookup['explanation'])) {
				$cemdb_info .= '<p><strong>' . __('Explanation:', 'thold') . '</strong> ' . htmlspecialchars($lookup['explanation']) . '</p>';
			}
			
			if (isset($lookup['recommended_action'])) {
				$cemdb_info .= '<p><strong>' . __('Recommended Action:', 'thold') . '</strong> ' . htmlspecialchars($lookup['recommended_action']) . '</p>';
			}
			
			$cemdb_info .= '</div><br>';
		}
	}
	
	// Append CEMDB info to the message
	if (!empty($cemdb_info)) {
		// Insert before closing body tag if HTML, otherwise append
		if (stripos($message, '</body>') !== false) {
			$message = str_replace('</body>', $cemdb_info . '</body>', $message);
		} else {
			$message .= "\n\n" . $cemdb_info;
		}
	}
	
	return $message;
}

/**
 * thold_cemdb_cleanup_cache - Remove old cache entries
 *
 * @return void
 */
function thold_cemdb_cleanup_cache() {
	$cache_ttl = read_config_option('thold_cemdb_cache_ttl', true);
	if (empty($cache_ttl) || $cache_ttl < 1) {
		$cache_ttl = 86400; // Default 24 hours
	}
	
	db_execute_prepared('DELETE FROM plugin_thold_cemdb_cache
		WHERE timestamp < ?',
		array(time() - $cache_ttl));
}
