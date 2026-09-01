<?php

namespace Xlited\Lamx\Support;

use LogicException;

/**
 * Adds attributes to the first (root) element of a rendered component.
 */
class RootElement
{
    /**
     * Merge the given values into the root element's "hx-vals:inherited"
     * attribute, so every htmx request issued from inside the component
     * carries them. A plain "hx-vals" on the root is upgraded to the
     * inherited form; nested elements that declare their own hx-vals keep
     * them by using "hx-vals:append".
     */
    public static function injectVals(string $html, array $vals): string
    {
        if ($vals === [] || ($root = static::root($html)) === null) {
            return $html;
        }

        [$offset, $attributes] = $root;

        if (preg_match('/\s(hx-vals(?::inherited)?)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>\/]+))/i', $attributes, $existing, PREG_OFFSET_CAPTURE)) {
            $merged = static::mergeVals(static::attributeValue($existing), $vals);

            return static::replaceAttribute($html, $offset + $existing[0][1], strlen($existing[0][0]), 'hx-vals:inherited', $merged);
        }

        return static::insertAttribute($html, $offset, 'hx-vals:inherited', json_encode($vals, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Merge the given values into the root element's "x-data" attribute
     * (Alpine.js). An existing object literal is extended; the given values
     * come last, so they win over keys of the same name.
     */
    public static function injectData(string $html, array $data): string
    {
        if ($data === [] || ($root = static::root($html)) === null) {
            return $html;
        }

        [$offset, $attributes] = $root;

        if (preg_match('/\s(x-data)(?:\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>\/]+)))?(?!\s*=)(?=[\s\/]|$)/i', $attributes, $existing, PREG_OFFSET_CAPTURE)) {
            $merged = static::mergeData(static::attributeValue($existing), $data);

            return static::replaceAttribute($html, $offset + $existing[0][1], strlen($existing[0][0]), 'x-data', $merged);
        }

        return static::insertAttribute($html, $offset, 'x-data', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }

    protected static function mergeVals(string $existing, array $vals): string
    {
        $existing = trim($existing);

        if ($existing === '') {
            return json_encode($vals, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        if (preg_match('/^(js|javascript):\s*(\{)(.*)$/s', $existing, $js)) {
            return $js[1].':{'.implode(', ', static::entries($vals)).', '.ltrim($js[3]);
        }

        $decoded = json_decode($existing, true);

        if (! is_array($decoded)) {
            throw new LogicException(
                'Lamx cannot merge the component state into the root element\'s hx-vals ['.$existing.']. '
                .'Use a JSON object or a "js:{...}" object literal.'
            );
        }

        return json_encode($vals + $decoded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    protected static function mergeData(string $existing, array $data): string
    {
        $existing = trim($existing);
        $entries = implode(', ', static::entries($data));

        if ($existing === '') {
            return '{'.$entries.'}';
        }

        if (! str_starts_with($existing, '{') || ! str_ends_with($existing, '}')) {
            throw new LogicException(
                'Lamx cannot merge the bindable properties into the root element\'s x-data ['.$existing.']. '
                .'Use an object literal: x-data="{ ... }".'
            );
        }

        $body = rtrim(trim(substr($existing, 1, -1)), ", \t\n\r");

        return '{'.($body === '' ? '' : $body.', ').$entries.'}';
    }

    /**
     * JavaScript object literal entries ("key": value) for the given values.
     *
     * @return array<int, string>
     */
    protected static function entries(array $values): array
    {
        $entries = [];

        foreach ($values as $key => $value) {
            $entries[] = json_encode((string) $key, JSON_THROW_ON_ERROR).': '.json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        }

        return $entries;
    }

    /**
     * Locate the root element: the offset right after its tag name and the
     * attribute string of its opening tag. Null when there is no element.
     *
     * @return array{0: int, 1: string}|null
     */
    protected static function root(string $html): ?array
    {
        if (! preg_match('/^(?:\s|<!--.*?-->)*<([a-zA-Z][\w.:-]*)/s', $html, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $afterTagName = $match[1][1] + strlen($match[1][0]);
        $tagClose = static::findOpeningTagEnd($html, $afterTagName);

        return [$afterTagName, substr($html, $afterTagName, $tagClose - $afterTagName)];
    }

    /**
     * The decoded value of an attribute matched with the quoted/unquoted
     * alternatives in groups 3, 4 and 5.
     */
    protected static function attributeValue(array $match): string
    {
        return html_entity_decode($match[5][0] ?? $match[4][0] ?? $match[3][0] ?? '', ENT_QUOTES | ENT_HTML5);
    }

    protected static function insertAttribute(string $html, int $offset, string $name, string $value): string
    {
        return substr_replace($html, ' '.$name.'="'.static::escape($value).'"', $offset, 0);
    }

    protected static function replaceAttribute(string $html, int $offset, int $length, string $name, string $value): string
    {
        return substr_replace($html, ' '.$name.'="'.static::escape($value).'"', $offset, $length);
    }

    /**
     * Find the offset of the ">" that closes the opening tag starting at $offset.
     */
    protected static function findOpeningTagEnd(string $html, int $offset): int
    {
        $quote = null;
        $length = strlen($html);

        for ($i = $offset; $i < $length; $i++) {
            $char = $html[$i];

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '>') {
                return $i;
            }
        }

        return $length;
    }

    protected static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
    }
}
