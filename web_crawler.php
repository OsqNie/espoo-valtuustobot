<?php

/**
 * Crawler goes thru the media server at http://espoo04.hosting.documenta.fi/ and looks for links
 * labeled "Esityslista" or "Pöytäkirja", extracting the PDF's and notifying users via email of
 * the changes.
 *
 * The class uses simple_html_dom.php for DOM tree traversal on the web page.
 */
class webCrawler {

	////////////////
	// Properties //
	////////////////

	private $base_url;
	private $target_url;
	private $settings;
	private $pdf_array;

	private $defaults = array(
		'recipients' => [
			'oskar.niemenoja@gmail.com' => 'Oskar Niemenoja'
			],
		'admin' => [
			'oskar.niemenoja@gmail.com' => 'Oskar Niemenoja'
			]
	);

	//////////
	// Init //
	//////////

	public function __construct( $settings ) {

		$this->base_url = ESPOO_FILE_BASE;
		$this->target_url = $settings['target_url'];

		$this->save_path = DATA_FOLDER_BASE . $settings['slug'] . '/';

		$this->settings = array_merge( $this->defaults, $settings );

		$this->pdf_array = json_decode( file_get_contents( $this->save_path . HISTORY_FILENAME ), 1 );

	}

	//////////////////////
	// Public functions //
	//////////////////////

	/**
	 * The main function. This traverses the main document root and looks for links to new files
	 * 
	 * @return none
	 */
	public function crawl() {

		if ( !file_get_contents( $this->base_url . $this->target_url ) ) {
			$this->send_mail_warning( 'Espoon valtuuston tiedostopalvelinta nuuskiva skripti ei pysty lataamaan sivua ja on lopettanut toiminsa. Tarkasta toiminta heti mahdollisuuden tultua.' );
			die();
		}

		$html = new simple_html_dom();
		$html->load_file( $this->base_url . $this->target_url );

		foreach($html->find( 'a' ) as $link){

			/**
			 * The file returns some few dozen links. We are only interested in ones containing interesting data
			 */
			if ( html_entity_decode($link->innertext) != 'Pöytäkirja' && html_entity_decode($link->innertext) != 'Esityslista' ) {
				continue;
			}

			$pdf_name = end(explode('/', str_replace('HTM', 'PDF', $link->href)));
			$file = file_get_contents($this->base_url . $pdf_name);

			$fetcher = new webCrawler( array_merge($this->settings, array('target_url' => $link->href) ) );
			$data = $fetcher->fetch_link();

			/**
			 * This gets saved into HISTORY_FILENAME as an instance row
			 */
			$row = [
				'timestamp' => time(),
				'md5' => md5( $file ),
				'file' => $this->save_path . 'PDF/' . $pdf_name,
				'filename' => $pdf_name,
				'name' => $data['header'],
				'content' => $data['content']
			];

			/**
			 * Only work this if the file is new or modified
			 */
			if ( $this->check_if_new_md5( $row ) ) {

				$this->add_row( $row );
				$this->send_mail( $row );
				$this->save_PDF( $pdf_name, $file );

			}

		}

	}

	/**
	 * Logic for each individual page. The page content (a HTML table) and header are extracted
	 * 
	 * @return array
	 */
	public function fetch_link() {

		$ret = [
			'header' => $this->strip_header(),
			'content' => $this->strip_tables()
		];

		return $ret;

	}

	///////////////////////
	// Private functions //
	///////////////////////

	/**
	 * Check against the existing files if the file has already been handled
	 * 
	 * @param  array $row 
	 * @return bool      Is the row new or existing
	 */
	private function check_if_new_md5( $row ) {

		$new = 1;

		foreach ($this->pdf_array as $hist_row) {
			if ( $hist_row[ 'md5' ] == $row[ 'md5' ] ) {
				$new = 0;
			}
		}

		return $new;

	}

	/**
	 * Get the header element from web page
	 * 
	 * @return text    $header
	 */
	private function strip_header() {

		$html = new simple_html_dom();
		$html->load_file($this->base_url . $this->target_url);

		$ret = "";

		$header = $html->find( 'label.h3' )[0];

		$header = html_entity_decode(strip_tags($header->innertext));

		return $header;

	}

	/**
	 * Get the tables containing the TOC and meeting notes. These are sent via email.
	 * 
	 * @return HTML
	 */
	private function strip_tables() {

		$html = new simple_html_dom();
		$html->load_file($this->base_url . $this->target_url);

		$ret = "";

		$table = $html->find( '.bodyKokous > p' )[0];

		if ( $table == null ) return null;

		foreach ( $table->find( 'img' ) as $image ) {
			$image->outertext = ' ';
		}

		foreach ( $table->find( 'a' ) as $link ) {
			$link->href = $this->base_url . $link->href;
		}

		return (string)$table;

	}

	/**
	 * Save a row of info into the "database"
	 * 
	 * @param array $row
	 */
	private function add_row( $row ) {

		array_push( $this->pdf_array, $row );

		$temp_arr = json_decode( file_get_contents( $this->save_path . HISTORY_FILENAME ), 1 );

		/*
		 * Make sure that all previous entries are included in this new array
		 */
		if ( array_intersect( $temp_arr , $this->pdf_array ) == $temp_arr ) {
			file_put_contents( $this->save_path . HISTORY_FILENAME , json_encode( $this->pdf_array ) );
		}

	}

	/**
	 * Save the PDF file into a folder named PDF
	 * @return none
	 */
	private function save_PDF( $name, $file ) {

		try {
	 		file_put_contents( DATA_FOLDER_BASE . $this->settings['slug'] . "/PDF/" . $name , $file);
	 	} catch (Exception $e) {
		 	trigger_error( "Invalid file: " . $this->base_url . str_replace('HTM', 'PDF', $link->href) );
		}

	}

	/**
	 * Send amil about a new file onto recipients. For now does not send the pdf as an attachment as these
	 * get easily blocked by mail clients
	 * 
	 * @param  [array] $data This array contains all the data for the message, parsed in crawl()
	 * @return bool    If sending mail succeeded or not
	 */
	private function send_mail( $data ) {

		$subject = '[Espoon valtuustobot] ' . $data['name'];
		$content = utf8_decode(utf8_encode(file_get_contents(EMAIL_TEMPLATE))) . $data['header'] . PHP_EOL . $data[ 'content' ];

		$mail = new SimpleMail();
		$mail->setSubject( $subject )
		     ->setFrom('noreply@sk2b13.com', 'Oskar Niemenoja')
		     ->addMailHeader('Reply-To', 'oskar.niemenoja@ayy.fi', 'Oskar Niemenoja')
		     ->addGenericHeader('Content-Type', 'text/html; charset="utf-8"')
		     ->setMessage( $content )
		     ->setWrap(100);

		foreach ( $this->settings['recipients'] as $to => $name ) {
			$mail->setTo( $to, $name );
		}

		$send = $mail->send();

	}

	/**
	 * Send an error message to the mail adresses designated in default settings.
	 * @param  string $msg The error message
	 * @return bool      
	 */
	private function send_mail_warning( $msg ) {

		$to = implode(", ", $this->settings['admin']);
		$subject = '[Espoon valtuustobot] Virhe palvelimella';

		return mail( $to, $subject, $msg );

	}

}

?>