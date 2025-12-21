<?php

/**
 * Minimal FontLib stub - provides classes Dompdf expects without full library
 */

namespace FontLib;

class Font
{
    public static function load($file)
    {
        return new TrueType\File();
    }
}

class BinaryStream
{
    protected $f;
    public function __construct($data = null) {}
    public function load($file)
    {
        return $this;
    }
    public function open($filename, $mode = 'rb')
    {
        return true;
    }
    public function close() {}
    public function read($length)
    {
        return '';
    }
    public function write($data) {}
    public function seek($offset, $whence = SEEK_SET) {}
    public function unpack($format)
    {
        return [];
    }
    public function readUInt8()
    {
        return 0;
    }
    public function readUInt16()
    {
        return 0;
    }
    public function readUInt32()
    {
        return 0;
    }
    public function readInt8()
    {
        return 0;
    }
    public function readInt16()
    {
        return 0;
    }
    public function readInt32()
    {
        return 0;
    }
}

namespace FontLib\Exception;

class FontNotFoundException extends \Exception {}

namespace FontLib\TrueType;

class File
{
    public $data = [];
    public function __construct() {}
    public function parse() {}
    public function close() {}
    public function getData($key, $default = null)
    {
        return $default;
    }
    public function setData($key, $value) {}
    public function reduce() {}
    public function encode($data = [])
    {
        return '';
    }
    public function getFontName()
    {
        return 'Arial';
    }
    public function getFontWeight()
    {
        return 400;
    }
    public function getFontFullName()
    {
        return 'Arial';
    }
    public function getFontSubfamily()
    {
        return 'Regular';
    }
    public function getFontCopyright()
    {
        return '';
    }
    public function getUnicodeCharMap()
    {
        return [];
    }
    public function getAdvanceWidth($glyph)
    {
        return 500;
    }
    public function getUnitsPerEm()
    {
        return 1000;
    }
    public function getAscender()
    {
        return 800;
    }
    public function getDescender()
    {
        return -200;
    }
    public function getItalicAngle()
    {
        return 0;
    }
    public function getCapHeight()
    {
        return 700;
    }
    public function getStemV()
    {
        return 80;
    }
    public function getBBox()
    {
        return [0, -200, 1000, 800];
    }
    public function getGlyphIDs($text)
    {
        return [];
    }
    public function saveAdobeFontMetrics($filename) {}
}
