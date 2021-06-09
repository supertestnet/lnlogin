<?php

/**
 * Plugin Name: Lightning login
 * Description: Allow account creation via lnurl-auth
 * Version: 1.0.1
 * Author: Super Testnet
 */

/* The function sigVerifier takes a message (the value of $k1), a signed version of that message (the value of $sig), and a linking key (the value of $key)
and verifies that the signature is valid. That is, it verifies that the message was signed by the owner of the private key belonging to the linking key.
This can be used as part of an lnurl login scheme. Note that I got $bitcoinECDSA from github and had to slightly modify the function checkSignaturePoints()
to get this script to work. Specifically, I modified this line:

	while( strlen($xRes) < strlen( $R ) )

It used to say:

	while( strlen($xRes) < 64 )

Here is my explanation for why I did it:

checkDerSignature() runs the signature through bin2hex() and passes a substring of the result -- which is 66 characters in length -- to another function.

To check whether the signature is valid, one part of that other function compares the signature to a string which it derives from the message hash and
which it ensures is at least 64 characters in length by taking the message-hash-derived-string, checking if it is less than 64 characters in length, and
-- if it is -- padding it with 0s until it is 64 characters in length. Since the signature derivation I'm passing along is 66 characters but the padded
message hash derivation is only 64 characters, the strings do not match, yielding a failure.

If I tell the function to pad the string obtained from the hash with 0s until it is the same length as the signature derivation, the function yields true
as expected. I wonder if this is a good solution. I suspect there's a deeper problem involving the signature derivation being 2 characters longer than
BitcoinECDSA expects it to be.

*/

function sigVerifier( $key, $sig, $k1 ) {
	include_once( 'BitcoinECDSA.php' );
	$bitcoinECDSA = new BitcoinPHP\BitcoinECDSA\BitcoinECDSA();
	return $bitcoinECDSA->checkDerSignature( $key, $sig, $k1 );
	die();
}

function k1Generator() {
	$rand1 = rand( 100000000, 999999999 );
        $rand2 = rand( 100000000, 999999999 );
        $rand3 = rand( 100000000, 999999999 );
        $rand4 = rand( 100000000, 999999999 );
        $rand5 = rand( 100000000, 999999999 );
        $rand6 = rand( 100000000, 999999999 );
        $rand7 = rand( 100000000, 999999999 );
        $rand8 = rand( 100000000, 999999999 );
	$hash = hash( 'sha256', $rand1 . $rand2 . $rand3 . $rand4 . $rand5 . $rand6 . $rand7 . $rand8 );
	return $hash;
}

function k1db() {
        global $wpdb;
        $k1db_version = '1.0';
        $table_name = $wpdb->prefix . "k1db";
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                k1 text(50),
                linkingkey text(50),
                expiry int(10),
		status tinyint(1),
                PRIMARY KEY  (id)
        ) $charset_collate;";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        add_option( 'k1db_version', $k1db_version );
}

register_activation_hook( __FILE__, 'k1db' );

function addk1( $k1 ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'k1db';
	$t = time();
	$threemins = $t + 180;
        $expiry = $threemins;
	$status = 0;
        $wpdb->insert(
                $table_name,
                array(
                        'k1' => $k1,
                        'expiry' => $expiry,
                        'status' => $status,
                )
        );
}

function removek1s() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'k1db';
	$t = time();
	$selection = "SELECT id FROM $table_name WHERE expiry < " . $t;
	$k1s = $wpdb->get_results( $selection );
	foreach( $k1s as $k1 ) {
		$wpdb->delete( $table_name, array( 'id' => $k1->id ) );
	}
}

function setLinkingKey( $lkey, $k1 ) {
        global $wpdb;
	$lkey = filter_var( $lkey, FILTER_SANITIZE_STRING );
        $table_name = $wpdb->prefix . 'k1db';
	$wpdb->update( $table_name, array( 'linkingkey' => $lkey, 'status' => 1 ), array( 'k1' => $k1 ) );
}

add_shortcode( 'generatelnurl', 'generateLnurl' );

