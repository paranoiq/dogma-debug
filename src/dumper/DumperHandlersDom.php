<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use Dogma\Dom\Element;
use Dogma\Dom\NodeList;
use DOMAttr;
use DOMCdataSection;
use DOMComment;
use DOMDocument;
use DOMDocumentFragment;
use DOMDocumentType;
use DOMElement;
use DOMEntity;
use DOMNodeList;
use DOMText;
use function count;
use function get_class;
use function implode;
use function strrpos;
use function substr;
use function trim;

trait DumperHandlersDom
{

    public static function dumpDomDocument(DOMDocument $document, int $depth = 0): string
    {
        return self::name(get_class($document)) . self::bracket('(')
            . self::dumpValue($document->documentElement, $depth + 1)
            . self::bracket(')');
    }

    public static function dumpDomDocumentType(DOMDocumentType $type, int $depth = 0): string
    {
        $value = self::value2($type->ownerDocument->saveHTML($type));

        return $depth === 0
            ? $value
            : self::name(get_class($type)) . self::bracket('(') . $value . self::bracket(')');
    }

    public static function dumpDomEntity(DOMEntity $entity, int $depth = 0): string
    {
        $value = self::value2($entity->ownerDocument->saveHTML($entity));

        return $depth === 0
            ? $value
            : self::name(get_class($entity)) . self::bracket('(') . $value . self::bracket(')');
    }

    public static function dumpDomDocumentFragment(DOMDocumentFragment $fragment, int $depth = 0): string
    {
        $coma = self::symbol(',');

        $nodes = [];
        foreach ($fragment->childNodes as $node) {
            $node = self::indent($depth + 1) . self::dumpValue($node, $depth + 1);
            $pos = strrpos($node, self::infoPrefix());
            if ($pos !== false && Str::contains(substr($node, $pos), "\n")) {
                $node = substr($node, 0, $pos) . $coma . substr($node, $pos);
            } else {
                $node .= $coma;
            }
            $nodes[] = $node;
        }

        $info = self::$showInfo ? ' ' . self::info('// #' . self::objectHash($fragment) . ', ' . count($fragment) . ' items') : '';

        return self::name(get_class($fragment)) . self::bracket('(')  . "\n"
            . implode("\n", $nodes) . "\n"
            . self::indent($depth) . self::bracket(')') . $info;
    }

    /**
     * @param NodeList|DOMNodeList $nodeList
     * @param int $depth
     * @return string
     */
    public static function dumpDomNodeList($nodeList, int $depth = 0): string
    {
        $coma = self::symbol(',');

        $nodes = [];
        foreach ($nodeList as $node) {
            $node = self::indent($depth + 1) . self::dumpValue($node, $depth + 1);
            $pos = strrpos($node, self::infoPrefix());
            if ($pos !== false && Str::contains(substr($node, $pos), "\n")) {
                $node = substr($node, 0, $pos) . $coma . substr($node, $pos);
            } else {
                $node .= $coma;
            }
            $nodes[] = $node;
        }

        $info = self::$showInfo ? ' ' . self::info('// #' . self::objectHash($nodeList) . ', ' . count($nodeList) . ' items') : '';

        return self::name(get_class($nodeList)) . self::bracket('[')  . "\n"
            . implode("\n", $nodes) . "\n"
            . self::indent($depth) . self::bracket(']') . $info;
    }

    /**
     * @param Element|DOMElement $node
     */
    public static function dumpDomElement($node, int $depth = 0): string
    {
        $coma = self::symbol(',');

        $showInfo = self::$showInfo;
        self::$showInfo = false;
        $attributes = [];
        foreach ($node->attributes ?? [] as $attribute) {
            $attributes[] = self::value($attribute->name) . self::value2('=') . self::dumpValue($attribute->value);
        }
        $attributes = implode(' ', $attributes);
        if ($attributes !== '') {
            $attributes = ' ' . $attributes;
        }
        self::$showInfo = $showInfo;

        $childNodes = [];
        foreach ($node->childNodes as $childNode) {
            $childNode = self::indent($depth + 1) . self::dumpValue($childNode, $depth + 1);
            $pos = strrpos($childNode, self::infoPrefix());
            if ($pos !== false && Str::contains(substr($childNode, $pos), "\n")) {
                $childNode = substr($childNode, 0, $pos) . $coma . substr($childNode, $pos);
            } else {
                $childNode .= $coma;
            }
            $childNodes[] = $childNode;
        }

        $hasChildNodes = $node->childNodes->length > 0;
        if ($hasChildNodes) {
            $head = "\n";
            $foot = "\n" . self::indent($depth);
        } else {
            $head = '';
            $foot = '';
        }

        return self::name(get_class($node)) . self::bracket('(')
            . self::value2('<') . self::value($node->nodeName) . $attributes . self::value2('>') . $head
            . implode("\n", $childNodes)
            . $foot . self::value2('<') . self::value($node->nodeName) . self::value2('>')
            . self::bracket(')');
    }

    public static function dumpDomCdataSection(DOMCdataSection $section, int $depth = 0): string
    {
        $value = self::value2('<![CDATA[') . self::value($section->data) . self::value2(']]>');

        return $depth !== 0
            ? $value
            : self::name(get_class($section)) . self::bracket('(') . $value . self::bracket(')')
                . self::info(' // #' . self::objectHash($section));
    }

    public static function dumpDomComment(DOMComment $comment, int $depth = 0): string
    {
        $value = self::info('<!-- ' . trim($comment->data) . " -->");

        return $depth !== 0
            ? $value
            : self::name(get_class($comment)) . self::bracket('(') . $value . self::bracket(')')
                . self::info(' // #' . self::objectHash($comment));
    }

    public static function dumpDomText(DOMText $text, int $depth = 0): string
    {
        $value = self::string($text->wholeText);

        return $depth !== 0
            ? $value
            : self::name(get_class($text)) . self::bracket('(') . $value . self::bracket(')')
                . self::info(' // #' . self::objectHash($text));
    }

    public static function dumpDomAttr(DOMAttr $attribute, int $depth = 0): string
    {
        $value = self::value($attribute->name) . self::value2('=') . self::dumpValue($attribute->value);

        return $depth !== 0
            ? $value
            : self::name(get_class($attribute)) . self::bracket('(') . $value . self::bracket(')')
                . self::info(' // #' . self::objectHash($attribute));
    }

}
