<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is licensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
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
 *       the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website\'s Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attributes
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
 * @copyright  2005-2012 (or current year) Dmitri Snytkine
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms\String;

use \Lampcms\Utf8String;

/**
 * Class for parsing html fragment
 * using DOMDocument class of php
 * and multibyte-safe regex functions
 *
 * All methods of this class are utf-8 safe
 *
 *
 * @author Dmitri Snytkine
 *
 */
class HTMLString extends \Lampcms\Dom\Document implements \Lampcms\Interfaces\LampcmsObject, \Serializable
{

    /**
     * Tracker flag to indicate that a method of this class
     * or sub-class added a CDATA node to the tree.
     * This would signal to importCDATA() that
     * this object should be reloaded
     * It's responsibility of implementing sub-class
     * to set this flag to true whenever a method
     * added a CDATA node to the tree
     *
     * @var bool
     */
    protected $bCDATA = false;


    /**
     * Factory method
     * Makes the object is this class
     * and load the html string, first wraps the html
     * string into the <div>
     *
     * @param mixed string | object of type Utf8String $oHtml
     * by being an object of type Utf8String it's guaranteed
     * to be in utf-8 charset
     *
     * @return object of this class
     *
     * @throws \Lampcms\DevException if unable to load the string
     */
    public static function stringFactory($s)
    {

        $Dom = new static();
        $Dom->preserveWhiteSpace = true;
        if (\is_string($s)) {
            $sHtml = $s;
        } elseif ($s instanceof \Lampcms\Utf8String) {
            $sHtml = $s->valueOf();
        } else {
            throw new \Lampcms\DevException('Input param $s must be string or instance of Utf8String. was: ' . var_export($s, true));
        }

        $ER = error_reporting(0);
        if (false === @$Dom->loadHTMLString($sHtml)) {
            throw new \Lampcms\DevException('Error. Unable to load html string: ' . $sHtml);
        }
        error_reporting($ER);
        \mb_regex_encoding('UTF-8');

        return $Dom;
    }


    /**
     * Same as loadHTMLString() only the input
     * is an object of type Utf8String
     *
     * @param Utf8String $Html
     *
     * @internal param \Lampcms\Utf8String $oHtml
     * @return bool
     */
    public function loadUTF8String(\Lampcms\Utf8String $Html)
    {
        $s = $Html->valueOf();

        return $this->loadHTMLString($s);
    }


    /**
     * Load html string into this object
     *
     * @param string $s
     * Must be absolutely sure that this string
     * is in a valid UTF-8 encoding!
     *
     * @return bool true if loadHTML() succeed or false if not
     */
    public function loadHTMLString($s)
    {
        /**
         * Extremely important to add the
         * <META CONTENT="text/html; charset=utf-8">
         * This is the ONLY way to tell the DOM (more specifically
         * the libxml) that input is in utf-8 encoding
         * Without this the DOM will assume that input is in the
         * default ISO-8859-1 format and then
         * will try to recode it to utf8
         * essentially it will do its own conversion to utf8,
         * messing up the string because it's already in utf8 and does not
         * need converting
         *
         * IMPORTANT: we are also wrapping the whole string in <div>
         * so that it will be easy to get back just the contents of
         * the first div
         *
         */
        $s = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN"
                      "http://www.w3.org/TR/REC-html40/loose.dtd">
			<head>
  			<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=utf-8">
			</head>
			<body><div>' . $s . '</div></body></html>';

        return $this->loadHTML($s);
    }


    /**
     * Get HTML fragment (the contents of the <body>,
     * without the actual <body> tag
     *
     * @throws \Lampcms\Exception
     * @return string HTML string, usually with html special
     */
    public function getHtml()
    {

        $s = $this->saveHTML();
        preg_match('/(\<body>\<div>)(.*)(\<\/div>\<\/body>)/sm', $s, $matches);

        if (!is_array($matches) || empty($matches[2])) {
            throw new \Lampcms\Exception('unable to extract string from result html: ' . $s);
        }

        return $matches[2];
    }


