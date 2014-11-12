<?php
/**
 * Created by Rosario Grosso
 * Date: 03/08/2014
 * Time: 14:54
 * Copyright Rosario Grosso
 */
require_once __DIR__ . "/../../../vendor/autoload.php";

class CrawlerTest extends PHPUnit_Framework_TestCase {

    protected $html_vk         = null;
    protected $html_weibo      = null;

    public function __construct() {
        $this->html_vk          = file_get_contents(__DIR__ . "/html_example_vk.html");
        $this->html_weibo       = file_get_contents(__DIR__ . "/html_example_weibo.html");
        $this->html_no_enc      = file_get_contents(__DIR__ . "/html_no_encoding.html");
        $this->xml_ns_root_dec  = file_get_contents(__DIR__ . "/xml_namespace_root_declaration.xml");
    }

    public function testConstructor() {
        $object = new \Yard\Dom\Crawler($this->html_weibo, "UTF-8", \Yard\Dom\Crawler::TYPE_HTML);
        $this->assertEquals("html", $this->getPrivateProperty("\Yard\Dom\Crawler", "type")->getValue($object));
        $this->assertEquals("utf-8", $this->getPrivateProperty("\Yard\Dom\Crawler", "encoding")->getValue($object));
//        $this->assertEquals("html", PHPUnit_Framework_Assert::readAttribute($object, 'type'));
    }

    public function testClassConstants(){
        $this->assertEquals("html", \Yard\Dom\Crawler::TYPE_HTML);
        $this->assertEquals("xml", \Yard\Dom\Crawler::TYPE_XML);
    }

    public function testXpathArray() {
        $object = new \Yard\Dom\Crawler($this->html_no_enc);
        $this->assertEquals(array("Test Title"), $object->query("//title")->toArray());
        $this->assertEquals(array(), $object->query("//invalidPath")->toArray());
        $this->assertEquals(array(), $object->query("//empty-string")->toArray());
        $this->assertEquals(array(), $object->query("//empty-string2")->toArray());
        // test no default trim on toArray()
        $this->assertEquals(
            array(
                "PHP, Java, J2EE, SOLR, Tomcat",
                "Java, J2EE, SOLR, Tomcat, JBoss ",
                "Java, J2EE, SOLR, Tomcat, JBoss, "
            ),
            $object->query("//p[@class='skill']")->toArray()
        );
    }


    public function testXpathString() {
        $object = new \Yard\Dom\Crawler($this->html_no_enc);
        $this->assertEquals("Test Title", $object->query("//title")->toString());
        $this->assertNull($object->query("//invalidPath")->toString());
        $this->assertEquals("", $object->query("//empty-string")->toString());
        $this->assertEquals("", $object->query("//empty-string2")->toString());
        $this->assertEquals('PHP, Java, J2EE, SOLR, Tomcat', $object->query("//p[@class='skill']")->toString());
        // test no default trim
        $this->assertEquals('Java, J2EE, SOLR, Tomcat, JBoss, ', $object->query("//div[@id='right-column']/div[3]/p[3]")->toString());
    }

    public function testTrim() {
        $object = new \Yard\Dom\Crawler($this->html_no_enc);
        // test trim without params
        $this->assertEquals(
            array(
                "PHP, Java, J2EE, SOLR, Tomcat",
                "Java, J2EE, SOLR, Tomcat, JBoss",
                "Java, J2EE, SOLR, Tomcat, JBoss,"
            ),
            $object->query("//p[@class='skill']")->trim()->toArray());
        // test trim passing params
        $this->assertEquals(
            array(
                "PHP, Java, J2EE, SOLR, Tomcat",
                "Java, J2EE, SOLR, Tomcat, JBoss",
                "Java, J2EE, SOLR, Tomcat, JBoss"
            ),
            $object->query("//p[@class='skill']")->trim(" ,")->toArray());
    }

