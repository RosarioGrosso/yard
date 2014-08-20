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

    /**
     * String value for xml document.
     */
    const TYPE_XML              = 'xml';

    /**
     * String value for (x)html document.
     */
    const TYPE_HTML             = 'html';

    /**
     * If a context search will be performed,
     * the query method will use this NodeList as base for the query and not the full html/xml.
     *
     * @var DomNodeList $contextNodesList
     */
    protected $contextNodesList = null;

    /**
     * Define the type of document (xml|html). Look at TYPE_* constants for a list.
     * @var string $type
     */
    protected $type             = null;

    /**
     * Document encoding (ISO-8859-1, UTF-8 and so on)
     * @var string $encoding
     */
    protected $encoding         = null;

    /**
     * If no encoding is passed or discovered, this one will be used.
     * auto let the DomDocument
     * @var string $encoding
     */
    protected $defaultEncoding  = "auto";

    /**
     * Hold the DomXPath Object.
     * @var \DomXPath $domXpath
     */
    protected $domXpath         = null;

    /**
     * Hold the result of a query.
     * @var Component\DomNodeList $domNodeList
     */
    protected $domNodeList      = null;

    /**
     * Contains the xpath passed to query method.
     * It is useful to perform query like andQuery and orQuery
     * @var Component\DomNodeList $domNodeList
     */
    protected $xpath            = null;


    /**
     * @param string $content   - The (X)HTML or XML Content.
     * @param string $encoding  - The encoding of the document, if null it will guessed from the document.
     * @param string $type      - HTML Document or XML Document Crawler::TYPE_XML or Crawler::TYPE_HTML
     */
    public function __construct($content = null, $encoding = null, $type = null){
        if (!is_null($content)) {
            $this->loadContent($content, $encoding, $type);
        }
    }

    /**
     * Load the document and initialize the class to be ready for queries.
     *
     * @param string $content   - The (X)HTML or XML Content.
     * @param string $encoding  - The encoding of the document, if null it will guessed from the document.
     * @param string $type      - HTML Document or XML Document Crawler::TYPE_XML or Crawler::TYPE_HTML
     * @return $this
     */
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

    /**
     * Performs a query to extract nodes from the document.
     * @param string $xpath - XPath query as a string.
     * @return $this
     */
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
     * If you need to extract more than one node in the same query, you can use a mix of "OR" or "|" XPath statements or,
     * you can use this method to retrieve different nodes into the same query results.
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
     * Execute this query only if the previous one has returned Zero Elements or empty nodes.
     *
     * @param $xpath
     * @return $this
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

    /**
     * Use a context for the query. This means that the query will be performed using the context result as Document
     * and not the whole html document.
     *
     * @param string $xpath
     * @return $this
     */
    public function context($xpath) {
        $this->xpath = null;
        $tmpList = $this->domXpath->query($xpath);
        $this->contextNodesList = new DomNodeList();
        foreach ($tmpList as $node) {
            $this->contextNodesList->attach($node);
        }
        return $this;
    }

    /**
     * forEach statement for the NodeList.
     *
     * @param callable $closure
     * @return array
     */
    public function each(\Closure $closure) {
        $this->xpath = null;
        $aData = array();
        foreach ($this->domNodeList as $k => $node) {
            $aData[] = $closure($node, $k);
        }
        return $aData;
    }

    /**
     * wrap of the php trim to be use on a nodeList.
     *
     * @param string $characterMask
     * @return $this
     */
    public function trim($characterMask = null) {
        foreach ($this->domNodeList as $node) {
            $node->nodeValue = is_null($characterMask) ? trim($node->nodeValue) : trim($node->nodeValue, $characterMask);
        }
        return $this;
    }

    /**
     * Same as query method but using css.
     *
     * @param string $css
     * @return $this
     * @throws CrawlerException
     */
    public function cssQuery($css) {
        if (!class_exists('Symfony\\Component\\CssSelector\\CssSelector')) {
            // @codeCoverageIgnoreStart
            throw new CrawlerException("Include Symfony\\Component\\CssSelector\\CssSelector to use this method");
            // @codeCoverageIgnoreEnd
        }
        $xpath = \Symfony\Component\CssSelector\CssSelector::toXPath($css);
        return $this->query($xpath);
    }

    /**
     * Same as context method but use a css instead of a xpath value.
     *
     * @param string $css
     * @return $this
     * @throws CrawlerException
     */
    public function cssContext($css) {
        if (!class_exists('Symfony\\Component\\CssSelector\\CssSelector')) {
            // @codeCoverageIgnoreStart
            throw new CrawlerException("Include Symfony\\Component\\CssSelector\\CssSelector to use this method");
            // @codeCoverageIgnoreEnd
        }
        $xpath = \Symfony\Component\CssSelector\CssSelector::toXPath($css);
        return $this->context($xpath);
    }

    /**
     * Force the result to be represented as string.
     *
     * @return string|null
     */
    public function toString() {
        $this->xpath = null;
        $sRet = null;
        foreach ($this->domNodeList as $node) {
            $sRet = $node->nodeValue;
            break;
        }
        return $sRet;
    }

    /**
     * Force the result to be represented as an array.
     *
     * @return array
     */
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
     * Try to guess the Document encoding from its contents.
     *
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

    /**
     * Remove the default namespace to simplify query on default nodes.
     * @param $content
     * @return mixed
     */
    function removeDefaultNamespace($content) {
        $content =  preg_replace("~\s(xmlns=[\"'].*[\"'])~", "", $content);
        return $content;
    }

    /**
     * return a Null DomDocument, useful to manage query to elements that do not exist inside the target document.
     * @return \DOMDocument
     */
    protected function getNullDomElement(){
        $nullNode = new \DOMDocument("1.0");
        $nullNode->createElement("::null::", null);
        return $nullNode;
    }

}