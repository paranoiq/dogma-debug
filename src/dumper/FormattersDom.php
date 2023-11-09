<?php
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
use RuntimeException;
use function count;
use function error_get_last;
use function get_class;
use function implode;
use function str_contains;
use function strrpos;
use function substr;
use function trim;

class FormattersDom
{

    public static function register(): void
    {
        Dumper::$objectFormatters[DOMDocument::class] = [self::class, 'dumpDomDocument'];
        Dumper::$objectFormatters[DOMDocumentFragment::class] = [self::class, 'dumpDomDocumentFragment'];
        Dumper::$objectFormatters[DOMDocumentType::class] = [self::class, 'dumpDomDocumentType'];
        Dumper::$objectFormatters[DOMEntity::class] = [self::class, 'dumpDomEntity'];
        Dumper::$objectFormatters[DOMElement::class] = [self::class, 'dumpDomElement'];
        Dumper::$objectFormatters[DOMNodeList::class] = [self::class, 'dumpDomNodeList'];
        Dumper::$objectFormatters[DOMCdataSection::class] = [self::class, 'dumpDomCdataSection'];
        Dumper::$objectFormatters[DOMComment::class] = [self::class, 'dumpDomComment'];
        Dumper::$objectFormatters[DOMText::class] = [self::class, 'dumpDomText'];
        Dumper::$objectFormatters[DOMAttr::class] = [self::class, 'dumpDomAttr'];

        Dumper::$objectFormatters[Element::class] = [self::class, 'dumpDomElement'];
        Dumper::$objectFormatters[NodeList::class] = [self::class, 'dumpDomNodeList'];
    }

    public static function dumpDomDocument(DOMDocument $document, int $depth = 0): string
    {
        return Dumper::class(get_class($document)) . Dumper::bracket('(')
            . Dumper::dumpValue($document->documentElement, $depth + 1)
            . Dumper::bracket(')');
    }

    public static function dumpDomDocumentType(DOMDocumentType $type, int $depth = 0): string
    {
        $html = $type->ownerDocument->saveHTML($type);
        if ($html === false) {
            throw new RuntimeException(error_get_last()['message']);
        }

        $value = Dumper::value2($html);

        return $depth === 0
            ? $value
            : Dumper::class(get_class($type)) . Dumper::bracket('(') . $value . Dumper::bracket(')');
    }

    public static function dumpDomEntity(DOMEntity $entity, int $depth = 0): string
    {
        $html = $entity->ownerDocument->saveHTML($entity);
        if ($html === false) {
            throw new RuntimeException(error_get_last()['message']);
        }

        $value = Dumper::value2($html);

        return $depth === 0
            ? $value
            : Dumper::class(get_class($entity)) . Dumper::bracket('(') . $value . Dumper::bracket(')');
    }

    public static function dumpDomDocumentFragment(DOMDocumentFragment $fragment, int $depth = 0): string
    {
        $coma = Dumper::symbol(',');

        $nodes = [];
        foreach ($fragment->childNodes as $node) {
            $node = Dumper::indent($depth + 1) . Dumper::dumpValue($node, $depth + 1);
            $pos = strrpos($node, Dumper::infoPrefix());
            if ($pos !== false && str_contains(substr($node, $pos), "\n")) {
                $node = substr($node, 0, $pos) . $coma . substr($node, $pos);
            } else {
                $node .= $coma;
            }
            $nodes[] = $node;
        }

        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($fragment) . ', ' . count($fragment->childNodes) . ' items') : '';

