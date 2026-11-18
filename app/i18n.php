<?php
declare(strict_types=1);

/** UI language is explicit in links and remembered for the next visit. */
function active_lang(): string
{
    static $language;
    if ($language !== null) { return $language; }
    $requested = $_GET['lang'] ?? $_COOKIE['jinal_lang'] ?? 'ru';
    $language = is_string($requested) && in_array($requested,['ru','kk','en'],true) ? $requested : 'ru';
    return $language;
}

function translations(): array
{
    static $dictionary;
    if ($dictionary === null) {
        $file = __DIR__ . '/translations/' . active_lang() . '.php';
        $dictionary = active_lang() !== 'ru' && is_file($file) ? require $file : [];
    }
    return $dictionary;
}

function translate_text(string $text): string
{
    if (active_lang() === 'ru') { return $text; }
    $source = trim($text);
    if ($source === '') { return $text; }
    $dictionary = translations();
    $translated = $dictionary[$source] ?? null;
    $en = active_lang() === 'en';
    if ($translated === null && str_ends_with($source,' · Jinal')) {
        $part = substr($source,0,-strlen(' · Jinal'));
        $translated = ($dictionary[$part] ?? $part) . ' · Jinal';
    }
    if ($translated === null) {
        $patterns = [
            '/^Ошибка (\d+)$/u' => static fn($m) => $en ? 'Error ' . $m[1] : 'Қате ' . $m[1],
            '/^(\d+) событий найдено$/u' => static fn($m) => $en ? $m[1] . ' events found' : $m[1] . ' іс-шара табылды',
            '/^(\d+) \/ (\d+) участников$/u' => static fn($m) => $en ? $m[1] . ' / ' . $m[2] . ' participants' : $m[1] . ' / ' . $m[2] . ' қатысушы',
            '/^(\d+) из (\d+) мест$/u' => static fn($m) => $en ? $m[1] . ' of ' . $m[2] . ' places' : $m[2] . ' орынның ' . $m[1] . '-і',
            '/^С возвращением, (.+)!$/u' => static fn($m) => $en ? 'Welcome back, ' . $m[1] . '!' : 'Қайта келгеніңізге қуаныштымыз, ' . $m[1] . '!',
            '/^Привет, (.+)$/u' => static fn($m) => $en ? 'Hi, ' . $m[1] : 'Сәлем, ' . $m[1],
            '/^Вместимость не может быть меньше количества зарегистрированных участников \((\d+)\)\.$/u' => static fn($m) => $en ? 'Capacity cannot be lower than the number of registered participants (' . $m[1] . ').' : 'Орын саны тіркелген қатысушылар санынан (' . $m[1] . ') кем болмауы тиіс.',
        ];
        foreach ($patterns as $pattern=>$replacement) {
            if (preg_match($pattern,$source,$matches)) { $translated=$replacement($matches); break; }
        }
    }
    // Labels may be joined by a middle dot in compact event metadata.
    if ($translated === null && str_contains($source,' · ')) {
        $parts=explode(' · ',$source);
        $translated=implode(' · ',array_map(static fn($part)=>$dictionary[$part] ?? $part,$parts));
    }
    if ($translated === null) { return $text; }
    $start = strpos($text,$source);
    return substr($text,0,$start) . $translated . substr($text,$start+strlen($source));
}

/** Translate server-rendered UI, preserving input values and escaped authored HTML. */
function translate_html(string $html): string
{
    if (active_lang() === 'ru' || !str_contains($html,'<html')) { return $html; }
    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument('1.0','UTF-8');
        if (!$document->loadHTML('<?xml encoding="UTF-8">' . $html,LIBXML_NONET)) { return $html; }
        $xpath=new DOMXPath($document);
        foreach ($xpath->query('//text()[not(ancestor::script) and not(ancestor::style) and not(ancestor::*[@translate="no"])]') as $node) {
            $node->nodeValue=translate_text($node->nodeValue);
        }
        foreach ($xpath->query('//*') as $element) {
            if (!$element instanceof DOMElement) { continue; }
            foreach (['placeholder','aria-label','title','alt','data-confirm'] as $attribute) {
                if ($element->hasAttribute($attribute)) { $element->setAttribute($attribute,translate_text($element->getAttribute($attribute))); }
            }
            if ($element->tagName === 'meta' && $element->getAttribute('name') === 'description') { $element->setAttribute('content',translate_text($element->getAttribute('content'))); }
        }
        $document->documentElement->setAttribute('lang',active_lang());
        foreach (iterator_to_array($document->childNodes) as $node) { if ($node->nodeType === XML_PI_NODE) { $document->removeChild($node); } }
        return $document->saveHTML() ?: $html;
    } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
}