    public function testReplace() {
        $object = new \Yard\Dom\Crawler($this->html_no_enc);
        $this->assertEquals(
            array(
                "PHP, JAVA@, J2EE, SOLR, Tomcat",
                "JAVA@, J2EE, SOLR, Tomcat, JBoss ",
                "JAVA@, J2EE, SOLR, Tomcat, JBoss, "
            ),
            $object->query("//p[@class='skill']")->replace("~Java~", "JAVA@")->toArray());
    }

    public function testFilter() {
        $object = new \Yard\Dom\Crawler($this->html_no_enc);
        $this->assertEquals(
            array(
//                "PHP, Java, J2EE, SOLR, Tomcat",
                "Java, J2EE, SOLR, Tomcat, JBoss ",
                "Java, J2EE, SOLR, Tomcat, JBoss, "
            ),

            $object->query("//p[@class='skill']")->filter(function(DOMNode $node){
                if (false !== mb_stripos($node->nodeValue, "php")) {
                    return false;
                } else {
                    return true;
                }
            })->toArray());
    }

    public function testContext() {
        $object = new \Yard\Dom\Crawler($this->html_no_enc);
        $this->assertEquals(
            array("Lead Software Engineer", "Senior Software Engineer","Software Engineer"),
            $object
                ->context("//div[@class='experience']")
                ->query("//p[@class='title']")
                ->toArray()
        );

        $this->assertEquals(
            array("Company 1 ltd", null, "Company 3 ltd"),
            $object
                ->context("//div[@class='experience']")
                ->query("//p[@class='org']")
                ->toArray()
        );
    }

    public function testGuessType() {
        $object = new \Yard\Dom\Crawler();
        // xml type
        $method = $this->getPrivateMethod( '\Yard\Dom\Crawler', 'guessType' );
        $result = $method->invokeArgs( $object, array($this->xml_ns_root_dec) );
        $this->assertEquals( "xml", $result );
        //html type
        $method = $this->getPrivateMethod( '\Yard\Dom\Crawler', 'guessType' );
        $result = $method->invokeArgs( $object, array($this->html_no_enc) );
        $this->assertEquals( "html", $result );
    }

    public function testGuessEncoding() {
        $object = new \Yard\Dom\Crawler();
        $method = $this->getPrivateMethod( '\Yard\Dom\Crawler', 'guessEncoding' );

        // xml
        $result = $method->invokeArgs( $object, array($this->xml_ns_root_dec) );
        $this->assertEquals("utf-8", $result );
        // html weibo utf-8 test
        $result = $method->invokeArgs( $object, array($this->html_weibo) );
        $this->assertEquals( "utf-8", $result );
        // html vk.com windows-1251 test
        $result = $method->invokeArgs( $object, array($this->html_vk) );
        $this->assertEquals( "windows-1251", $result );
        // html empty document - null encoding
        $result = $method->invokeArgs( $object, array("") );
        $this->assertNull( $result );
    }

    public function testGetNullDomElement() {
        $object = new \Yard\Dom\Crawler();
        $method = $this->getPrivateMethod('\Yard\Dom\Crawler', 'getNullDomElement');
        $result = $method->invoke($object);
        $this->assertNull($result->nodeValue);
    }

    public function testStringQuery() {
        $object = new \Yard\Dom\Crawler($this->html_vk);
        $this->assertEquals(
            "Марганец(",
            $object->query("//div[contains(concat(' ', normalize-space(text()), ' '), ' Hometown: ')]/following-sibling::div[1]/a/text()")
                ->toString()
        );
    }

    public function testCssQuery() {
        $object = new \Yard\Dom\Crawler($this->html_vk);
        $this->assertEquals(
            "Forgot your password?",
            $object->cssQuery("#quick_forgot")
                ->toString()
        );
    }

    public function testCssContext() {
        $object = new \Yard\Dom\Crawler($this->html_no_enc);
        $this->assertEquals(
            array('Company 1 ltd', null, 'Company 3 ltd'),
            $object->cssContext(".experience")
                ->cssQuery(".org")
                ->toArray()
        );
    }

    public function testEmptyStringQuery() {
        $object = new \Yard\Dom\Crawler($this->html_vk);
        $this->assertNull( $object->query("//node-that-does-not-exist")->toString());
    }

