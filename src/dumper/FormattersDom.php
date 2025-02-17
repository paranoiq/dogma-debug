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

    public static function dumpDomDocument(DOMDocument $document, DumperConfig $config, int $depth = 0): string
    {
        return Dumper::class(get_class($document), $config) . Dumper::bracket('(')
            . Dumper::dumpValue($document->documentElement, $config, $depth + 1)
            . Dumper::bracket(')');
    }

    public static function dumpDomDocumentType(DOMDocumentType $type, DumperConfig $config, int $depth = 0): string
    {
        $html = $type->ownerDocument->saveHTML($type);
        if ($html === false) {
            throw new RuntimeException(error_get_last()['message']);
        }

        $value = Dumper::value2($html);

        return $depth === 0
            ? $value
            : Dumper::class(get_class($type), $config) . Dumper::bracket('(') . $value . Dumper::bracket(')');
    }

    public static function dumpDomEntity(DOMEntity $entity, DumperConfig $config, int $depth = 0): string
    {
        $html = $entity->ownerDocument->saveHTML($entity);
        if ($html === false) {
            throw new RuntimeException(error_get_last()['message']);
        }

        $value = Dumper::value2($html);

        return $depth === 0
            ? $value
            : Dumper::class(get_class($entity), $config) . Dumper::bracket('(') . $value . Dumper::bracket(')');
    }

    public static function dumpDomDocumentFragment(DOMDocumentFragment $fragment, DumperConfig $config, int $depth = 0): string
    {
        $coma = Dumper::symbol(',');

        $nodes = [];
        foreach ($fragment->childNodes as $node) {
            $node = Dumper::indent($depth + 1, $config) . Dumper::dumpValue($node, $config, $depth + 1);
            $pos = strrpos($node, Dumper::infoPrefix());
            if ($pos !== false && str_contains(substr($node, $pos), "\n")) {
                $node = substr($node, 0, $pos) . $coma . substr($node, $pos);
            } else {
                $node .= $coma;
            }
            $nodes[] = $node;
        }

        $info = Dumper::$config->showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($fragment) . ', ' . count($fragment->childNodes) . ' items') : '';

        return Dumper::class(get_class($fragment), $config) . Dumper::bracket('(') . "\n"
            . implode("\n", $nodes) . "\n"
            . Dumper::indent($depth, $config) . Dumper::bracket(')') . $info;
    }

    /**
     * @param NodeList|DOMNodeList $nodeList
     */
    public static function dumpDomNodeList($nodeList, DumperConfig $config, int $depth = 0): string
    {
        $coma = Dumper::symbol(',');

        $nodes = [];
        foreach ($nodeList as $node) {
            $node = Dumper::indent($depth + 1, $config) . Dumper::dumpValue($node, $config, $depth + 1);
            $pos = strrpos($node, Dumper::infoPrefix());
            if ($pos !== false && str_contains(substr($node, $pos), "\n")) {
                $node = substr($node, 0, $pos) . $coma . substr($node, $pos);
            } else {
                $node .= $coma;
            }
            $nodes[] = $node;
        }

        $info = Dumper::$config->showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($nodeList) . ', ' . count($nodeList) . ' items') : '';

        return Dumper::class(get_class($nodeList), $config) . Dumper::bracket('[') . "\n"
            . implode("\n", $nodes) . "\n"
            . Dumper::indent($depth, $config) . Dumper::bracket(']') . $info;
    }

    /**
     * @param Element|DOMElement $node
     */
    public static function dumpDomElement($node, DumperConfig $config, int $depth = 0): string
    {
        $coma = Dumper::symbol(',');

        $showInfo = Dumper::$config->showInfo;
        Dumper::$config->showInfo = false;
        $attributes = [];
        foreach ($node->attributes ?? [] as $attribute) {
            $attributes[] = Dumper::value($attribute->name) . Dumper::value2('=') . Dumper::dumpValue($attribute->value, $config, $depth + 1);
        }
        $attributes = implode(' ', $attributes);
        if ($attributes !== '') {
            $attributes = ' ' . $attributes;
        }
        Dumper::$config->showInfo = $showInfo;

        $childNodes = [];
        foreach ($node->childNodes as $childNode) {
            $childNode = Dumper::indent($depth + 1, $config) . Dumper::dumpValue($childNode, $config, $depth + 1);
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
            $foot = "\n" . Dumper::indent($depth, $config);
        } else {
            $head = '';
            $foot = '';
        }

        return Dumper::class(get_class($node), $config) . Dumper::bracket('(')
            . Dumper::value2('<') . Dumper::value($node->nodeName) . $attributes . Dumper::value2('>') . $head
            . implode("\n", $childNodes)
            . $foot . Dumper::value2('<') . Dumper::value($node->nodeName) . Dumper::value2('>')
            . Dumper::bracket(')');
    }

    public static function dumpDomCdataSection(DOMCdataSection $section, DumperConfig $config, int $depth = 0): string
    {
        $value = Dumper::value2('<![CDATA[') . Dumper::value($section->data) . Dumper::value2(']]>');

        return $depth !== 0
            ? $value
            : Dumper::class(get_class($section), $config) . Dumper::bracket('(') . $value . Dumper::bracket(')')
                . Dumper::info(' // #' . Dumper::objectHash($section));
    }

    public static function dumpDomComment(DOMComment $comment, DumperConfig $config, int $depth = 0): string
    {
        $value = Dumper::info('<!-- ' . trim($comment->data) . " -->");

        return $depth !== 0
            ? $value
            : Dumper::class(get_class($comment), $config) . Dumper::bracket('(') . $value . Dumper::bracket(')')
                . Dumper::info(' // #' . Dumper::objectHash($comment));
    }

    public static function dumpDomText(DOMText $text, DumperConfig $config, int $depth = 0): string
    {
        $value = Dumper::string($text->wholeText, $config, $depth);

        return $depth !== 0
            ? $value
            : Dumper::class(get_class($text), $config) . Dumper::bracket('(') . $value . Dumper::bracket(')')
                . Dumper::info(' // #' . Dumper::objectHash($text));
    }

    public static function dumpDomAttr(DOMAttr $attribute, DumperConfig $config, int $depth = 0): string
    {
        $value = Dumper::value($attribute->name) . Dumper::value2('=') . Dumper::dumpValue($attribute->value, $config, $depth + 1);

        return $depth !== 0
            ? $value
            : Dumper::class(get_class($attribute), $config) . Dumper::bracket('(') . $value . Dumper::bracket(')')
                . Dumper::info(' // #' . Dumper::objectHash($attribute));
    }

}
