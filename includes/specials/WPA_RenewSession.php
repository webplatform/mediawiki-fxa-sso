<?php
class WPARenewSession extends UnlistedSpecialPage {

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct( 'RenewSession' );
	}
	
	function execute( $par ) {
		global $wgUser;
		$_SESSION['wsUserEmail']             = $wgUser->getEmail();
		$_SESSION['wsUserEffectiveGroups']   = implode(',',$wgUser->getEffectiveGroups());
		$_SESSION['wsUserPageURL']           = $wgUser->getUserPage()->getFullURL();
		
		$this->getOutput()->redirect( $par );
	}
}