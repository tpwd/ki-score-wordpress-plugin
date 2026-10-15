<?php
// Stands in for the KIscore API. POST /track/<site> records the hit and
// answers 201, or 401 unless the tracking secret is "e2e-secret". GET /hits lists,
// DELETE /hits clears, POST /delay?seconds=N slows down later answers.
$store = '/tmp/kiscore-hits.json';
$delay = '/tmp/kiscore-delay';
$path  = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$hits  = is_file( $store ) ? json_decode( file_get_contents( $store ), true ) : array();

header( 'Content-Type: application/json' );

if ( '/hits' === $path ) {
	if ( 'DELETE' === $_SERVER['REQUEST_METHOD'] ) {
		$hits = array();
		file_put_contents( $store, '[]', LOCK_EX );
	}
	echo json_encode( $hits );
	return;
}

if ( '/delay' === $path ) {
	file_put_contents( $delay, (string) (int) ( $_GET['seconds'] ?? 0 ) );
	echo '{}';
	return;
}

if ( 0 === strpos( $path, '/track/' ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	sleep( is_file( $delay ) ? (int) file_get_contents( $delay ) : 0 );
	$secret = $_SERVER['HTTP_X_KISCORE_TRACKING_SECRET'] ?? '';
	$fp     = fopen( $store, 'c+' );
	flock( $fp, LOCK_EX );
	$hits   = json_decode( stream_get_contents( $fp ) ?: '[]', true );
	$hits[] = array(
		'path'   => $path,
		'secret' => $secret,
		'agent'  => $_SERVER['HTTP_USER_AGENT'] ?? '',
		'body'   => json_decode( file_get_contents( 'php://input' ), true ),
	);
	ftruncate( $fp, 0 );
	rewind( $fp );
	fwrite( $fp, json_encode( $hits ) );
	flock( $fp, LOCK_UN );
	fclose( $fp );

	if ( 'e2e-secret' !== $secret ) {
		http_response_code( 401 );
		echo '{"error":"Unauthorized"}';
		return;
	}
	// Like KIscore: Grape answers a POST with 201.
	http_response_code( 201 );
	echo '{"status":"ok","tracked":false}';
	return;
}

http_response_code( 404 );
echo '{}';
