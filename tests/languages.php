<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$base=rtrim($argv[1] ?? 'http://127.0.0.1:8088','/');
$checks=0;
function verify(bool $value,string $label): void { global $checks; if (!$value) { throw new RuntimeException($label); } $checks++; echo 'PASS: ' . $label . PHP_EOL; }
function fetch_page(string $query,string $cookies=''): array {
    global $base;
    $handle=curl_init($base.'/index.php?'.$query);
    curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIE=>$cookies,CURLOPT_TIMEOUT=>10]);
    $response=curl_exec($handle);
    if ($response === false) { throw new RuntimeException(curl_error($handle)); }
    $code=curl_getinfo($handle,CURLINFO_RESPONSE_CODE); $offset=curl_getinfo($handle,CURLINFO_HEADER_SIZE); curl_close($handle);
    return [$code,substr($response,$offset),substr($response,0,$offset)];
}
try {
    foreach (['ru','kk','en'] as $lang) {
        foreach (['home','events','login','register','about'] as $page) {
            [$code,$html,$headers]=fetch_page('page='.$page.'&lang='.$lang);
            verify($code===200 && str_contains($html,'lang="'.$lang.'"'),$lang.' '.$page.' renders with correct HTML language');
            verify(!str_contains($html,'Warning:') && !str_contains($html,'Fatal error:'),$lang.' '.$page.' has no visible PHP error');
        }
    }
    [, $english,$headers]=fetch_page('page=events&lang=en');
    $dom=new DOMDocument(); @$dom->loadHTML($english);
    $xpath=new DOMXPath($dom);
    verify($xpath->query('//select[@name="city"]/option[@value="Алматы"]')->length===1,'City filter keeps original database value');
    verify(str_contains($headers,'jinal_lang=en'),'Language choice saved as cookie');
    [, $remembered]=fetch_page('page=login','jinal_lang=en');
    verify(str_contains($remembered,'lang="en"'),'Language cookie is remembered');
    [, $fallback]=fetch_page('page=login&lang=invalid');
    verify(str_contains($fallback,'lang="ru"'),'Unsupported language falls back to Russian');
    verify(str_contains($english,'events found'),'English catalog count translated');
    [, $kazakh]=fetch_page('page=events&lang=kk');
    verify(str_contains(html_entity_decode($kazakh,ENT_QUOTES|ENT_HTML5,'UTF-8'),'іс-шара табылды'),'Kazakh catalog count translated');
    [, $detail]=fetch_page('page=event&id=1&lang=en');
    verify(str_contains($detail,'places'),'Dynamic seat count translated');
    verify(str_contains($english,'value="date"'),'Sorting option value preserved');
    echo "SUCCESS: {$checks} language checks passed." . PHP_EOL;
} catch (Throwable $error) { fwrite(STDERR,'FAIL: '.$error->getMessage().PHP_EOL); exit(1); }