    public function testEmptyArrayQuery() {
        $object = new \Yard\Dom\Crawler($this->html_vk);
        $this->assertEquals(array(), $object->query("//node-that-does-not-exist")->toArray());
    }

    public function testXmlType() {
        $object = new \Yard\Dom\Crawler($this->xml_ns_root_dec);
        $this->assertEquals("xml", $this->getPrivateProperty("\Yard\Dom\Crawler", "type")->getValue($object));
    }

    public function testXmlEncoding() {
        $object = new \Yard\Dom\Crawler($this->xml_ns_root_dec);
        $this->assertEquals("utf-8", $this->getPrivateProperty("\Yard\Dom\Crawler", "encoding")->getValue($object));
    }

    public function testXmlNamespacedQuery() {
        $object = new \Yard\Dom\Crawler($this->xml_ns_root_dec);
        $this->assertEquals("80", $object->query("//f:width")->toString());
        $this->assertEquals(array("Apples","Bananas"), $object->query("//h:td")->toArray());
        $this->assertEquals("test no default namespace", $object->query("//title")->toString());
    }

    public function testOrQuery() {
        $object = new \Yard\Dom\Crawler($this->xml_ns_root_dec);
        $res = $object
            ->query("//nonamespace//fakenode")
            ->orQuery("//nonamespace//otherfakenode")
            ->orQuery("//h:td")
            ->orQuery("//nonamespace")
            ->toArray();
        $this->assertEquals(
            array("Apples", "Bananas"),
            $res
        );

        /** INSERT TEST FOR STRING */
        $res = $object
            ->query("//nonamespace//fakenode")
            ->orQuery("//h:td")
            ->orQuery("//h:td[2]")
            ->toString();
        $this->assertEquals(
            "Apples",
            $res
        );

        /** TEST FOR ALL NODES EMPTY */
        $res = $object
            ->query("//nonamespace//fakenode")
            ->orQuery("//nonamespace//otherfakenode")
            ->toString();
        $this->assertEquals(
            null,
            $res
        );

        $object = new \Yard\Dom\Crawler($this->html_no_enc);
        $res = $object
            ->query("//*[@class='followers']")
            ->orQuery("//nonamespace//otherfakenode")
            ->orQuery("//*[@class='following']")
            ->toString();
        $this->assertEquals(
            200,
            $res
        );

        $res = $object
            ->query("//*[@class='empty-string']")
            ->orQuery("//nonamespace//otherfakenode")
            ->orQuery("//*[@class='followers']")
            ->toString();
        $this->assertEquals(
            200,
            $res
        );
    }

    public function testAndQuery() {
        $object = new \Yard\Dom\Crawler($this->xml_ns_root_dec);
        $res = $object
            ->query("//nonamespace//id")
            ->andQuery("//nonamespace//title")
            ->andQuery("//h:td")
            ->andQuery("//f:length")
            ->toArray();
        $this->assertEquals(
            array("Apples", "Bananas", 120, 4, "test no default namespace"),
            $res
        );

        /** @TODO fix order for AndQuery */
//        $res = $object
//            ->query("//nonamespace//id")
//            ->andQuery("//nonamespace//title")
//            ->andQuery("//h:td")
//            ->andQuery("//f:length")
//            ->toString();
//        $this->assertEquals(
//            "4",
//            $res
//        );

    }

    /**
     * @expectedException \Yard\Dom\CrawlerException
     */
    public function testAndQueryException() {
        $object = new \Yard\Dom\Crawler($this->xml_ns_root_dec);
        $object
            ->andQuery("//nonamespace//title")
            ->andQuery("//h:td")
            ->andQuery("//f:length")
            ->toArray();
    }

