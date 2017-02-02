<?php
/**
 * PHP Webcrawler | Oskar Niemenoja 2016-2017
 *
 * This file crawls thru the Espoo council file database at http://espoo04.hosting.documenta.fi/
 * and checks for new meeting notes. If the files have been modified this will alert all recipients
 * and send the updated notes.
 *
 * The task is meant to run with cron every morning. Email oskar.niemenoja@ayy.fi for more info.
 *
 * Espoo Valtuusto endpoint to use as the script argument
 * "TELIN-214775.HTM";
 */

include_once('simple_html_dom.php');
include_once('simple_mail.php');

include_once("web_crawler.php");

define( 'HISTORY_FILENAME', 'history.json' );
define( 'EMAIL_TEMPLATE', 'email_template.tpl');
define( 'ESPOO_FILE_BASE', "http://espoo04.hosting.documenta.fi/kokous/" );
define( 'DATA_FOLDER_BASE', "DATA/" );

/**
 * Create folder for data if none exist
 */
if (!file_exists(DATA_FOLDER_BASE)) {
	mkdir(DATA_FOLDER_BASE);
}

/**
 * Has format of
 * {
 * 	'target_url' : ..., // which web address to look at
 * 	'slug' : ..., // what to call the subfolder
 * 	'recipients' : ..., // Who to send this to
 * 	'admin' : ... // who administers this list and should know about errors
 * }
 */
$input_args = json_decode( file_get_contents( 'init.json' ), 1 );

/**
 * Go thru the values
 */
foreach ($input_args as $settings) {

	/**
	 * Create folder for data if none exist and init the history file
	 */
	if ( !file_exists( DATA_FOLDER_BASE . $settings['slug'] . '/' ) ) {
		mkdir( DATA_FOLDER_BASE . $settings['slug'] . '/' );
	}
	if ( !file_exists( DATA_FOLDER_BASE . $settings['slug'] . '/' . HISTORY_FILENAME ) ) {
		file_put_contents(DATA_FOLDER_BASE . $settings['slug'] . '/' . HISTORY_FILENAME, json_encode( array() ));
	}
	if ( !file_exists( DATA_FOLDER_BASE . $settings['slug'] . '/PDF' ) ) {
		mkdir( DATA_FOLDER_BASE . $settings['slug'] . '/PDF' );
	}

	$crawler = new webCrawler( $settings );
	echo $crawler->crawl();
}

?>