    /**
     * Get HTML fragment (the contents of the <body>,
     * without the actual <body> tag
     *
     * @throws \Lampcms\Exception
     * @return string XML string
     */
    public function getXML()
    {
        $this->preserveWhiteSpace = false;
        $this->documentElement->removeWhitespace();
        $s = $this->saveXML();
        preg_match('/(\<body>\<div>)(.*)(\<\/div>\<\/body>)/sm', $s, $matches);

        if (!is_array($matches) || empty($matches[2])) {
            throw new \Lampcms\Exception('unable to extract string from result html: ' . $s);
        }

        return $matches[2];
    }


    /**
     * Get only the text from this document.
     * It will basically strip all the tags and return only
     * text value of tags
     *
     * @return string plaintext
     */
    public function getText()
    {

        return $this->getElementsByTagName('body')->item(0)->textContent;
    }


    /**
     * Get the length of all text in this document,
     * not counting any of the html tags
     *
     * @return int length of text content
     */
    public function length()
    {

        return \mb_strlen($this->getText());
    }


    /**
     * Get all text nodes of this HTML string
     *
     * @return object of type DOMNodeList
     */
    public function getTextNodes()
    {
        return $this->xpath('//text()');
    }


    /**
     * Get count of words in this html document
     * This is the right way to get word count
     * from HTML doc. The simple way of strip_tags and
     * then explode by spaces will not work if
     * html string is just one long
     * string run together without white spaces
     * and using regex is usually not the best way
     * to deal with html string.
     *
     * Each Text Node element is then treated
     * as separate UTF8String object
     *
     * This way each text node is split by UTF-8 specific word
     * delimiters, making it return correct word count
     * for Any type of language (not only splitting by spaces but
     * by other accepted delimiters)
     *
     * The resulting word count will be accurate for arabic, chinese,
     * and probably all other languages
     *
     * @return int count of words in this html string
     */
    public function getWordsCount()
    {
        $count = 0;
        $Nodes = $this->getTextNodes();
        $len = $Nodes->length;
        if (!$Nodes || 0 === $len) {

            return 0;
        }

        for ($i = 0; $i < $len; $i += 1) {
            $UTF8String = Utf8String::stringFactory($Nodes->item($i)->data, 'utf-8', true);
            $count += $UTF8String->getWordsCount();
        }

        return $count;
    }


    /**
     *
     * Reloads the html into this object
     * This is useful to turn all the CDATA
     * sections into the actual DOM tree
     *
     * @return object $this
     */
    public function reload()
    {
        $this->loadHTML($this->saveHTML());

        return $this;
    }


    /**
     * Reload document only if CData has been
     * added anywhere in the document
     * This basically imports contents of CDATA section
     * into the DOM Tree so it's not just a string anymore
     * but a part of DOM
     *
     * @return object $this
     */
    public function importCDATA()
    {
        if ($this->bCDATA) {
            $this->reload();
            $this->bCDATA = false;
        }

        return $this;
    }


    /**
     *
     * @return bool true if this object
     * has CDATA section added by one of the
     * methods, false otherwise
     */
    public function hasCDATA()
    {

        return $this->bCDATA;
    }


    /**
     * @Important to override the one from parent
     * because parent's class returns the saveXML() version
     * and here we need HTML
     * (non-PHPdoc)
     * @see Lampcms\Interfaces.LampcmsObject::__toString()
     */
    public function __toString()
    {
        return $this->getHtml();
    }


    /**
     * Same as __toString(), just for
     * consistency with our String class
     *
     * @return string html contents of this String
     */
    public function valueOf()
    {
        return $this->getHTML();
    }


    /**
     * (non-PHPdoc)
     * @see Serializable::serialize()
     */
    public function serialize()
    {
        return $this->saveHTML();
    }


    /**
     * (non-PHPdoc)
     * @see Serializable::unserialize()
     */
    public function unserialize($serialized)
    {
        $this->loadHTML($serialized);
        $this->encoding = 'UTF-8';
        $this->preserveWhiteSpace = true;
        $this->registerNodeClass('DOMElement', '\\Lampcms\\Dom\\Element');
    }

}
