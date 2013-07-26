<?php

class WebPlatformAuthHooks {

	/**
	 * 
	 * @param User $user
	 * @param string $inject_html
	 * @return boolean
	 */
	public static function onUserLoginComplete( $user, &$inject_html ) {
		$_SESSION['wsUserEmail']             = $user->getEmail();
		//We've got to flatten the effective groups because MWs MemchacheD 
		//session handler does not serialize $_SESSION correctly 
		// -> inludes/MemcachedClient.php:993
		$_SESSION['wsUserEffectiveGroups']   = implode( ',', $user->getEffectiveGroups() );
		//$_SESSION['wsUserRealName']        = $user->getRealName();
		$_SESSION['wsUserPageURL']           = $user->getUserPage()->getFullURL();

		self::writeDataToMemcache( $user );

		self::checkReturnTo();
		
		return true;
	}

	/**
	 * 
	 * @param User $user
	 * @param string $inject_html
	 * @param string $oldName
	 * @return boolean
	 */
	public static function onUserLogoutComplete($user, $inject_html, $oldName) {
		//TODO: Maybe
		session_destroy();
		self::checkReturnTo();

		return true;
	}
	
	/**
	 * 
	 * @param User $user User object
	 * @param array $session session array, will be added to $_SESSION
	 * @param array $cookies cookies array mapping cookie name to its value
	 * @return boolean
	 */
	public static function onUserSetCookies( $user, &$session, &$cookies ) {
		$session['wsUserEmail']             = $user->getEmail();
		$session['wsUserEffectiveGroups']   = implode(',',$user->getEffectiveGroups());
		$session['wsUserPageURL']           = $user->getUserPage()->getFullURL();
		
		self::writeDataToMemcache( $user );
		return true;
	}

	/**
	 * 
	 * @param User $user User object
	 * @return boolean
	 */
	public static function onUserLoadAfterLoadFromSession( $user ) {
		$_SESSION['wsUserEmail']             = $user->getEmail();
		$_SESSION['wsUserEffectiveGroups']   = implode(',',$user->getEffectiveGroups());
		$_SESSION['wsUserPageURL']           = $user->getUserPage()->getFullURL();

		self::writeDataToMemcache( $user );
		return true;
	}
	
	/**
	 * 
	 * @global WebRequest $wgRequest
	 * @global OutputPage $wgOut
	 */
	public static function checkReturnTo() {
		global $wgRequest;
		$returnTo = $wgRequest->getVal('returnto');
		if (!is_null($returnTo) && in_array( substr($returnTo, 0, 3), array( 'qa|', 'wp|' ) ) ) {
			//We have to exit() here because otherwise we would be redirected to a MW page
			header('Location: ' . substr($returnTo, 3));
			exit();
		}
	}

	/**
	 * 
	 * @param string $userIds Comma seperated list of user ids
	 * @param string $secret A secret key to avoid unauthorized use of the ajax interface
	 * @return string JSON encoded list of requested user information
	 */
	public static function ajaxGetUserInfoById($userIds, $secret ) {
		global $wgWebPlatformAuthSecret;
		if( $secret != $wgWebPlatformAuthSecret ) {
			return FormatJson::encode( new stdClass() );
		}

		$userIds = explode(',', $userIds);
		$users = UserArray::newFromIDs($userIds);

		$response = array();
		foreach ($users as $user) {
			$response[$user->getId()] = array(
				'user_name'      => $user->getName(),
				'user_real_name' => $user->getRealName(),
				'user_email'     => $user->getEmail(),
				'user_page_url'  => $user->getUserPage()->getFullURL()
			);
		}

		//In MW there is no user "0", but in Q2A
		if( in_array( 0, $userIds ) ) {
			$user = User::newFromId(1); //WikiSysop;
			$response[0] = array(
				'user_name'      => $user->getName(),
				'user_real_name' => $user->getRealName(),
				'user_email'     => $user->getEmail(),
				'user_page_url'  => $user->getUserPage()->getFullURL()
			);
		}

		return FormatJson::encode($response);
	}
	
	/**
	 * 
	 * @param string $userNames Comma seperated list of user names
	 * @param string $secret A secret key to avoid unauthorized use of the ajax interface
	 * @return string JSON encoded list of requested user information
	 */
	public static function ajaxGetUserInfoByName($userNames, $secret) {
		global $wgWebPlatformAuthSecret;
		if( $secret != $wgWebPlatformAuthSecret ) {
			return FormatJson::encode( new stdClass() );
		}

		$userNames = explode(',', $userNames);
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
				'user',
				'user_id',
				array( 'user_name' => $userNames )
		);

		$response = array();
		foreach ( $res as $row ) {
			$user = User::newFromId($row->user_id);
			$response[$user->getId()] = array(
				'user_name'      => $user->getName(),
				'user_real_name' => $user->getRealName(),
				'user_email'     => $user->getEmail(),
				'user_page_url'  => $user->getUserPage()->getFullURL()
			);
		}

		return FormatJson::encode($response);
	}
	
	protected static function writeDataToMemcache( $user ) {
		global $wgMemc;

		if ( !is_object($user) ) return true;

		$sesskey = false;
		if ( isset( $_COOKIE[ 'wpwiki_session' ] ) ) {
			$sesskey = $_COOKIE[ 'wpwiki_session' ];
		}
		if ( !$sesskey ) return true;

		$memcAlternateSessionKey = 'wpwiki:altsession:'.$sesskey;
		#error_log( "Docs Alternate Session Key: ". $memcAlternateSessionKey );
		#error_log( "UserId". $user->getId() );

		$data = array();
		$data['wsUserID'] = $user->getId();
		$data['wsUserName'] = $user->getName();
		$data['wsUserEmail']             = $user->getEmail();
		$data['wsUserEffectiveGroups']   = implode(',',$user->getEffectiveGroups());
		$data['wsUserPageURL']           = $user->getUserPage()->getFullURL();

		$wgMemc->add( $memcAlternateSessionKey, serialize( $data ) );
	}
}