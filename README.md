### Welcome to the *Yard Framework 0.0.10* Unstable Release!


[![Build Status](https://travis-ci.org/RosarioGrosso/yard.svg?branch=master)](https://travis-ci.org/RosarioGrosso/yard)
[![Coverage Status](https://img.shields.io/coveralls/RosarioGrosso/yard.svg)](https://coveralls.io/r/RosarioGrosso/yard)
## RELEASE INFORMATION

*Yard Framework 0.0.10dev* [unstable]

Please do not use yet: initial development stage and the public API should not be considered stable.


 Available Classes:

   * Yard\Dom\Crawler

```php
<?php
require_once __DIR__ . "/../vendor/autoload.php";
$xml = file_get_contents(__DIR__ . "/../Tests/Yard/Dom/xml_namespace_root_declaration.xml");
$domCrawler = new Yard\Dom\Crawler($xml);
```