        return Dumper::class(get_class($fragment)) . Dumper::bracket('(') . "\n"
            . implode("\n", $nodes) . "\n"
            . Dumper::indent($depth) . Dumper::bracket(')') . $info;
    }

    /**
     * @param NodeList|DOMNodeList $nodeList
     */
    public static function dumpDomNodeList($nodeList, int $depth = 0): string
    {
        $coma = Dumper::symbol(',');

        $nodes = [];
        foreach ($nodeList as $node) {
            $node = Dumper::indent($depth + 1) . Dumper::dumpValue($node, $depth + 1);
            $pos = strrpos($node, Dumper::infoPrefix());
            if ($pos !== false && str_contains(substr($node, $pos), "\n")) {
                $node = substr($node, 0, $pos) . $coma . substr($node, $pos);
            } else {
                $node .= $coma;
            }
            $nodes[] = $node;
        }

        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($nodeList) . ', ' . count($nodeList) . ' items') : '';

        return Dumper::class(get_class($nodeList)) . Dumper::bracket('[') . "\n"
            . implode("\n", $nodes) . "\n"
            . Dumper::indent($depth) . Dumper::bracket(']') . $info;
    }

    /**
     * @param Element|DOMElement $node
     */
    public static function dumpDomElement($node, int $depth = 0): string
    {
        $coma = Dumper::symbol(',');

        $showInfo = Dumper::$showInfo;
        Dumper::$showInfo = false;
        $attributes = [];
        foreach ($node->attributes ?? [] as $attribute) {
            $attributes[] = Dumper::value($attribute->name) . Dumper::value2('=') . Dumper::dumpValue($attribute->value, $depth + 1);
        }
        $attributes = implode(' ', $attributes);
        if ($attributes !== '') {
            $attributes = ' ' . $attributes;
        }
        Dumper::$showInfo = $showInfo;

        $childNodes = [];
        foreach ($node->childNodes as $childNode) {
            $childNode = Dumper::indent($depth + 1) . Dumper::dumpValue($childNode, $depth + 1);
            $pos = strrpos($childNode, Dumper::infoPrefix());
            if ($pos !== false && str_contains(substr($childNode, $pos), "\n")) {
                $childNode = substr($childNode, 0, $pos) . $coma . substr($childNode, $pos);
            } else {
                $childNode .= $coma;
            }
            $childNodes[] = $childNode;
        }

        $hasChildNodes = $node->childNodes->length > 0;
        if ($hasChildNodes) {
            $head = "\n";
            $foot = "\n" . Dumper::indent($depth);
        } else {
            $head = '';
            $foot = '';
        }

        return Dumper::class(get_class($node)) . Dumper::bracket('(')
            . Dumper::value2('<') . Dumper::value($node->nodeName) . $attributes . Dumper::value2('>') . $head
            . implode("\n", $childNodes)
            . $foot . Dumper::value2('<') . Dumper::value($node->nodeName) . Dumper::value2('>')
            . Dumper::bracket(')');
    }

    public static function dumpDomCdataSection(DOMCdataSection $section, int $depth = 0): string
    {
        $value = Dumper::value2('<![CDATA[') . Dumper::value($section->data) . Dumper::value2(']]>');

        return $depth !== 0
            ? $value
            : Dumper::class(get_class($section)) . Dumper::bracket('(') . $value . Dumper::bracket(')')
                . Dumper::info(' // #' . Dumper::objectHash($section));
    }

    public static function dumpDomComment(DOMComment $comment, int $depth = 0): string
    {
        $value = Dumper::info('<!-- ' . trim($comment->data) . " -->");

        return $depth !== 0
            ? $value
            : Dumper::class(get_class($comment)) . Dumper::bracket('(') . $value . Dumper::bracket(')')
                . Dumper::info(' // #' . Dumper::objectHash($comment));
    }

    public static function dumpDomText(DOMText $text, int $depth = 0): string
    {
        $value = Dumper::string($text->wholeText, $depth);

        return $depth !== 0
            ? $value
            : Dumper::class(get_class($text)) . Dumper::bracket('(') . $value . Dumper::bracket(')')
                . Dumper::info(' // #' . Dumper::objectHash($text));
    }

    public static function dumpDomAttr(DOMAttr $attribute, int $depth = 0): string
    {
        $value = Dumper::value($attribute->name) . Dumper::value2('=') . Dumper::dumpValue($attribute->value, $depth + 1);

        return $depth !== 0
            ? $value
            : Dumper::class(get_class($attribute)) . Dumper::bracket('(') . $value . Dumper::bracket(')')
                . Dumper::info(' // #' . Dumper::objectHash($attribute));
    }

}
