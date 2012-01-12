<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 * 	  the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website's Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2011 (or current year) ExamNotes.net inc.
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms;

use Lampcms\Interfaces\RoleInterface;


/**
 * The reason for using this Base object and extending it
 * in WebPage is to be able to use some methods
 * from classes other than WebPage, which may also extend
 * this class
 * For example, a class may exist that accepts messages by email
 * Or accepts some data, including login in users using some
 * type of server other than web server
 *
 * @author Dmitri Snytkine
 *
 */
class Base extends LampcmsObject
{

	/**
	 * Premission required to access this script
	 *
	 * @var string
	 */
	protected $permission;



	/**
	 * Special type of permission check where we don't
	 * need to check the specific permission but only
	 * require the user to be logged in. This is faster
	 * than a full Access Control check.
	 *
	 * @var bool
	 */
	protected $membersOnly = false;


	/**
	 * Special type of permission check where we don't
	 * need to check the specific permission but only
	 * require the user to be NOT logged in. This is faster
	 * than a full Access Control check.
	 *
	 * @var bool
	 */
	protected $guestsOnly = false;


	/**
	 *
	 * Flag used for memoization
	 * to speed up resolving isLoggedIn()
	 *
	 * @var bool
	 */
	protected $bLoggedIn;

	/**
	 * Registry Object
	 *
	 * @var object of type \Lampcms\Registry
	 */
	protected $Registry;


	public function __construct(Registry $Registry){
		$this->Registry = $Registry;
	}


	/**
	 * Given the $resourceId and $ip this functon
	 * creates a record in RESOURCE_LOCATION collection (or any other collection name specified in arguments)
	 *
	 * @param string $ip ip address or host name from where resource was submitted
	 *
	 * @param array $arrExtra associative array of key=>value can be passed here with extra data
	 * this data will be added to geoIP array so that any additional data can then be inserted into
	 * collection together with GeoIP data.
	 *
	 * @param string $collection a name of mongo collection where the GeoIP data will be inserted
	 *
	 * @param string $columnName a name of database table column that can be used instead of the default 'resouce_id'
	 * the value of $resourceId will be recorded in that column.
	 *
	 * @return bool true or false
	 */
	public function saveResourceLocation($resourceId = '', $ip = '', array $arrExtra = array(),
	$collection = 'RESOURCE_LOCATION', $columnName = 'i_res_id',
	$addIp = true){

		if (empty($ip) || empty($resourceId)) {
			return false;
		}

		$aLocation = $this->Registry->Geo->getLocation($ip)->data;

		if ( empty($aLocation) && 'RESOURCE_LOCATION' === $collection) {
			d( 'Did not find location for this ip: '.$ip );

			return false;
		}


		if (null !== $columnName) {
			$arrExtra[$columnName] = $resourceId;
		}

		if ($addIp) {
			$arrExtra['ip'] = $ip;
		}


		$arrData = $arrExtra + $aLocation;
		d( '$arrData: '.print_r( $arrData, true ) );

		d('collName: '.$columnName);

		/**
		 * Ensure index on column $columnName
		 *
		 */
		$this->Registry->Mongo->getCollection($collection)->ensureIndex(array($columnName => 1));

		$saved = $this->Registry->Mongo->insertData($collection, $arrData, null);
		d( '$saved: '.$saved );

		return (bool)$saved;

	}


