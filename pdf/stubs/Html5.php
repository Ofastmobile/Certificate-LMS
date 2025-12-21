<?php

/**
 * Minimal Masterminds HTML5 parser stub
 */

namespace Masterminds;

class HTML5
{
    protected $options = [];

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function loadHTML($html, array $options = [])
    {
        // Use PHP's built-in DOMDocument instead
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        // Add proper HTML5 doctype if not present
        if (stripos($html, '<!DOCTYPE') === false) {
            $html = '<!DOCTYPE html>' . $html;
        }

        // Convert to UTF-8 and load
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();

        return $dom;
    }

    public function loadHTMLFile($filename, array $options = [])
    {
        $html = file_get_contents($filename);
        return $this->loadHTML($html, $options);
    }

    public function loadHTMLFragment($string, array $options = [])
    {
        return $this->loadHTML('<html><body>' . $string . '</body></html>', $options);
    }

    public function parse($input, array $options = [])
    {
        return $this->loadHTML($input, $options);
    }

    public function parseFragment($input, array $options = [])
    {
        return $this->loadHTMLFragment($input, $options);
    }

    public function save($dom)
    {
        if ($dom instanceof \DOMDocument) {
            return $dom->saveHTML();
        }
        return '';
    }

    public function saveHTML($dom)
    {
        return $this->save($dom);
    }
}
