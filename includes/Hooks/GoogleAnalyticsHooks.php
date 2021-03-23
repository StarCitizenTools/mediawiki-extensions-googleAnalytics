<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\GoogleAnalytics\Hooks;

use MediaWiki\Hook\SkinAfterBottomScriptsHook;
use MediaWiki\Hook\UnitTestsListHook;

class GoogleAnalyticsHooks implements SkinAfterBottomScriptsHook, UnitTestsListHook {

	/**
	 * @param Skin $skin
	 * @param string &$text
	 * @return bool
	 */
	public static function onSkinAfterBottomScripts( $skin, &$text ) {
		global $wgGoogleAnalyticsAccount, $wgGoogleAnalyticsAnonymizeIP, $wgGoogleAnalyticsOtherCode,
			   $wgGoogleAnalyticsIgnoreNsIDs, $wgGoogleAnalyticsIgnorePages, $wgGoogleAnalyticsIgnoreSpecials;

		$title = $skin->getTitle();
		$csp = $skin->getOutput()->getCSP();
		$cspSrc = 'https://www.google-analytics.com';

		if ( $skin->getUser()->isAllowed( 'noanalytics' ) ) {
			$text .= "<!-- Web analytics code inclusion is disabled for this user. -->\r\n";
			return true;
		}

		$ignoreSpecials = array_filter( $wgGoogleAnalyticsIgnoreSpecials, function ( $v ) use ( $title ) {
			return $title->isSpecial( $v );
		} );
		if ( count( $ignoreSpecials ) > 0
			|| in_array( $title->getNamespace(), $wgGoogleAnalyticsIgnoreNsIDs, true )
			|| in_array( $title->getPrefixedText(), $wgGoogleAnalyticsIgnorePages, true ) ) {
			$text .= "<!-- Web analytics code inclusion is disabled for this page. -->\r\n";
			return true;
		}

		$appended = false;

		if ( $wgGoogleAnalyticsAccount !== '' ) {
			// Add CSP src
			// Have to use default-src since MW does not support img and connect
			$csp->addDefaultSrc( $cspSrc );
			$csp->addScriptSrc( 'https://ssl.google-analytics.com' );
			// Add inline script
			$text .= Html::inlineScript( <<<EOD
				window.ga=window.ga||function(){(ga.q=ga.q||[]).push(arguments)};ga.l=+new Date;
				ga('create', '
				EOD
				. $wgGoogleAnalyticsAccount . <<<EOD
				', 'auto');
				EOD
				. ( $wgGoogleAnalyticsAnonymizeIP ? "  ga('set', 'anonymizeIp', true);\r\n" : "" ) . <<<EOD
					ga('send', 'pageview');
				EOD
			, $csp->getNonce() );
			$text .= "\r\n<script async src='https://www.google-analytics.com/analytics.js'></script>";
			$appended = true;
		}

		if ( $wgGoogleAnalyticsOtherCode !== '' ) {
			$text .= $wgGoogleAnalyticsOtherCode . "\r\n";
			$appended = true;
		}

		if ( !$appended ) {
			$text .= "<!-- No web analytics configured. -->\r\n";
		}

		return true;
	}

	/**
	 * @param string[] &$files
	 * @return bool
	 */
	public static function onUnitTestsList( array &$files ) {
		// @codeCoverageIgnoreStart
		$directoryIterator = new RecursiveDirectoryIterator( __DIR__ . '/tests/' );

		/**
		 * @var SplFileInfo $fileInfo
		 */
		$ourFiles = [];
		foreach ( new RecursiveIteratorIterator( $directoryIterator ) as $fileInfo ) {
			if ( substr( $fileInfo->getFilename(), -8 ) === 'Test.php' ) {
				$ourFiles[] = $fileInfo->getPathname();
			}
		}

		$files = array_merge( $files, $ourFiles );
		return true;
		// @codeCoverageIgnoreEnd
	}
}
