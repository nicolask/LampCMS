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

namespace Lampcms\Controllers;

use Lampcms\WebPage;

class Requestactivation extends WebPage
{
	/**
	 * @todo Translate strings (and make these instance variables
	 * instead of constants)
	 */
	const EMAIL_BODY = 'Welcome to %1$s!

IMPORTANT: You Must use the link below to activate your account
%2$s

	';
	/**
	 * @todo
	 * Translate String
	 */
	const SUBJECT = '%s account activation';

	/**
	 * @todo
	 * Translate String
	 */
	const SUCCESS = 'Activation instructions have just been emailed to you to %s';


	protected $membersOnly = true;

	protected $layoutID = 1;

	protected $oEmail;

	protected $email;

	protected function main(){
		/**
		 * @todo
		 * Translate String
		 */
		$this->aPageVars['title'] = $this->_('Request email confirmation');

		$this->getEmailObject()
		->makeActivationCode()
		->sendActivationEmail();

		$this->aPageVars['body'] = '<div id="tools">'.sprintf(self::SUCCESS, $this->email).'</div>';
	}


	/**
	 *
	 * Create object representing email address
	 * of current Viewer
	 *
	 * @throws \Lampcms\NoemailException if unable to find email address
	 * of current Viewer
	 *
	 * @return object $this
	 */
	protected function getEmailObject(){
		$this->email = strtolower($this->Registry->Viewer->email);
		if(empty($this->email)){
			/**
			 * @todo
			 * Translate String
			 */
			throw new \Lampcms\NoemailException($this->_('You have not selected any email address for your account yet') );
		}

		try{
			$this->oEmail = \Lampcms\Mongo\Doc::factory($this->Registry, 'EMAILS')->byEmail($this->email);
			if('' == $this->oEmail['email']){
				/**
				 * @todo
				 * Translate String
				 */
				throw new \Lampcms\NoemailException($this->_('You have not selected any email address for your account yet'));
			}
		} catch (\MongoException $e){
			/**
			 * @todo
			 * Translate String
			 */
			throw new \Lampcms\NoemailException($this->_('You have not selected any email address for your account yet') );

		}

		return $this;
	}



	protected function makeActivationCode(){

		if((!empty($this->oEmail['i_vts'])) && $this->oEmail['i_vts'] > 0){
			e('Account already activated. i_vts in aEmail: '.$this->oEmail['i_vts'].' $this->oEmail: '.print_r($this->oEmail->getArrayCopy(), 1));
			/**
			 * @todo
			 * Translate String
			 */
			throw new \Lampcms\Exception($this->_('This account has already been activated') );
		}

		$code = $this->oEmail['code'];

		if(empty($code)){
			$this->oEmail['code'] = substr(hash('md5', uniqid(mt_rand())), 0, 12);
		}

		$this->oEmail['i_code_ts'] = time();

		$this->oEmail['i_vts'] = null;
		$this->oEmail->save();

		return $this;
	}


	/**
	 * Email activation link to user
	 *
	 * @throws \Lampcms\Exception if unable to email
	 *
	 * @return object $this
	 */
	protected function sendActivationEmail(){
		$tpl = $this->Registry->Ini->SITE_URL.'/aa/%d/%s';
		$link = sprintf($tpl, $this->oEmail['_id'], $this->oEmail['code']);
		d('$link: '.$link);

		$siteName = $this->Registry->Ini->SITE_NAME;
		$body = vsprintf(self::EMAIL_BODY, array($siteName, $link));
		$subject = sprintf(self::SUBJECT, $siteName);
		$this->Registry->Mailer->mail($this->email, $subject, $body);

		return $this;
	}

}