function generateLnurl() {
	$k1 = k1Generator();
	addk1( $k1 );
	removek1s();
	$url = 'https://' . $_SERVER[ "HTTP_HOST" ] . '/wp-admin/admin-ajax.php?action=lnlogin&tag=login&k1=' . $k1;
	$lnurl = lnurlEncoder( $url );
	$script = '
		<div id="lnurl-auth-qr" style="' . get_option( 'lnlogin_container_style' ) . '"><a id="lnurl-auth-link" target="_blank"></a></div>
		<script>
			function createQR( data ) {
                                var dataUriPngImage = document.createElement( "img" ),
                                s = QRCode.generatePNG( data, {
                                        ecclevel: "M",
                                        format: "html",
                                        fillcolor: "#FFFFFF",
                                        textcolor: "#373737",
                                        margin: 4,
                                        modulesize: 8
                                } );
                                dataUriPngImage.src = s;
                                dataUriPngImage.id = "lnurl-auth-image";
				dataUriPngImage.style = "' . get_option( 'lnlogin_qr_style' ) . '";
				return dataUriPngImage;
                        }
			document.getElementById( "lnurl-auth-link" ).appendChild( createQR( "' . $lnurl . '".toUpperCase() ) );
			document.getElementById( "lnurl-auth-link" ).href = "lightning:' . $lnurl . '";
			var caption = document.createElement( "pre" );
			caption.id = "lnurl-auth-caption";
			caption.style = "' . get_option( 'lnlogin_caption_style' ) . '";
			caption.innerText = "' . $lnurl . '";
			document.getElementById( "lnurl-auth-qr" ).append( caption );
			var k1 = "' . $k1 . '";
			function checkChallenge( challenge ) {
                		var xhttp = new XMLHttpRequest();
                		xhttp.onreadystatechange = function() {
                	        	if ( this.readyState == 4 && this.status == 200 ) {
                	                	if ( this.responseText == 1 ) {
							window.location.href = "' . get_option( 'lnlogin_redirect' ) . '";
	                                	} else {
	                        	                setTimeout( function() {checkChallenge( challenge );}, 1000 );
	                	                }
	        	                }
		                };
	                	xhttp.open( "GET", "/wp-admin/admin-ajax.php?action=loginif&#038;".replace("#038;", "") + "k1=" + challenge, true );
	        	        xhttp.send();
		        }
			checkChallenge( k1 );
		</script>
	';
	return $script;
	die();
}

function lnurlEncoder( $url ) {
	include_once( 'lnurl.php' );
	$lnurl = tkijewski\lnurl\encodeUrl( $url );
	return $lnurl;
}

add_action( 'wp_ajax_lnlogin', 'lnlogin' );
add_action( 'wp_ajax_nopriv_lnlogin', 'lnlogin' );

function lnlogin() {
	removek1s();
	$key = $_GET[ "key" ];
	$sig = $_GET[ "sig" ];
	$k1 = $_GET[ "k1" ];
	$all_good = sigVerifier( $key, $sig, $k1 );
	if ( $all_good ) {
		setLinkingKey( $key, $k1 );
		$message[ "status" ] = "OK";
		echo json_encode( $message );
		die();
	}
	$message[ "status" ] = "ERROR";
	$message[ "reason" ] = "Unknown error. Perhaps your login qr expired, they only last three minutes. Please try again or contact the system administrator.";
	echo json_encode( $message );
	die();
}

function loginUser( $id ) {
	$user = get_user_by( 'id', $id );
	$creds = array(
        	'user_login'    => $user->user_login,
        	'user_password' => $user->user_pass,
        	'remember'      => true
	);
	do_action( 'wp_login', $user->user_login, $user );
	$secure_cookie = is_ssl();
	$secure_cookie = apply_filters( 'secure_signon_cookie', $secure_cookie, $creds );
	global $auth_secure_cookie;
	$auth_secure_cookie = $secure_cookie;
	add_filter( 'authenticate', 'wp_authenticate_cookie', 30, 3 );
	if ( is_wp_error( $user ) ) {
		return $user;
	}
	wp_set_auth_cookie( $user->ID, $creds[ 'remember' ], $secure_cookie );
}

function checkMeta( $linkingkey ) {
	$users = get_users(array(
		'meta_key' => 'linkingkey',
		'meta_value' => $linkingkey
	));
	if ( !empty( $users ) ) {
		$user = $users[ 0 ];
		$user_id = $user->ID;
		return $user_id;
	}
	return;
}

add_action( 'wp_enqueue_scripts', 'load_my_scripts' );

function load_my_scripts( $hook ) {
    $my_js_ver  = date( "ymd-Gis", filemtime( plugin_dir_path( __FILE__ ) . 'js/qrcode.js' ) );
    wp_enqueue_script( 'qrcode', plugins_url( 'js/qrcode.js', __FILE__ ), array(), $my_js_ver );
}

add_action( 'wp_ajax_loginif', 'loginIfVerified' );
add_action( 'wp_ajax_nopriv_loginif', 'loginIfVerified' );

function loginIfVerified() {
        removek1s();
        $k1 = $_GET[ "k1" ];
        global $wpdb;
        $table_name = $wpdb->prefix . 'k1db';
        $selection = "SELECT status, linkingkey FROM $table_name WHERE k1 = '" . $k1 . "'";
        $status = $wpdb->get_results( $selection );
	$status = $status[ 0 ];
	$key = $status->linkingkey;
        if ( $status->status == "1" ) {
		if ( checkMeta( $key ) ) {
                        loginUser( checkMeta( $key ) );
                } else {
			$password = hash( 'sha256', $key . rand( 100000000, 999999999 ) );
			$userdata[ "user_login" ] = substr( $key, 0, 10 );
                	$userdata[ "user_pass" ] = substr( $password, 0, 20 );
                	$userdata[ "user_nicename" ] = substr( $key, 0, 10 );
                	$userdata[ "show_admin_bar_front" ] = false;
	                $user_id = wp_insert_user( $userdata );
                	$user_id_role = new WP_User( $user_id );
        	        update_user_meta( $user_id, 'linkingkey', $key );
			loginUser( $user_id );
                }
	} else {
		echo 0;
	}
	echo $status->status;
	die();
}

