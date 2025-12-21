<?php

/**
 * Minimal Svg stub - provides classes Dompdf expects for SVG handling
 */

namespace Svg;

class Document
{
    protected $width = 0;
    protected $height = 0;
    protected $surfaces = [];

    public function __construct() {}

    public function loadFile($filename) {}

    public function parseXml($data) {}

    public function getWidth()
    {
        return $this->width;
    }

    public function getHeight()
    {
        return $this->height;
    }

    public function getDimensions()
    {
        return ['width' => $this->width, 'height' => $this->height];
    }

    public function render($surface) {}

    public function getSurface()
    {
        return null;
    }
}

namespace Svg\Surface;

class SurfacePDFLib
{
    public function __construct($document, $canvas = null) {}
}

class SurfaceCpdf
{
    protected $canvas;

    public function __construct($document, $canvas = null)
    {
        $this->canvas = $canvas;
    }

    public function setCanvas($canvas)
    {
        $this->canvas = $canvas;
    }
}
