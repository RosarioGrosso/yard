<?php
/**
 * Created by Rosario Grosso
 * Date: 03/08/2014
 * Time: 16:01
 * Copyright Rosario Grosso
 */

namespace Yard\Dom;

interface CrawlerInterface {
    public function query($xpath);
    public function context($xpath);
    public function cssQuery($css);
    public function cssContext($css);
    public function andQuery($xpath);
    public function orQuery($xpath);
    public function toString();
    public function toArray();
    public function replace($match, $replace);
    public function filter(\Closure $closure);
    public function trim($characterMask);
}