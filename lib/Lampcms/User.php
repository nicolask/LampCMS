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

/**
 * Class represents logged in User
 *
 * @author Dmitri Snytkine
 *
 */

use Lampcms\Interfaces\Answer;

class User extends \Lampcms\Mongo\Doc implements Interfaces\RoleInterface,
Interfaces\User,
Interfaces\TwitterUser,
Interfaces\FacebookUser,
Interfaces\TumblrUser,
Interfaces\BloggerUser,
Interfaces\LinkedinUser
{
	const COLLECTION = 'USERS';

	/**
	 * Special flag indicates that user has
	 * just registered.
	 * This flag stays only during
	 * the session, so for the whole duration of the session
	 * we know that this is a new user
	 * @var bool
	 */
	protected $bNewUser = false;


	/**
	 * Path to avatar image
	 * used for memoization
	 *
	 * @var string
	 */
	protected $avtrSrc;

	/**
	 * This is important to define
	 * because in some rare cases
	 * the value
	 * of $this->collectionName is lost
	 * during serialization/unserialization
	 *
	 *
	 * @var string
	 */
	//protected $collectionName = 'USERS';

	protected $nice = 'NICE';


	/**
	 * Factory method
	 *
	 * @param object $Registry Registry object
	 *
	 * @param array $a
	 *
	 * @return object of this class
	 */
	public static function factory(Registry $Registry, array $a = array()){
		$o = new static($Registry, 'USERS', $a);

		return $o;
	}


	/**
	 * (non-PHPdoc)
	 * @see Lampcms.ArrayDefaults::__get()
	 */
	public function __get($name){
		if('id' === $name){

			return $this->getUid();
		}

		return $this->offsetGet($name);
	}


	/**
	 * Getter for userID (value of USERS._id)
	 *
	 * @return int value of userid (value of USERS._id)
	 */
	public function getUid(){
		d('$this->keyColumn: '.$this->keyColumn);

		if (true !== $this->offsetExists($this->keyColumn)) {
			d('cp no key column '.$this->keyColumn);

			return 0;
		}

		return (int)$this->offsetGet($this->keyColumn);
	}


	/**
	 *
	 * Get link to user's external url
	 *
	 * @return html of link to user's external page
	 */
	public function getUrl(){
		$url = $this->offsetGet('url');
		if(empty($url)){
			return '';
		}

		return '<a rel="nofollow" href="'.$url.'">'.$url.'</a>';
	}


	/**
	 * Check to see if user is registered
	 * or guest user
	 *
	 * Guest always has uid === 0
	 *
	 * @return bool true if user is a guest,
	 * false if registered user
	 */
	public function isGuest(){
		$uid = $this->getUid();
		d('uid: '.$uid);

		return 0 === $uid;
	}


	/**
	 * Check if user is moderator, which
	 * includes all types of moderator or admin
	 *
	 * @return bool true if moderator, falst otherwise
	 */
	public function isModerator(){
		$role = $this->getRoleId();

		return  (('administrator' === $role) || false !== (\strstr($role, 'moderator')) );
	}

	/**
	 * Check if user is administrator
	 *
	 * @return bool
	 *
	 */
	public function isAdmin(){

		return  ('administrator' === $this->getRoleId());
	}


	/**
	 * Get full name of user
	 * by concatinating first name, middle name, last name
	 *
	 * @return string full name
	 */
	public function getFullName(){
		return $this->offsetGet('fn').' '.$this->offsetGet('mn').' '.$this->offsetGet('ln');
	}


	/**
	 * Get string to display as user name
	 * preferrably it's a full name, but if
	 * user has not yet provided it, then
	 * user just 'username'
	 *
	 * @return string value to display on welcome block
	 */
	public function getDisplayName(){
		$ret = $this->getFullName();
		/**
		 * Must trim, otherwise
		 * we can have a string with just 2 spaces, which
		 * is not considered empty.
		 */
		$ret = \trim($ret);
		
		return (!empty($ret)) ? $ret : $this->offsetGet('username');
	}


	public function __set($name, $val){

		throw new DevException('Should not set property of User as object property');
		
	}


	/**
	 * Return HTML code for avatar image (with full path)
	 *
	 * @param string $sSize type of avatar: large, medium, tiny
	 *
	 * @param bool $boolNoCache if true, then add a timestamp to url, making
	 * browser not to use cached version and to get a fresh new one
	 *
	 * @return string the HTML code for image src
	 */
	public function getAvatarImgSrc($sSize = 'medium', $noCache = false){
		$strAvatar = '<img src="' . $this->getAvatarSrc($noCache) . '" class="img_avatar" width="40" height="40" border="0" alt="avatar">';

		return $strAvatar;

	}


	/**
	 * Get only the http path to avatar without
	 * the html img tag.
	 *
	 * @return string path to avatar image medium size
	 */
	public function getAvatarSrc($noCache = false){

		if(!isset($this->avtrSrc)){

			$srcAvatar = \trim($this->offsetGet('avatar'));
			if(empty($srcAvatar)){
				$srcAvatar =  \trim($this->offsetGet('avatar_external'));
			}

			/**
			 * If no own avatar and no avatar_external
			 * then try to get gravatar IF gravatar support
			 * is enabled in !config.ini [GRAVATAR] section
			 */
			if(empty($srcAvatar)){
				$email = $this->offsetGet('email');
				$aGravatar = array();
				try{
					$aGravatar = $this->getRegistry()->Ini->getSection('GRAVATAR');
					if(!empty($email) && (count($aGravatar) > 0)){

						return $aGravatar['url'].hash('md5', $email).'?s='.$aGravatar['size'].'&d='.$aGravatar['fallback'].'&r='.$aGravatar['rating'];
					}
						
				} catch (\Exception $e){
					e('exception: '.$e->getMessage());
				}

			}
				
			/**
			 * If still no avatar (no gravatar support
			 * of gravatar failed)
			 * then return default image
			 */
			if(empty($srcAvatar)){
				return LAMPCMS_IMAGE_SITE.'/images/avatar.png';
			}

			/**
			 * Path to avatar may be a relative path
			 * if this is our own avatar
			 * or absolute path if this is an external avatar
			 * like avatar from Twitter or FC or GFC
			 *
			 */
			$this->avtrSrc = (0 === \strncmp($srcAvatar, 'http', 4)) ? $srcAvatar : LAMPCMS_AVATAR_IMG_SITE.PATH_WWW_IMG_AVATAR_SQUARE.$srcAvatar;

			if ($noCache) {
				$this->avtrSrc .= '?id=' . microtime(true); // helps browser to NOT cache this image
			}

		}

		return $this->avtrSrc;
	}


	/**
	 * Get path to user profile (excluding the domain name part)
	 *
	 * @return string relative url to the user profile page
	 */
	public function getProfileUrl(){

		return '/users/'.$this->getUid().'/'.$this->offsetGet('username');
	}


	/**
	 * Implements Zend_Acl_Role_Interface
	 * (non-PHPdoc)
	 *
	 * @todo check if needs changing
	 *
	 * @see classes/Zend/Acl/Role/Zend_Acl_Role_Interface#getRoleId()
	 * @return string the value of user_group_id of user which
	 * serves as the role name in Zend_Acl
	 */
	public function getRoleId(){

		$role = $this->offsetGet('role');

		return (!empty($role)) ? $role : 'guest';
	}


	/**
	 * Setter for 'role' key
	 *
	 * This setter validates the role name
	 * to be one in the ACL file
	 *
	 * @param string $role
	 */
	public function setRoleId($role){
		if(!\is_string($role)){
			throw new \InvalidArgumentException('$role must be a string. was: '.gettype($role));
		}

		$a = $this->getRegistry()->Acl->getRegisteredRoles();
		if(!\array_key_exists($role, $a)){
			throw new \Lampcms\DevException('The $role name: '.$role.' is not one of the roles in the acl.ini file');
		}

		/**
		 * IMPORTANT: do not make a mistake
		 * of using $this->offsetSet()
		 * because it will point back to
		 * this function and start
		 * an evil infinite loop untill we will
		 * run out of memory
		 */
		parent::offsetSet('role', $role);

		return $this;
	}


	/**
	 * Get twitter user_id of user
	 * @return int
	 */
	public function getTwitterUid(){

		return $this->offsetGet('twitter_uid');
	}


	/**
	 * Get html of the link to User's Twitter page
	 * If user has Twitter account
	 *
	 * @return string html code for link or
	 * empty string if user does not have Twitter account
	 */
	public function getTwitterUrl(){
		$user = $this->getTwitterUsername();
		if(empty($user)){
			return '';
		}

		return '<a rel="nofollow" class="twtr" href="http://twitter.com/'.$user.'">@'.$user.'</a>';
	}


	/**
	 * Get oAuth token
	 * that we got from Twitter for this user
	 * @return string
	 */
	public function getTwitterToken(){
		return $this->offsetGet('oauth_token');
	}


	/**
	 * Get oAuth sercret that we got for this user
	 * @return string
	 */
	public function getTwitterSecret(){
		return $this->offsetGet('oauth_token_secret');
	}


	/**
	 * (non-PHPdoc)
	 * @see Lampcms\Interfaces.TwitterUser::getTwitterUsername()
	 */
	public function getTwitterUsername(){
		return $this->offsetGet('twtr_username');
	}


	/**
	 * Empty the values of oauth_token
	 * and oauth_token_secret
	 * and save the data
	 *
	 * @return object $this
	 */
	public function revokeOauthToken(){
		d('Revoking user OauthToken');
		$this->offsetUnset('oauth_token');
		$this->offsetUnset('oauth_token_secret');
		$this->save();

		/**
		 * Since oauth_token and oauth_secret are not store in
		 * USER table
		 * we actually need to update the USERS_TWITTER Collection
		 */
		$this->getRegistry()->Mongo->USERS_TWITTER->remove(array('_id' => $this->getTwitterUid()));
		d('revoked Twitter token for user: '.$uid);

		return $this;
	}


	/**
	 * Unsets the fb_id from the object, therefore
	 * marking user as NOT connected to facebook
	 * account
	 *
	 * @return object $this;
	 */
	public function revokeFacebookConnect(){
		/**
		 * Instead of offsetUnset we do
		 * offsetSet and set to null
		 * This is necessary in case user
		 * does not have this key yet,
		 * in which case offsetUnset will raise error
		 */
		$this->offsetSet('fb_token', null);
		$this->save();

		$this->getRegistry()->Mongo->USERS_FACEBOOK->update(array('i_uid' => $this->getUid()), array('$set' => array('access_token' => '')));
		d('revoked FB token for user: '.$uid);

		return $this;
	}


	/**
	 * (non-PHPdoc)
	 * @see Lampcms\Interfaces.FacebookUser::getFacebookUid()
	 */
	public function getFacebookUid(){

		return (string)$this->offsetGet('fb_id');
	}


	/**
	 * Get html for the link to user's
	 * Facebook profile
	 *
	 * @return string empty string if user does not
	 * have fb_url value or html fragment for the link
	 * to this User's Facebok page
	 */
	public function getFacebookUrl(){
		$url = $this->offsetGet('fb_url');
		if(empty($url)){
			return '';
		}

		$name = $this->offsetGet('fn').' '.$this->offsetGet('ln');

		$name = (!empty($name)) ? $name : $url;

		return '<a rel="nofollow" class="fbook" href="'.$url.'">'.$name.'</a>';
	}


	/**
	 * (non-PHPdoc)
	 * @see Lampcms\Interfaces.FacebookUser::getFacebookToken()
	 */
	public function getFacebookToken(){
		return $this->offsetGet('fb_token');
	}


	/**
	 * (non-PHPdoc)
	 * @see Lampcms.LampcmsArray::__toString()
	 */
	public function __toString(){
		return 'object of type '.$this->getClass().' for userid: '.$this->getUid();
	}


	/**
	 * Setter for bNewUser
	 *
	 * @return object $this
	 */
	public function setNewUser(){
		$this->bNewUser = true;

		return $this;
	}


	/**
	 * Getter for this->bNewUser
	 *
	 * @return bool true indicates that this object
	 * represents a new user
	 */
	public function isNewUser(){
		return $this->bNewUser;
	}


	/**
	 * Unique hash code for one user
	 * This is useful for generating etag of cache headers
	 * User is considered the same user if
	 * Array of data is the same as well as class name
	 * and bNewUser status.
	 * So if user changed something that may result in
	 * different info on welcome block
	 * or different permissions for the user
	 * like name or avatar
	 * or usergroup id
	 * then cached page should not be shown.
	 *
	 * @return string unique to each user
	 *
	 */
	public function hashCode(){
		$a = $this->getArrayCopy();

		return \hash('md5', \json_encode($a).$this->getClass().$this->bNewUser);
	}


	/**
	 * If user has valid value of 'tz'
	 * then use it to set global time
	 * This is the time that will be used
	 * during the rest of the script execution
	 *
	 * @return object $this
	 */
	public function setTime(){
		$tz = $this->offsetGet('tz');
		if(!empty($tz)){
			if (false === @\date_default_timezone_set( $tz )) {
				d( 'Error: wrong value of timezone: '.$tz );
			}
		}

		return $this;
	}


	/**
	 * Setter for value of $tz (timezone)
	 * it will first check to make
	 * sure the $tz is a valid timezone name
	 *
	 * @param string $tz
	 */
	public function setTimezone($tz){
		if(!\is_string($tz)){
			throw new DevException('Param $tz must be string. Was: '.gettype($tz));
		}

		$currentTz = \date_default_timezone_get();
		if (false !== @\date_default_timezone_set( $tz )) {
			parent::offsetSet('tz', $tz);
		} else {
			@\date_default_timezone_set($currentTz);
		}

		return $this;
	}


	/**
	 * Getter for value of 'tz' (timezone) value
	 *
	 * @return string valid value of Timezone name or string
	 * if no value was previously set
	 */
	public function getTimezone(){
		return $this->offsetGet('tz');
	}


	public function setLocale($locale){
		if(!\is_string($locale)){
			throw new \InvalidArgumentException('Param $locale must be a string');
		}

		$locale = str_replace('-', '_', $locale);

		if(2 !== strlen($locale) && (!preg_match('/[a-z]{2}_[A-Z]{2}/', $locale))){
			$err = 'Param $locale is invalid. Must be in a form on "en_US" format (2 letter lang followed by underscore followed by 2-letter country';
			e($err);
			throw new \InvalidArgumentException($err);
		}

		if(!$this->isGuest()){
			$this->offsetSet('locale', $locale);

			$this->save();
		}

		return $this;
	}


	public function getLocale(){
		$locale = $this->offsetGet('locale');

		return (!empty($locale)) ? $locale : LAMPCMS_DEFAULT_LOCALE;
	}


	/**
	 * Change the 'role' to 'registered' if
	 * user has 'unactivated' or 'unactivated_external' role
	 *
	 * @return object $this
	 */
	public function activate(){
		$role = $this->offsetGet('role');

		d('activating user '.$this->getUid().' role: '.$role);
		if( \strstr($role, 'unactivated')){
			$this->setRoleId('registered');
		} else {
			d('Cannot activate this user because the current role is: '.$role);
		}


		return $this;
	}

	/**
	 * Check if this user has same userID
	 * as user object passed to this method
	 *
	 * @param User $user another User object
	 *
	 * @return bool true if User object passed here has the same user id
	 *
	 */
	public function equals(User $User){

		return ($User->getUid() === $this->getUid());
	}


	/**
	 * Test to see if this user has permission
	 *
	 * @param string $permission
	 * @return bool true if User has this permission, false otherwise
	 */
	public function isAllowed($permission){
		return $this->getRegistry()->Acl->isAllowed($this->getRoleId(), null, $permission);
	}


	/**
	 * Change reputation score
	 * Makes sure new score can never go lower than 1
	 * @param int $iPoints
	 *
	 * @return object $this
	 */
	public function setReputation($iPoints){
		if(!\is_numeric($iPoints)){
			throw new DevException('value of $iPoints must be numeric, was: '.$iPoints);
		}

		$iRep = $this->offsetGet('i_rep');
		$iNew = max(1, ($iRep + (int)$iPoints));

		/**
		 * @todo investigate where reputation is set directly
		 * using assignment operator $User['i_rep'] = $x
		 * and change it to use proper setReputation method
		 * then stop using parent::offsetSet()
		 */
		parent::offsetSet('i_rep', $iNew);

		return $this;
	}


	/**
	 *
	 * Get reputation score of user
	 *
	 * @return int reputation of user, with minimum of 1
	 */
	public function getReputation(){

		return max(1, $this->offsetGet('i_rep'));
	}


	/**
	 * Get Location of user based on GeoIP data
	 * (or data
	 * that user has entered in profile, which will
	 * override the GeoIP data that we get during
	 * the registration)
	 *
	 * @return string
	 */
	public function getLocation(){
		$cc = $this->offsetGet('cc');
		$cn = $this->offsetGet('cn');
		$state = $this->offsetGet('state');
		$city = $this->offsetGet('city');

		$ret = '';

		if(!empty($city)){
			$ret .= $city;
		}

		if(!empty($state) && ('US' === $cc || 'CA' === $cc)){
			$ret .= (!empty($city) ) ? ', '.$state : $state;
		}

		if(!empty($cn)){
			$ret .= ' '.$cn;
		}

		return $ret;
	}


	/**
	 * Update i_lm_ts timestamp
	 * IF it is older than 5 minutes.
	 * So while this method is going to be
	 * called on every page load (from Registry destructor)
	 * it will not be updated unless its older than 5 minutes
	 *
	 *
	 * @return object $this
	 */
	public function setLastActive(){
		$lastActive = $this->offsetGet('i_lm_ts');
		d('$lastActive was: '.$lastActive);
		$now = time();
		if((($now - $lastActive) > 300) && !$this->isGuest()){
			d('updating i_lm_ts of user');
			$this->offsetSet('i_lm_ts', $now);
		}

		return $this;
	}


	/**
	 * Get user's age (in years)
	 * at the time of this request
	 *
	 * @return string empty string if 'dob' value
	 * is empty or string age in years
	 *
	 */
	public function getAge(){
		$dob = $this->offsetGet('dob');
		if(empty($dob)){
			return '';
		}

		$oDOB = new \DateTime($dob);
		$oDiff = $oDOB->diff(new \DateTime(), true);

		return $oDiff->format("%y");
	}


	/**
	 * Get array of blogs
	 *
	 * @return array
	 */
	public function getTumblrBlogs(){
		$a = $this->offsetGet('tumblr');
		if(empty($a) || empty($a['blogs'])){
			return null;
		}

		return $a['blogs'];
	}


	/**
	 * Set value of tumblr['blogs']
	 *
	 * @param array $blogs array of blogs
	 *
	 * @return object $this
	 */
	public function setTumblrBlogs(array $blogs){
		$a = $this->offsetGet('tumblr');
		if(!empty($a) && !empty($a['blogs'])){
			$a['blogs'] = $blogs;
			$this->offsetSet('tumblr', $a);
		}

		return $this;
	}


	/**
	 * Get array of all user's blogs
	 * @return mixed array of at least one blog | null
	 * if user does not have any blogs (not a usual situation)
	 *
	 */
	public function getTumblrToken(){
		$a = $this->offsetGet('tumblr');
		if(empty($a) || empty($a['tokens'])){
			return null;
		}

		return $a['tokens']['oauth_token'];
	}


	/**
	 * Get oAuth sercret that we got for this user
	 * @return string
	 */
	public function getTumblrSecret(){
		$a = $this->offsetGet('tumblr');
		if(empty($a) || empty($a['tokens'])){
			return null;
		}

		return $a['tokens']['oauth_token_secret'];
	}


	/**
	 * Set the value of ['tumblr']['tokens'] to null
	 * This removes both the token and token_secret
	 * for the tumblr oauth credentials
	 *
	 * @return object $this
	 */
	public function revokeTumblrToken(){
		$a = $this->offsetGet('tumblr');
		if(!empty($a) && !empty($a['tokens'])){
			$a['tokens'] = null;
			$this->offsetSet('tumblr', $a);
		}

		return $this;
	}


	/**
	 * Get html for the link to tumblr blog
	 *
	 * @return string html of link
	 */
	public function getTumblrBlogLink(){
		$a = $this->offsetGet('tumblr');
		if(empty($a) || empty($a['blogs'])){
			return '';
		}

		$aBlog = $a['blogs'][0];
		if(!empty($aBlog['url']) && !empty($aBlog['title'])){
			$tpl = '<a href="%s" class="tmblr" rel="nofollow">%s</a>';

			return sprintf($tpl, $aBlog['url'], $aBlog['title']);
		}

		return '';
	}


	/**
	 * Get Title of "default" Tumblr Blog
	 * Default blog is the one blog
	 * that user connected to this site
	 * If user has only one blog on Tumblr then
	 * it's automatically is default blog
	 *
	 * (non-PHPdoc)
	 * @see Lampcms\Interfaces.TumblrUser::getTumblrBlogTitle()
	 */
	public function getTumblrBlogTitle(){
		$a = $this->offsetGet('tumblr');
		if(empty($a) || empty($a['blogs'])){
			return '';
		}

		$aBlog = $a['blogs'][0];

		return(!empty($a['blogs'][0]['title'])) ? $a['blogs'][0]['title'] : '';
	}


	/**
	 * Get full url of the "default" Tumblr blog
	 * Default blog is the one blog
	 * that user connected to this site
	 * If user has only one blog on Tumblr then
	 * it's automatically is default blog
	 *
	 * (non-PHPdoc)
	 * @see Lampcms\Interfaces.TumblrUser::getTumblrBlogUrl()
	 */
	public function getTumblrBlogUrl(){
		$a = $this->offsetGet('tumblr');
		if(empty($a) || empty($a['blogs'])){
			return '';
		}

		$aBlog = $a['blogs'][0];

		return(!empty($a['blogs'][0]['url'])) ? $a['blogs'][0]['url'] : '';
	}


	/**
	 * (non-PHPdoc)
	 * @see Lampcms\Interfaces.TumblrUser::getTumblrBlogId()
	 */
	public function getTumblrBlogId(){
		$a = $this->offsetGet('tumblr');
		if(empty($a) || empty($a['blogs']) || empty($a['blogs'][0])){
			throw new DevException('User does not have any blogs on Tumblr');
		}

		$blog = $a['blogs'][0];
		if(!empty($blog['private-id'])){
			return $blog['private-id'];
		}

		if(!empty($blog['name'])){
			return $blog['name'].'.tumblr.com';
		}

		return null;
	}


	/**
	 * (non-PHPdoc)
	 * @see Lampcms\Interfaces.BloggerUser::getBloggerToken()
	 */
	public function getBloggerToken(){
		$a = $this->offsetGet('blogger');
		if(empty($a) || empty($a['tokens'])){
			return null;
		}

		return $a['tokens']['oauth_token'];
	}


	/**
	 * (non-PHPdoc)
	 * @see Lampcms\Interfaces.BloggerUser::getBloggerSecret()
	 */
	public function getBloggerSecret(){
		$a = $this->offsetGet('blogger');
		if(empty($a) || empty($a['tokens'])){
			return null;
		}

		return $a['tokens']['oauth_token_secret'];
	}


	/**
	 * (non-PHPdoc)
	 * @see Lampcms\Interfaces.BloggerUser::revokeBloggerToken()
	 */
	public function revokeBloggerToken(){
		$a = $this->offsetGet('blogger');
		if(!empty($a) && !empty($a['tokens'])){
			$a['tokens'] = null;
			$this->offsetSet('blogger', $a);
		}

		return $this;
	}


	/**
	 * Get html for the link to Blogger blog
	 * @return string html of link
	 */
	public function getBloggerBlogLink(){
		$a = $this->offsetGet('blogger');
		if(empty($a) || empty($a['blogs'])){
			return '';
		}

		$aBlog = $a['blogs'][0];
		if(!empty($aBlog['url']) && !empty($aBlog['title'])){
			$tpl = '<a href="%s" class="blgr" rel="nofollow">%s</a>';

			return sprintf($tpl, $aBlog['url'], $aBlog['title']);
		}

		return '';
	}


	/**
	 * Get array of all user's blogs
	 * @return mixed array of at least one blog | null
	 * if user does not have any blogs (not a usual situation)
	 *
	 */
	public function getBloggerBlogs(){
		$a = $this->offsetGet('blogger');
		if(empty($a) || empty($a['blogs'])){
			return null;
		}

		return $a['blogs'];
	}


	/**
	 * (non-PHPdoc)
	 * @see Lampcms\Interfaces.BloggerUser::getBloggerBlogTitle()
	 */
	public function getBloggerBlogTitle(){
		$a = $this->offsetGet('blogger');
		if(empty($a) || empty($a['blogs'])){
			return '';
		}

		return $a['blogs'][0]['title'];
	}


	/**
	 * (non-PHPdoc)
	 * @see Lampcms\Interfaces.BloggerUser::getBloggerBlogUrl()
	 */
	public function getBloggerBlogUrl(){
		$a = $this->offsetGet('blogger');
		if(empty($a) || empty($a['blogs'])){
			return '';
		}

		return $a['blogs'][0]['url'];
	}


	/**
	 * @return string value to be used as '<blogid>' param
	 * in WRITE API call
	 *
	 */
	public function getBloggerBlogId(){
		$a = $this->offsetGet('blogger');
		if(empty($a) || empty($a['blogs']) || empty($a['blogs'][0])){
			throw new DevException('User does not have any blogs on Blogger');
		}

		return $a['blogs'][0]['id'];
	}


	/**
	 * (non-PHPdoc)
	 * @see Lampcms\Interfaces.BloggerUser::setBloggerBlogs()
	 */
	public function setBloggerBlogs(array $blogs){
		$a = $this->offsetGet('blogger');
		if(!empty($a) && !empty($a['blogs'])){
			$a['blogs'] = $blogs;
			$this->offsetSet('blogger', $a);
		}

		return $this;
	}


	/**
	 * Some keys should not be set directly
	 * but instead use proper setter methods
	 *
	 * @todo must go over all classes and see
	 * which classes set values directly using
	 * ->offsetSet or as assignment
	 * and add some of the more important
	 * keys here. For example: language, locale,
	 * ,username(maybe), pwd(maybe), email,
	 * timezone should go through validation
	 *
	 * (non-PHPdoc)
	 * @see ArrayObject::offsetSet()
	 */
	public function offsetSet($index, $newval){
		switch($index){
			case 'role':
				throw new DevException('User Role cannot be set directly, must be set using setRoleId() method');
				//$this->setRoleId($newval);
				break;

			case 'i_rep':
				throw new DevException('value of i_rep cannot be set directly. Use setReputation() method');
				break;

			case 'tz':
			case 'timezone':
				//$this->setTimezone($newval);
				throw new DevException('Value of timezone should be set using setTimezone() method');
				break;

			default:
				parent::offsetSet($index, $newval);
		}
	}


	/**
	 * Get LinkedIn oAuth token
	 *
	 * @return mixed string | null if not found
	 */
	public function getLinkedinToken(){
		$a = $this->offsetGet('linkedin');
		if(empty($a) || empty($a['tokens'])){
			return null;
		}

		return $a['tokens']['oauth_token'];
	}


	/**
	 * Get LinkedIn oAuth sercret
	 *
	 * @return mixed string | null if not found
	 */
	public function getLinkedinSecret(){
		$a = $this->offsetGet('linkedin');
		if(empty($a) || empty($a['tokens'])){
			return null;
		}

		return $a['tokens']['oauth_token_secret'];
	}


	/**
	 * Revoke token and secret - remove
	 * these values from User object
	 *
	 * @return object $this
	 */
	public function revokeLinkedinToken(){
		$a = $this->offsetGet('linkedin');
		if(!empty($a) && !empty($a['tokens'])){
			$a['tokens'] = null;
			$this->offsetSet('linkedin', $a);
		}

		return $this;
	}


	/**
	 * Get html for the link to tumblr blog
	 * @return string html of link
	 */
	public function getLinkedinUrl(){
		$a = $this->offsetGet('linkedin');

		return (empty($a) || empty($a['url'])) ? '' : $a['url'];
	}

	/**
	 * Get html for the link to LinedIn Profile
	 * @return string html of link
	 */
	public function getLinkedinLink(){
		$url = $this->getLinkedinUrl();
		d('url: '.$url);

		$tpl = '<a href="%s" class="linkedin" rel="nofollow" target="_blank">%s</a>';

		return (empty($url)) ? '' : \sprintf($tpl, $url, 'LinkedIn Profile');
	}


	/**
	 * Get value of 'group' or 'private-id'
	 * This is used for indicating which blog
	 * the post will go to. It is needed
	 * in case when user has more than one blog
	 * on Linkedin.
	 * If user has only one blog we still use this param
	 * for consistancy
	 *
	 * @return string value to be used as 'group' param
	 * in WRITE API call
	 *
	 */
	public function getLinkedinId(){
		return (string)$this->offsetGet('linkedin_id');
	}

	/**
	 * Do not want auto-save on destruction
	 *
	 *
	 * (non-PHPdoc)
	 * @see Lampcms\Mongo.Doc::__destruct()
	 */
	public function __destruct(){}

	public function serialize(){
		$a = array('array' => $this->getArrayCopy(),
					'md5' => $this->md5,
					'bSaved' => $this->bSaved
		);

		return serialize($a);
	}


	/**
	 * (non-PHPdoc)
	 * @see Lampcms.ArrayDefaults::unserialize()
	 */
	public function unserialize($serialized){
		$a = unserialize($serialized);
		$this->exchangeArray($a['array']);
		$this->collectionName = 'USERS';
		$this->bSaved = $a['bSaved'];
		$this->keyColumn = '_id';
		$this->md5 = $a['md5'];
	}
}