    public function testCleanXpathProperty() {
        $object = new \Yard\Dom\Crawler($this->xml_ns_root_dec);
        // before query it must be null;
        $this->assertNull(
            $this->getPrivateProperty("\Yard\Dom\Crawler", "xpath")->getValue($object)
        );
        $res = $object->query("//nonamespace//id");
        // it must contains the first xpath
        $this->assertEquals(
            "//nonamespace//id",
            $this->getPrivateProperty("\Yard\Dom\Crawler", "xpath")->getValue($object)
        );
        $res = $res->andQuery("//a");
        // it must contains the 2 xpaths (query() and andQuery() methods)
        $this->assertEquals(
            "//nonamespace//id | //a",
            $this->getPrivateProperty("\Yard\Dom\Crawler", "xpath")->getValue($object)
        );
        $res->toArray();
        // it must be null again
        $this->assertNull(
            $this->getPrivateProperty("\Yard\Dom\Crawler", "xpath")->getValue($object)
        );
    }
    public function testLibxmlDisableEntityLoader() {
        // test HTML
        libxml_disable_entity_loader(true) ;
        $object = new \Yard\Dom\Crawler($this->html_weibo, "UTF-8", \Yard\Dom\Crawler::TYPE_HTML);
        // libxml_disable_entity_loader must be true 
        $this->assertEquals( true , libxml_disable_entity_loader() );
        libxml_disable_entity_loader(false) ;
        $object = new \Yard\Dom\Crawler($this->html_weibo, "UTF-8", \Yard\Dom\Crawler::TYPE_HTML);
        // libxml_disable_entity_loader must be false 
        $this->assertEquals( false , libxml_disable_entity_loader() );
        // Test XML 
        libxml_disable_entity_loader(true) ;
        $object = new \Yard\Dom\Crawler($this->xml_ns_root_dec, "UTF-8", \Yard\Dom\Crawler::TYPE_XML);
        // libxml_disable_entity_loader must be true 
        $this->assertEquals( true , libxml_disable_entity_loader() );
        libxml_disable_entity_loader(false) ;
        $object = new \Yard\Dom\Crawler($this->xml_ns_root_dec, "UTF-8", \Yard\Dom\Crawler::TYPE_XML);
        // libxml_disable_entity_loader must be false 
        $this->assertEquals( false , libxml_disable_entity_loader() );
        

    }
    public function testLibxmlUseInternalErrorsPersistence() {
        // test HTML
        libxml_use_internal_errors(true) ;
        $object = new \Yard\Dom\Crawler($this->html_weibo, "UTF-8", \Yard\Dom\Crawler::TYPE_HTML);
        // libxml_use_internal_errors must be true 
        $this->assertEquals( true , libxml_use_internal_errors() );
        libxml_use_internal_errors(false) ;
        $object = new \Yard\Dom\Crawler($this->html_weibo, "UTF-8", \Yard\Dom\Crawler::TYPE_HTML);
        // libxml_use_internal_errors must be false 
        $this->assertEquals( false , libxml_use_internal_errors() );
        // Test XML 
        libxml_use_internal_errors(true) ;
        $object = new \Yard\Dom\Crawler($this->xml_ns_root_dec, "UTF-8", \Yard\Dom\Crawler::TYPE_XML);
        // libxml_use_internal_errors must be true 
        $this->assertEquals( true , libxml_use_internal_errors() );
        libxml_use_internal_errors(false) ;
        $object = new \Yard\Dom\Crawler($this->xml_ns_root_dec, "UTF-8", \Yard\Dom\Crawler::TYPE_XML);
        // libxml_use_internal_errors must be false 
        $this->assertEquals( false , libxml_use_internal_errors() );

    }


    /**
     * getPrivateMethod method
     * @param     string $className
     * @param     string $methodName
     * @return    ReflectionMethod
     */
    public function getPrivateMethod($className, $methodName) {
        $reflector  = new ReflectionClass($className);
        $method     = $reflector->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * getPrivateProperty method
     * @param     string $className
     * @param     string $propertyName
     * @return    ReflectionProperty
     */
    public function getPrivateProperty($className, $propertyName) {
        $reflector  = new ReflectionClass($className);
        $property   = $reflector->getProperty($propertyName);
        $property->setAccessible(true);
        return $property;
    }



} 