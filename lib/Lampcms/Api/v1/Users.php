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
 *    the website\'s Questions/Answers functionality is powered by lampcms.com
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


namespace Lampcms\Api\v1;

use Lampcms\Api\Api;

class Users extends Api
{
	/**
	 * Array of user IDs
	 *
	 * The list of user IDs can be passed in uids
	 * param in a form of semicolon separated values
	 * of up to 100 user IDs
	 *
	 * @var array
	 */
	protected $aUids;

	/**
	 * Allowed values of the 'sort' param
	 *
	 * @var array
	 */
	protected $allowedSortBy = array('_id', 'i_rep', 'i_lm_ts');

	protected $aFields = array(
			'_id' => 1, 
			'i_rep' => 1, 
			'username' => 1, 
			'fn' => 1, 
			'mn' => 1, 
			'ln' => 1,
			'avatar' => 1, 
			'avatar_external' => 1, 
			'i_reg_ts' => 1, 
			'i_lm_ts' => 1,
			'country' => 1,
			'state' => 1,
			'city' => 1,
			'url' => 1,
			'description' => 1
	);


	protected function main(){
		$this->pageID = $this->oRequest['pageID'];

		$this->setSortBy()
		->setUids()
		->setSortOrder()
		->setLimit()
		->getCursor()
		->setOutput();
	}


	/**
	 * If there is a "uids" param in request
	 * then use its value to extract 
	 * array of uids using the semicolon as separator
	 * 
	 * Use a maximum of this many ids.
	 * 
	 * @return object $this
	 */
	protected function setUids(){
		$uids = $this->oRequest->get('uids', 's');
		
		if(!empty($uids)){
			$this->aUids = explode(';', $uids);
			$total = count($this->aUids);
			if($total > 100){
				throw new \Lampcms\HttpResponseCodeException('Too many user ids passed in "uids" param. Must be under 100. Was: '.$total, 406);
			}
			
			/**
			 * IMPORTANT
			 * Must cast array elements to 
			 * integers, otherwise Mongo will 
			 * not be able to find any records because
			 * match is type-sensitive!
			 */
			array_walk($this->aUids, function(&$item, $key){
				$item = (int)$item;
			});

		}
		
		return $this;
	}

	
	protected function getCursor(){
		$sort[$this->sortBy] = $this->sortOrder;
		$offset = (($this->pageID - 1) * $this->limit);
		d('offset: '.$offset);

		$where = array('role' => array('$ne' => 'deleted'));
		
		if(isset($this->aUids)){
			$match = array('$in' => $this->aUids);
			$where['_id'] = $match;
		}

		d('$where: '.print_r($where, 1));
		
		$this->cursor = $this->oRegistry->Mongo->USERS->find($where, $this->aFields)
		->sort($sort)
		->limit($this->limit)
		->skip($offset);

		$this->count = $this->cursor->count();
		d('count: '.$this->count);

		if(0 === $this->count){
			d('No results found for this query: '.print_r($where, 1));

			throw new \Lampcms\HttpResponseCodeException('No matches for your request', 404);
		}

		return $this;
	}

	/**
	 *
	 * Set to $this->oOutput object with
	 * data from cursor
	 *
	 * @return object $this
	 */
	protected function setOutput(){

		$data = array('total' => $this->count,
		'page' => $this->pageID,
		'perpage' => $this->limit,
		'users' => \iterator_to_array($this->cursor, false));

		$this->oOutput->setData($data);

		return $this;
	}

}
