<?php
/**
 *
 *
 * Created on Sep 29, 2012
 *
 * API module for MediaWiki's WebPlatformSearchAutocomplete extension
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

/**
 * @ingroup WebPlatformAuth
 */
class ApiWebPlatformAuth extends ApiBase {

	public function __construct( $main, $action ) {
		parent::__construct( $main, $action );
	}

	public function getCustomPrinter() {
		return $this->getMain()->createPrinterByName( 'json' );
	}

	public function execute() {
		global $wgSearchSuggestCacheExpiry, $wgWebPlatformAuthSecret;
		$params = $this->extractRequestParams();

		$command  = $params['command'];
		$users    = $params['users'];
		$secret   = $params['secret'];
		
		$result = $this->getResult();
		
		if( $secret != $wgWebPlatformAuthSecret ) {
			$result->addValue( null, 'result', new stdClass() );
			return;
		}
		
		$users = explode(',', $users);
		$userlist = array();

		if( $command == 'GetUsersById' ) {
			$userlist = UserArray::newFromIDs($users);
		}
		else if( $command == 'GetUsersByName' ) {
			$res = $this->getDB()->select(
				'user',
				'user_id',
				array( 'user_name' => $users )
			);
			foreach ( $res as $row ) {
				$userlist[] = User::newFromId($row->user_id);
			}
		}

		$response = array();
		foreach( $userlist as $user ) {
			$response[$user->getId()] = array(
			    'user_id'        => $user->getId(),
				'user_name'      => $user->getName(),
				'user_real_name' => $user->getRealName(),
				'user_email'     => $user->getEmail(),
				'user_page_url'  => $user->getUserPage()->getFullURL()
			);
		}
		
		//In MW there is no user "0", but in Q2A
		if( in_array( 0, $users ) ) {
			$user = User::newFromId(1); //WikiSysop;
			$response[0] = array(
			    'user_id'        => 0,
				'user_name'      => $user->getName(),
				'user_real_name' => $user->getRealName(),
				'user_email'     => $user->getEmail(),
				'user_page_url'  => $user->getUserPage()->getFullURL()
			);
		}

		// Open search results may be stored for a very long time
		$this->getMain()->setCacheMaxAge( $wgSearchSuggestCacheExpiry );
		$this->getMain()->setCacheMode( 'public' );

		// Set top level elements
		$result->addValue( null, 'users', $response );
	}
	
	public function isReadMode() { //Needed to be always available, even if read-api is not allowed
		return false;
	}

	public function getAllowedParams() {
		return array(
			'command' => null,
			'users'  => null,
			'secret' => null,
		);
	}

	public function getParamDescription() {
		return false;
		return array(
			'command' => 'GetUsersById|GetUsersByName',
			'users' => 'Comma seperated list of either user names or user ids. Depends on given command.',
		);
	}

	public function getDescription() {
		return false;
		return 'User information provider for the webplatform.org applications';
	}

	public function getExamples() {
		return false;
		return array(
			'api.php?action=webplatformauth&command=GetUsersById&users=45,23,56'
		);
	}

	public function getHelpUrls() {
		return 'https://www.webplatform.org';
	}

	public function getVersion() {
		return __CLASS__ . ': $Id: ApiWebPlatformAuth.php 6651 2012-09-30 22:33:29Z mglaser $';
	}
}