function lnlogin_register_settings() {
        add_option( 'lnlogin_redirect', 'https://' . $_SERVER[ "HTTP_HOST" ] );
        add_option( 'lnlogin_container_style', '' );
	add_option( 'lnlogin_qr_style', 'width: 100%;' );
	add_option( 'lnlogin_caption_style', 'width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border: 1px solid black; padding: 5px;' );
        register_setting( 'lnlogin_options_group', 'lnlogin_redirect', 'lnlogin_callback' );
        register_setting( 'lnlogin_options_group', 'lnlogin_container_style', 'lnlogin_callback' );
        register_setting( 'lnlogin_options_group', 'lnlogin_qr_style', 'lnlogin_callback' );
        register_setting( 'lnlogin_options_group', 'lnlogin_caption_style', 'lnlogin_callback' );
}
add_action( 'admin_init', 'lnlogin_register_settings' );

function lnlogin_register_options_page() {
        add_options_page( 'Lightning login', 'Lightning login', 'manage_options', 'lnlogin', 'lnlogin_options_page' );
}
add_action('admin_menu', 'lnlogin_register_options_page');

function lnlogin_options_page()
{
?>
        <h2 style="text-decoration: underline;">Lightning login</h2>
        <form method="post" action="options.php">
                <?php settings_fields( 'lnlogin_options_group' ); ?>
                <h3>
                        Redirect
                </h3>
		<p>Where should users be redirected to when they log in?</p>
                <table>
                        <tr valign="middle">
                                <th scope="row">
                                        <label for="lnlogin_redirect">
                                                Lightning login redirect
                                        </label>
                                </th>
                                <td>
                                        <input type="text" id="lnlogin_redirect" name="lnlogin_redirect" value="<?php echo get_option( 'lnlogin_redirect' ); ?>" />
                                </td>
                        </tr>
                </table>
                <h3>
                        Css
                </h3>
                <p>Adjust the css of the div element that contains the qr code and the caption. Resizing this will resize the qr code and the caption simultaneously.</p>
                <table>
                        <tr valign="middle">
                                <th scope="row">
                                        <label for="lnlogin_container_style">
                                                Container css
                                        </label>
                                </th>
                                <td>
                                        <input type="text" id="lnlogin_container_style" name="lnlogin_container_style" value="<?php echo get_option( 'lnlogin_container_style' ); ?>" />
                                </td>
                        </tr>
                </table>
		<p>Adjust the css of the qr code image that appears in place of the shortcode. Note that this size attribute is independent of the caption so if you make one smaller, consider making the other smaller too.</p>
                <table>
                        <tr valign="middle">
                                <th scope="row">
                                        <label for="lnlogin_qr_style">
                                                QR code css
                                        </label>
                                </th>
                                <td>
                                        <input type="text" id="lnlogin_qr_style" name="lnlogin_qr_style" value="<?php echo get_option( 'lnlogin_qr_style' ); ?>" />
                                </td>
			</tr>
		</table>
		<p>Adjust the css of the caption that appears below the shortcode</p>
                <table>
                        <tr valign="middle">
                                <th scope="row">
                                        <label for="lnlogin_caption_style">
                                                Caption css
                                        </label>
                                </th>
                                <td>
                                        <input type="text" id="lnlogin_caption_style" name="lnlogin_caption_style" value="<?php echo get_option( 'lnlogin_caption_style' ); ?>" />
                                </td>
                        </tr>
                </table>
                <?php  submit_button(); ?>
		<h3>
			Instructions
		</h3>
		<p>Add the following shortcode to any page on your site.</p>
		<pre style="margin-left: 50px;">[generatelnurl]</pre>
		<p>When a user visits the page, the shortcode will display as a clickable lightning login qr code. Place the shortcode inside one of your own elements to use standard wordpress css tools or website builders for modifying its size, position, etc. The css property of the element containing the link is #lnurl-auth-qr. The css id of the link is #lnurl-auth-link. The css id of the image is #lnurl-auth-image. There will also be a caption underneath the image containing the text of the lnurl. Its css id is #lnurl-auth-caption.</p>
		<p>Users who scan the qr code with a bitcoin wallet that supports the lnurl-auth protocol will automatically get a new account with a random username and secure password or, if they've signed in with that bitcoin wallet before, they will be signed into their existing user (without ever needing to remember -- or even see -- their password!). After logging in, the user will be redirected to whatever page you specify in settings.</p>
        </form>
<?php
} ?>
