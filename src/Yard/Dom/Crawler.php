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

    /**
     * Contains the xpath passed to query method.
     * It is useful to perform query like andQuery and orQuery
     * @var Component\DomNodeList $domNodeList
     */
    protected $xpath            = null;


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
        if (self::TYPE_XML === $this->type) {
            $domDocument = $this->getDomDocumentFromXmlContent($content, $this->encoding);
        } else {
            $domDocument = $this->getDomDocumentFromHtmlContent($content, $this->encoding);
        }
        $this->domXpath = new \DOMXPath($domDocument);
        return $this;
    }

    public function query($xpath) {
        $this->xpath = $xpath;
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

    /**
     * Select all the xpath passed in the first query and the following one or more andXpath.
     *
     * @param string $xpath
     * @return $this
     * @throws CrawlerException
     */
    public function andQuery($xpath) {
        if (empty($this->xpath)) {
            throw new CrawlerException("query() method MUST be executed before the andQuery()");
        }
        return $this->query(sprintf("%s | %s", $this->xpath, $xpath));
    }

    /**
     * Exec the query inside this statement only if the previous one has 0 result or empty node.
     * @param $xpath
     */
    public function orQuery($xpath) {
        if ($this->domNodeList->count() > 0) {
            foreach ($this->domNodeList as $node) {
                if (null !== $node->nodeValue && '' !== $node->nodeValue) {
                    return $this;
                }
            }
        }
        return $this->query($xpath);
    }


    public function context($xpath) {
        $this->xpath = null;
        $tmpList = $this->domXpath->query($xpath);
        $this->contextNodesList = new DomNodeList();
        foreach ($tmpList as $node) {
            $this->contextNodesList->attach($node);
        }
        return $this;
    }

    public function each(\Closure $closure) {
        $this->xpath = null;
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

    public function cssQuery($css) {
        if (!class_exists('Symfony\\Component\\CssSelector\\CssSelector')) {
            // @codeCoverageIgnoreStart
            throw new CrawlerException("Include Symfony\\Component\\CssSelector\\CssSelector to use this method");
            // @codeCoverageIgnoreEnd
        }
        $xpath = \Symfony\Component\CssSelector\CssSelector::toXPath($css);
        return $this->query($xpath);
    }

    public function cssContext($css) {
        if (!class_exists('Symfony\\Component\\CssSelector\\CssSelector')) {
            // @codeCoverageIgnoreStart
            throw new CrawlerException("Include Symfony\\Component\\CssSelector\\CssSelector to use this method");
            // @codeCoverageIgnoreEnd
        }
        $xpath = \Symfony\Component\CssSelector\CssSelector::toXPath($css);
        return $this->context($xpath);
    }

    public function toString() {
        $this->xpath = null;
        $sRet = null;
        foreach ($this->domNodeList as $node) {
            $sRet = $node->nodeValue;
            break;
        }
        return $sRet;
    }
    public function toArray() {
        $this->xpath = null;
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
     * Transform the (x)html content(string) in a DomDocument.
     * @param string $content - (x)html
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

    /**
     * Transform the xml content(string) in a DomDocument.
     * @param string $content - (xml)
     * @param string $encoding
     * @return \DOMDocument
     */
    protected function getDomDocumentFromXmlContent($content, $encoding) {

        libxml_use_internal_errors(true);
        libxml_disable_entity_loader(true);

        $dom = new \DOMDocument('1.0', $encoding);
        $content = trim($content);
        if ('' !== $content) {
            $content = $this->removeDefaultNamespace($content);
            @$dom->loadXML($content, LIBXML_COMPACT|LIBXML_HTML_NODEFDTD|LIBXML_NONET|LIBXML_NSCLEAN);
        }
        libxml_use_internal_errors(false);
        libxml_disable_entity_loader(false);
        return $dom;
    }

    function removeDefaultNamespace($content) {
        $content =  preg_replace("~\s(xmlns=[\"'].*[\"'])~", "", $content);
        return $content;
    }

    protected function getNullDomElement(){
        $nullNode = new \DOMDocument("1.0");
        $nullNode->createElement("::null::", null);
        return $nullNode;
    }

}