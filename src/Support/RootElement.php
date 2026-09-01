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
        if ($vals === [] || ! preg_match('/^(?:\s|<!--.*?-->)*<([a-zA-Z][\w.:-]*)/s', $html, $match, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $afterTagName = $match[1][1] + strlen($match[1][0]);
        $tagClose = static::findOpeningTagEnd($html, $afterTagName);
        $attributes = substr($html, $afterTagName, $tagClose - $afterTagName);

        if (preg_match('/\s(hx-vals(?::inherited)?)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>\/]+))/i', $attributes, $existing, PREG_OFFSET_CAPTURE)) {
            $value = html_entity_decode($existing[5][0] ?? $existing[4][0] ?? $existing[3][0] ?? '', ENT_QUOTES | ENT_HTML5);
            $replacement = ' hx-vals:inherited="'.static::escape(static::mergeVals($value, $vals)).'"';
            $offset = $afterTagName + $existing[0][1];

            return substr_replace($html, $replacement, $offset, strlen($existing[0][0]));
        }

        $attribute = ' hx-vals:inherited="'.static::escape(json_encode($vals, JSON_UNESCAPED_SLASHES)).'"';

        return substr_replace($html, $attribute, $afterTagName, 0);
    }

    protected static function mergeVals(string $existing, array $vals): string
    {
        $existing = trim($existing);

        if ($existing === '') {
            return json_encode($vals, JSON_UNESCAPED_SLASHES);
        }

        if (preg_match('/^(js|javascript):\s*(\{)(.*)$/s', $existing, $js)) {
            $entries = [];

            foreach ($vals as $key => $value) {
                $entries[] = json_encode((string) $key).': '.json_encode($value, JSON_UNESCAPED_SLASHES);
            }

            return $js[1].':{'.implode(', ', $entries).', '.ltrim($js[3]);
        }

        $decoded = json_decode($existing, true);

        if (! is_array($decoded)) {
            throw new LogicException(
                'Lamx cannot merge the component state into the root element\'s hx-vals ['.$existing.']. '
                .'Use a JSON object or a "js:{...}" object literal.'
            );
        }

        return json_encode($vals + $decoded, JSON_UNESCAPED_SLASHES);
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
