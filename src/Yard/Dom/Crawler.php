<?php
/**
 * Created by Rosario Grosso
 * Date: 01/08/2014
 * Time: 00:28
 * Copyright Rosario Grosso
 */
namespace Yard\Dom;

use Yard\Dom\Component\DomNodeList;

class Crawler implements CrawlerInterface{

    const TYPE_XML              = 'xml';
    const TYPE_HTML             = 'html';

    /**
     * @var Component\DomNodeList $contextNodesList
     */
    protected $contextNodesList = null;

    /**
     * Define the type of document (xml|html|xhtml). Look at TYPE_* constants.
     * @var string $type
     */
    protected $type             = null;

    /**
     * Document encoding
     * @var string $encoding
     */
    protected $encoding         = null;

    /**
     * If no encoding is passed or discovered, this one will be used.
     * @var string $encoding
     */
//    protected $defaultEncoding  = "UTF-8";
    protected $defaultEncoding  = "auto";

    /**
     * @var \DomXPath $domXpath
     */
    protected $domXpath         = null;

    /**
     * Contains the resulting nodelist.
     * @var Component\DomNodeList $domNodeList
     */
    protected $domNodeList      = null;


    public function __construct($content = null, $encoding = null, $type = null){
        if (!is_null($content)) {
            $this->loadContent($content, $encoding, $type);
        }
    }

    public function loadContent($content, $encoding = null, $type = null) {
        if (is_null($type)){
            $type = $this->guessType($content);
        }

        if (is_null($encoding)){
            $encoding = $this->guessEncoding($content);
            if (empty($encoding)) {
                $encoding = $this->defaultEncoding;
            }
        }
        $this->type = strtolower($type);
        $this->encoding = strtolower($encoding);
        $domDocument = $this->getDomDocumentFromHtmlContent($content, $this->encoding);
        $this->domXpath = new \DOMXPath($domDocument);
        return $this;
    }

    public function query($xpath) {
        $this->domNodeList = new DomNodeList();
        if ( null === $this->contextNodesList ) {
            $res = $this->domXpath->query($xpath);
//            $this->domNodeList = new DomNodeList();
            foreach ($res as $obj) {
                $this->domNodeList->attach($obj);
            }
        } else {
            foreach ($this->contextNodesList as $node) {
                $nodeHtml = $node->ownerDocument->saveXML($node);
                $tmpDomDoc = $this->getDomDocumentFromHtmlContent($nodeHtml, "auto");
                $tmpXpath = new \DOMXPath($tmpDomDoc);
                $res = $tmpXpath->query($xpath);
                // if the NodeList empty, set a null DomElement to maintain the context consistence.
                if ($res->length === 0) {
                    $this->domNodeList->attach( $this->getNullDomElement() );
                }
                foreach ($res as $obj) {
                    $this->domNodeList->attach($obj);
                }
            }
        }
        $this->contextNodesList = null;
        return $this;
    }

    public function context($xpath) {
        $tmpList = $this->domXpath->query($xpath);
        $this->contextNodesList = new DomNodeList();
        foreach ($tmpList as $node) {
            $this->contextNodesList->attach($node);
        }
        return $this;
    }

    public function each(\Closure $closure) {
        $aData = array();
        foreach ($this->domNodeList as $k => $node) {
            $aData[] = $closure($node, $k);
        }
        return $aData;
    }

    public function trim($characterMask = null) {
        foreach ($this->domNodeList as $node) {
            $node->nodeValue = is_null($characterMask) ? trim($node->nodeValue) : trim($node->nodeValue, $characterMask);
        }
        return $this;
    }
    public function cssQuery($xpath) {}
    public function cssContextQuery($contextXPath, $xpath) {}

    public function toString() {
        $sRet = null;
        foreach ($this->domNodeList as $node) {
            $sRet = $node->nodeValue;
            break;
        }
        return $sRet;
    }
    public function toArray() {
        $aRet = array();
        if (0 === $this->domNodeList->count()) {
            return $aRet;
        }
        foreach ($this->domNodeList as $node) {
            $aRet[] = $node->nodeValue;
        }
    return $aRet;
    }

    /**
     * Guess the Document Type (look at const TYPE_* for possible values).
     *
     * @param string $content
     * @return string
     */
    protected function guessType($content) {
        if (0 === strpos(trim($content), '<?xml ')) {
            return static::TYPE_XML;
        }
        return static::TYPE_HTML;
    }

    /**
     * Guess the Document encoding.
     * @param string $content
     * @return null|string
     */
    protected function guessEncoding($content) {
        if (   preg_match("~<\?xml\s.*encoding\s*=\s*[\"']?(?<ENCODING>[a-zA-Z0-9\-_]+)[\"']?~i", $content, $aMatch)
            || preg_match("~<meta[^\>]+charset\s*=\s*[\"']?(?<ENCODING>[a-zA-Z0-9\-_]+)[\"']?~i", $content, $aMatch)
        ) {
            return strtolower(trim($aMatch['ENCODING']));
        }
        return null;
    }

    /**
     * Transform the content(string) in a DomDocument.
     * @param string $content
     * @param string $encoding
     * @return \DOMDocument
     */
    protected function getDomDocumentFromHtmlContent($content, $encoding) {
        libxml_use_internal_errors(true);
        libxml_disable_entity_loader(true);

        $dom = new \DOMDocument('1.0', $encoding);
        // @codeCoverageIgnoreStart
        set_error_handler(function () use (&$hasError) {
            $hasError = true;
        });
        // @codeCoverageIgnoreEnd
        $tmpContent = @mb_convert_encoding($content, 'HTML-ENTITIES', $encoding);
        restore_error_handler();
        if (!$hasError) {
            $content = $tmpContent;
        }
        $content = trim($content);
        if ('' !== $content) {
            @$dom->loadHTML($content);
        }
        libxml_use_internal_errors(false);
        libxml_disable_entity_loader(false);
        return $dom;
    }

    protected function getNullDomElement(){
        $nullNode = new \DOMDocument("1.0");
        $nullNode->createElement("::null::", null);
        return $nullNode;

    }

}