	/**
	 *
	 * Check if a user has permission
	 * either on a specific resource
	 * or on site's permission
	 *
	 * This is basically a wrapper for Zend_Acl isAllowed()
	 * method, but the order of params is different here
	 * because we tend to most often
	 * only have the privilege param and $role is
	 * usually a Viewer and $resource is usually omitted
	 * because we are checking a site-wide permission
	 *
	 * @param string $privilege name of privilege (like 'add_comments')
	 *
	 * @param object $role our User Object is fine because it implements Zned_Acl_Role_Interface
	 *
	 * @param mixed $resource object or string name of resource
	 *
	 * @return mixed object $this if everything is OK
	 * OR throws exception is access is denied
	 *
	 * @throws If permission is denied, then we throw a special
	 * Exception: Lampcms\AuthException if user is not logged in,
	 * which would cause the template to present a login form
	 * on the error page
	 *
	 * OR Lampcms\AccessException if user is logged in
	 * which would mean a user does not have appropriate
	 * access privileges
	 */
	public function checkAccessPermission($privilege = null, RoleInterface $role = null, $resource = null){

		//d('$privilege: '.$privilege.' '.var_export($privilege, true));

		if(null === $privilege){
			d('$privilege is null');

			if(!isset($this->permission)){

				d('$this->permission not set');

				return $this;
			}

			$privilege = $this->permission;
		}

		/**
		 * If $role is not passed here
		 * then we use the current user ($this->oViewer)
		 * but we must reload the user details because
		 * otherwise the data may be somewhat stale - use object
		 * is stored in session and what if admin has banned
		 * the user after the user logged in or maybe
		 * admin demoted the user from moderator to
		 * normal user or assigned a user to 'spammers' group
		 * This is why we need the very latest user data,
		 * so we get the user object (User) from
		 * session ($this->oViewer usually points to object in session)
		 * then we call the reload() method which basically replaces that array
		 * of data with the fresh new array. The fresh new array is still
		 * taken via cache, so if user data has not changed, then the whole
		 * reload operation does not require even a single sql select
		 *
		 */
		//d('role: '.$role. ' $this->Registry->Viewer: '.$this->Registry->Viewer);

		/**
		 * How not to reload the object?
		 * The only way is to NOT store Viewer in session at all
		 * if only storing uid in session then
		 * initViewer would always get data from USERS collection
		 * on every visit instead of from SESSION
		 * Will be easier to maintain, session objects will be smaller
		 * Can still have custom session handler to store
		 * location, username, avatar? Not sure yet
		 * If in initViewer will have something like $_SESSION['username']
		 * = Viewer->getScrenName().... then yes.
		 *
		 * But it would mean lots of calls to viewer object on every
		 * page load. Is this big deal to make extra 2 calls to already
		 * inflated object? No!
		 *
		 * Problem with this approach is that we will lose
		 * the class type. For example, if Viewer is TwitterUser
		 * or FacebookUser, then will will lose this ability to
		 * differentiate user types. It's just better to
		 * reload viewer here, it's not all that slow - Mongo
		 * select is fast!
		 *
		 */

		$role = (null !== $role) ? $role : $this->Registry->Viewer;

		$Tr = $this->Registry->Tr;
		/**
		 * oACL can be cached, which saves about 5-7 milliseconds
		 * on my dev machine. The downside is that if you
		 * edit acl.ini you must manually remove
		 * Acl key from cache. (from C_Cache collection)
		 */

		$oACL = $this->Registry->Acl;

		$roleID = $role->getRoleId();
		d('$roleID '.$roleID.' $privilege: '.$privilege);

		if(!$oACL->isAllowed($role, $resource, $privilege)){
			if(!$this->isLoggedin()){
				/**
				 * @todo translate string
				 */
				throw new AuthException($Tr->get('Please Register or Login to perform this action') );
			}

			if(\strstr($roleID, 'unactivated')){

				if(  ($role instanceof User) && strlen($role->email) > 6 ){
					/**
					 * @todo
					 * Translate string
					 */
					$email = $role->email;
					$err = \tplConfirmemail::parse(array('email' => $email,
							'notConfirmed' => $Tr->get('not validated'),
							'sendLink' => $Tr->get('send me validation link') )).'<br>';
				} else {
					$err = $Tr->get('You have not confirmed email address').'<br><a href="/settings/">'.$Tr->get('Request activation email').'</a><br>';
				}

				throw new UnactivatedException($err);

			}

			throw new AccessException($Tr->get('Your account does not have permission to perform this action'));
		}

		return $this;
	}


	/**
	 * Checks the access permissions for current page
	 * based on values of $this->bMembersOnly,
	 * $this->bGuestsOnly and logged in status
	 * For example, if page is available only
	 * to logged in users, the exception will be
	 * throws in guest tries to access it
	 *
	 * @return object $this
	 *
	 * @throws LampcmsException if access level
	 * error is detected
	 */
	protected function checkLoginStatus(){
		if ($this->membersOnly && !$this->isLoggedIn()) {
			d('cp must login');
			throw new MustLoginException('You must login to access this page');
		}

		if($this->guestsOnly && $this->isLoggedIn()){
			d('not a guest!');
			throw new MustLoginException('This page cannot be accessed by a logged in user');
		}

		return $this;
	}


	/**
	 *
	 * Check to see if current viewer
	 * is a logged in user or guest
	 *
	 * @return bool true if logged in Viewer
	 * is registered user of false if is guest
	 */
	public function isLoggedIn(){

		if(!isset($this->bLoggedIn)){
			d('bLoggedIn not set');
			$this->bLoggedIn = !$this->Registry->Viewer->isGuest();
			d('bLoggedIn now: '.$this->bLoggedIn);
		}

		return $this->bLoggedIn;
	}

}
