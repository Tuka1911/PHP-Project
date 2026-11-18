<?php
declare(strict_types=1);
/** Real HTTP integration checks. Creates and removes only uniquely named test data. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8088','/');
$tag = 'qa_' . bin2hex(random_bytes(5));
$emails = [$tag . '@example.test',$tag . '_other@example.test'];
$cookies = [tempnam(sys_get_temp_dir(),'jinal_'),tempnam(sys_get_temp_dir(),'jinal_')];
$checks = 0;
function check(bool $condition, string $name): void { global $checks; if (!$condition) { throw new RuntimeException('FAIL: ' . $name); } $checks++; echo 'PASS: ' . $name . PHP_EOL; }
function request(string $page, array $fields = [], int $client = 0): array
{
    global $base,$cookies;
    $curl = curl_init($base . '/index.php?' . $page);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookies[$client],CURLOPT_COOKIEFILE=>$cookies[$client],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>10]);
    if ($fields) { curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($fields)]); }
    $body = curl_exec($curl);
    if ($body === false) { throw new RuntimeException(curl_error($curl)); }
    $code = curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    return [$code,$body];
}
function token(int $client = 0): string
{
    [$code,$html] = request('page=login',[],$client);
    if (!preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$match)) { throw new RuntimeException('No CSRF token in rendered page: ' . $code); }
    return $match[1];
}
try {
    foreach (['home','events','about','login','register'] as $page) { check(request('page='.$page)[0] === 200,'Public page ' . $page); }
    check(request('page=dashboard')[0] === 303,'Guest dashboard protected');
    check(request('page=missing')[0] === 404,'Unknown page returns 404');
    check(request('page=event&id=99999999')[0] === 404,'Missing event returns 404');
    check(request('page=register',['action'=>'register','csrf'=>'invalid'])[0] === 419,'CSRF rejects forged request');
    check(request('page=register',['action'=>'register','csrf'=>token(),'name'=>'X','email'=>'bad','password'=>'short','password_confirmation'=>'different'])[0] === 200,'Invalid registration stays on form');
    check(!(bool)query('SELECT id FROM users WHERE email=?',['bad'])->fetchColumn(),'Invalid registration not persisted');
    for ($i=0;$i<2;$i++) {
        check(request('page=register',['action'=>'register','csrf'=>token($i),'name'=>'QA ' . $i,'email'=>$emails[$i],'password'=>'TestSecure!2026','password_confirmation'=>'TestSecure!2026'],$i)[0] === 303,'Register user ' . $i);
    }
    $owner=(int)query('SELECT id FROM users WHERE email=?',[$emails[0]])->fetchColumn();
    $profile=request('page=profile')[1];
    check(str_contains($profile,'name="bio"') && str_contains($profile,'name="action" value="profile_save"'),'Profile renders editable form markup');
    check(!str_contains($profile,'h?=') && !str_contains($profile,'hsection'),'Profile contains no leaked template source');
    check(password_verify('TestSecure!2026',(string)query('SELECT password_hash FROM users WHERE id=?',[$owner])->fetchColumn()),'Password stored as verifiable hash');
    $category=(int)query('SELECT id FROM categories ORDER BY id LIMIT 1')->fetchColumn();
    $payload=['action'=>'event_save','csrf'=>token(),'title'=>$tag . ' Test event','description'=>'A sufficiently long description used for automated integration testing.','category_id'=>$category,'city'=>'Алматы','venue'=>'QA test venue','starts_at'=>date('Y-m-d\TH:i',strtotime('+10 days')),'capacity'=>'1','price'=>'0'];
    check(request('page=edit',$payload)[0] === 303,'Create event');
    $eventId=(int)query('SELECT id FROM events WHERE title=?',[$payload['title']])->fetchColumn();
    check($eventId>0,'Created event persisted');
    check(request('page=event&id='.$eventId)[0] === 200,'Read event');
    $payload['id']=$eventId; $payload['title'].=' updated';
    check(request('page=edit&id='.$eventId,$payload)[0] === 303,'Update own event');
    check(query('SELECT title FROM events WHERE id=?',[$eventId])->fetchColumn() === $payload['title'],'Update persisted');
    $foreign=$payload; $foreign['csrf']=token(1);
    check(request('page=edit&id='.$eventId,$foreign,1)[0] === 403,'Other user cannot edit event');
    check(request('page=edit&id='.$eventId,[],1)[0] === 403,'Other user cannot open edit page');
    $book=['action'=>'booking_add','csrf'=>token(1),'event_id'=>$eventId];
    check(request('page=event&id='.$eventId,$book,1)[0] === 303,'Book available seat');
    request('page=event&id='.$eventId,$book,1);
    check((int)query('SELECT COUNT(*) FROM bookings WHERE event_id=?',[$eventId])->fetchColumn() === 1,'Duplicate booking prevented');
    request('page=event&id='.$eventId,['action'=>'booking_add','csrf'=>token(),'event_id'=>$eventId]);
    check((int)query('SELECT COUNT(*) FROM bookings WHERE event_id=?',[$eventId])->fetchColumn() === 1,'Full event cannot be overbooked');
    $payload['capacity']='0'; request('page=edit&id='.$eventId,$payload);
    check((int)query('SELECT capacity FROM events WHERE id=?',[$eventId])->fetchColumn() === 1,'Invalid capacity rejected');
    $xss='<script>alert("qa")</script>';
    request('page=event&id='.$eventId,['action'=>'comment_add','csrf'=>token(1),'event_id'=>$eventId,'body'=>$xss],1);
    $html=request('page=event&id='.$eventId)[1];
    check(str_contains($html,'&lt;script&gt;') && !str_contains($html,$xss),'Comments escape XSS');
    check(str_contains(request('page=events&q='.urlencode($tag))[1],e($payload['title'])),'Search finds created event');
    $filtered=request('page=events&category='.$category.'&city='.urlencode('Алматы').'&sort=price');
    check($filtered[0] === 200 && str_contains($filtered[1],e($payload['title'])),'Category city and sort work');
    check(request('page=events&q='.urlencode("' OR 1=1 --"))[0] === 200,'SQL injection search handled as text');
    check(request('page=events&sort='.urlencode('DROP TABLE users'))[0] === 200,'Sort allowlist rejects SQL fragment');
    check(request('page=events&p=2')[0] === 200,'Pagination page two');
    check(request('page=events&date=2026-99-99')[0] === 200,'Invalid date handled safely');
    request('page=profile',['action'=>'profile_save','csrf'=>token(),'name'=>'QA Updated','bio'=>'Test profile']);
    check(query('SELECT name FROM users WHERE id=?',[$owner])->fetchColumn() === 'QA Updated','Profile update persisted');
    request('page=dashboard',['action'=>'booking_cancel','csrf'=>token(1),'event_id'=>$eventId],1);
    check((int)query('SELECT COUNT(*) FROM bookings WHERE event_id=?',[$eventId])->fetchColumn() === 0,'Cancel own booking');
    check(request('page=dashboard',['action'=>'event_delete','csrf'=>token(1),'event_id'=>$eventId],1)[0] === 403,'Other user cannot delete event');
    check(request('page=dashboard',['action'=>'event_delete','csrf'=>token(),'event_id'=>$eventId])[0] === 303,'Delete own event');
    check(!(bool)query('SELECT id FROM events WHERE id=?',[$eventId])->fetchColumn(),'Deletion persisted');
    check((int)query('SELECT COUNT(*) FROM comments WHERE event_id=?',[$eventId])->fetchColumn() === 0,'Related comments cascade on delete');
    request('page=home',['action'=>'logout','csrf'=>token()]);
    check(request('page=dashboard')[0] === 303,'Logout clears authentication');
    check(request('page=login',['action'=>'login','csrf'=>token(),'email'=>$emails[0],'password'=>'TestSecure!2026'])[0] === 303,'Login with registered credentials');
    request('page=home',['action'=>'logout','csrf'=>token()]);
    for ($i=0;$i<6;$i++) { $last=request('page=login',['action'=>'login','csrf'=>token(),'email'=>$emails[0],'password'=>'wrong']); }
    check(str_contains($last[1],'Слишком много попыток'),'Login rate limit after failed attempts');
    echo "SUCCESS: {$checks} integration checks passed." . PHP_EOL;
} catch (Throwable $exception) { fwrite(STDERR,$exception->getMessage() . PHP_EOL); $failed=true; }
finally {
    foreach ($emails as $email) {
        query('DELETE FROM login_attempts WHERE identifier=?',[hash('sha256',$email.'|127.0.0.1')]);
        query('DELETE FROM users WHERE email=?',[$email]);
    }
    foreach ($cookies as $cookie) { if (is_file($cookie)) { unlink($cookie); } }
}
exit(!empty($failed) ? 1 : 0);
