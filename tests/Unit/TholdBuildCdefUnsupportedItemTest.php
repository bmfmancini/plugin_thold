<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * A CDEF that thold cannot evaluate is reported at every log level (#736).
 *
 * thold_build_cdef() evaluates operators and literal values only. A function
 * item (LOG, as a dBm conversion needs), a nested CDEF, or an unknown special
 * data source makes it return the value without the CDEF applied, and that
 * was logged only at MEDIUM verbosity, so the threshold quietly compared
 * against the unconverted number. The value returned is unchanged here; what
 * changes is that the default log now says so.
 */
final class TholdBuildCdefUnsupportedItemTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('includes/functions.php');
	}

	/**
	 * The cacti_log() calls that reported an unsupported CDEF, as
	 * [message, level].
	 *
	 * @return array<int, array{0: string, 1: mixed}>
	 */
	private function unsupportedWarnings() {
		$warnings = [];

		foreach (CactiStubs::callsTo('cacti_log') as $call) {
			if (strpos($call['params'][0], 'is not supported') !== false) {
				$warnings[] = [$call['params'][0], $call['params'][3]];
			}
		}

		return $warnings;
	}

	/**
	 * CDEF 12 is "CURRENT_DATA_SOURCE, LOG, 10, *": a dBm-style conversion.
	 *
	 * @return void
	 */
	public function testAFunctionItemIsReportedAtTheDefaultLevel(): void {
		CactiStubs::willReturn('db_fetch_assoc_prepared', [
			['id' => 1, 'cdef_id' => 12, 'type' => 4, 'value' => 'CURRENT_DATA_SOURCE'],
			['id' => 2, 'cdef_id' => 12, 'type' => 1, 'value' => 3],
			['id' => 3, 'cdef_id' => 12, 'type' => 6, 'value' => 10],
			['id' => 4, 'cdef_id' => 12, 'type' => 2, 'value' => 3],
		]);

		$this->assertSame(100, thold_build_cdef(12, 100, 4, 5));
		$this->assertSame(
			[['WARNING: Threshold CDEF[12] item type 1 (Function) is not supported. Data ID 4 is evaluated without the CDEF, using 100', 0]],
			$this->unsupportedWarnings()
		);
	}

	/**
	 * @return void
	 */
	public function testANestedCdefIsReportedAtTheDefaultLevel(): void {
		CactiStubs::willReturn('db_fetch_assoc_prepared', [
			['id' => 1, 'cdef_id' => 12, 'type' => 6, 'value' => 10],
			['id' => 2, 'cdef_id' => 12, 'type' => 5, 'value' => 3],
		]);

		$this->assertSame(100, thold_build_cdef(12, 100, 4, 5));
		$this->assertSame(
			[['WARNING: Threshold CDEF[12] item type 5 (Another CDEF) is not supported. Data ID 4 is evaluated without the CDEF, using 100', 0]],
			$this->unsupportedWarnings()
		);
	}

	/**
	 * @return void
	 */
	public function testAnUnknownSpecialDataSourceIsReportedAtTheDefaultLevel(): void {
		CactiStubs::willReturn('db_fetch_assoc_prepared', [
			['id' => 1, 'cdef_id' => 12, 'type' => 4, 'value' => 'SIMILAR_DATA_SOURCES_NODUPS'],
		]);

		$this->assertSame(100, thold_build_cdef(12, 100, 4, 5));
		$this->assertSame(
			[['WARNING: Threshold CDEF[12] Special Data Source "SIMILAR_DATA_SOURCES_NODUPS" is not supported. Data ID 4 is evaluated without the CDEF, using 100', 0]],
			$this->unsupportedWarnings()
		);
	}
}
