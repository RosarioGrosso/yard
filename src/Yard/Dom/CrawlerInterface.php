<?php
/**
 * Created by Rosario Grosso
 * Date: 03/08/2014
 * Time: 16:01
 * Copyright Rosario Grosso
 */

namespace Yard\Dom;


interface CrawlerInterface {

    public function query($xpathQuery);
    public function context($xpathQuery);
    public function toString();
    public function toArray();
    public function each(\Closure $closure);
    public function trim($xpathQuery);

}