<?php
declare(strict_types=1);

/** All state changes are POST requests protected by a session CSRF token. */
function handle_post(string &$page, array &$errors, array &$old): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { return; }
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        abort_page(419, 'Время действия формы истекло. Обновите страницу и повторите отправку.');
    }
    $action = input('action');
    // Passwords are never copied into form values or logs.
    $old = array_filter($_POST, static fn($value,$key) => is_string($value) && !in_array($key, ['password','password_confirmation','csrf'], true), ARRAY_FILTER_USE_BOTH);
    if ($action === 'logout') {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        flash('Вы вышли из аккаунта. До встречи!'); redirect('home');
    }
    if ($action === 'register') {
        $page = 'register'; $name = input('name'); $email = mb_strtolower(input('email')); $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) { $errors[] = 'Имя должно содержать от 2 до 80 символов.'; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) { $errors[] = 'Укажите корректный email.'; }
        if (strlen($password) < 10 || strlen($password) > 72) { $errors[] = 'Пароль должен содержать от 10 до 72 байт (для латиницы — символов).'; }
        if ($password !== ($_POST['password_confirmation'] ?? '')) { $errors[] = 'Пароли не совпадают.'; }
        if (!$errors) {
            try {
                query('INSERT INTO users (name,email,password_hash,role,bio) VALUES (?,?,?,\'user\',\'\')', [$name,$email,password_hash($password, PASSWORD_DEFAULT)]);
                session_regenerate_id(true); $_SESSION['user_id'] = (int) db()->lastInsertId(); $_SESSION['csrf'] = bin2hex(random_bytes(32));
                flash('Добро пожаловать в Jinal! Найдите своё первое событие.'); redirect('events');
            } catch (PDOException $exception) {
                if ($exception->getCode() !== '23000') { throw $exception; }
                $errors[] = 'Этот email уже зарегистрирован. Войдите в аккаунт.';
            }
        }
        return;
    }
    if ($action === 'login') {
        $page = 'login'; $email = mb_strtolower(input('email')); $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $identifier = hash('sha256', $email . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'local'));
        $attempts = (int) query('SELECT COUNT(*) FROM login_attempts WHERE identifier=? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)', [$identifier])->fetchColumn();
        if ($attempts >= 5) { $errors[] = 'Слишком много попыток. Попробуйте снова через 15 минут.'; return; }
        $account = query('SELECT * FROM users WHERE email=?', [$email])->fetch();
        if (!$account || !password_verify($password, $account['password_hash'])) {
            query('INSERT INTO login_attempts (identifier) VALUES (?)', [$identifier]);
            $errors[] = 'Неверный email или пароль.'; return;
        }
        query('DELETE FROM login_attempts WHERE identifier=?', [$identifier]);
        session_regenerate_id(true); $_SESSION['user_id'] = (int) $account['id']; $_SESSION['csrf'] = bin2hex(random_bytes(32));
        flash('С возвращением, ' . $account['name'] . '!'); redirect('dashboard');
    }
    $user = require_user();
    if ($action === 'profile_save') {
        $page = 'profile'; $name = input('name'); $bio = input('bio');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) { $errors[] = 'Имя должно содержать от 2 до 80 символов.'; }
        if (mb_strlen($bio) > 500) { $errors[] = 'О себе: не более 500 символов.'; }
        if (!$errors) { query('UPDATE users SET name=?,bio=? WHERE id=?', [$name,$bio,$user['id']]); flash('Профиль обновлён.'); redirect('profile'); }
        return;
    }
    if ($action === 'event_save') {
        $page = 'edit'; $id = positive_id($_POST['id'] ?? 0); $existing = $id ? find_event($id) : null;
        if ($id && (!$existing || !can_manage($existing,$user))) { abort_page(403, 'Изменять событие может только его организатор или администратор.'); }
        $title = input('title'); $description = input('description'); $city = input('city'); $venue = input('venue');
        $category = positive_id($_POST['category_id'] ?? 0); $capacity = filter_var(input('capacity'),FILTER_VALIDATE_INT); $price = input('price');
        $start = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', input('starts_at'));
        if (!$start) { $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', input('starts_at')); }
        if (mb_strlen($title) < 5 || mb_strlen($title) > 120) { $errors[] = 'Название должно содержать от 5 до 120 символов.'; }
        if (mb_strlen($description) < 30 || mb_strlen($description) > 10000) { $errors[] = 'Описание должно содержать от 30 до 10 000 символов.'; }
        if (mb_strlen($city) < 2 || mb_strlen($city) > 80 || mb_strlen($venue) < 3 || mb_strlen($venue) > 160) { $errors[] = 'Укажите город (2–80 символов) и место проведения (3–160 символов).'; }
        if (!$start || $start->format('Y-m-d\TH:i') !== str_replace(' ', 'T', substr(input('starts_at'),0,16)) || $start <= new DateTimeImmutable()) { $errors[] = 'Укажите корректную будущую дату и время.'; }
        if ($capacity === false || $capacity < 1 || $capacity > 10000) { $errors[] = 'Количество мест — от 1 до 10 000.'; }
        if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/D', $price)) { $errors[] = 'Стоимость должна быть числом от 0 до 9 999 999.99 ₸.'; }
        $categoryRow = query('SELECT * FROM categories WHERE id=?',[$category])->fetch();
        if (!$categoryRow) { $errors[] = 'Выберите существующую категорию.'; }
        $poster = input('poster') ?: ($categoryRow['slug'] ?? 'meetup');
        if (!in_array($poster,['workshop','music','sport','art','tech','meetup'],true)) { $errors[]='Выберите обложку из предложенных вариантов.'; }
        if (!$errors) {
            db()->beginTransaction();
            try {
                if ($id) {
                    query('SELECT id FROM events WHERE id=? FOR UPDATE',[$id]);
                    $count = (int) query('SELECT COUNT(*) FROM bookings WHERE event_id=?',[$id])->fetchColumn();
                    if ($capacity < $count) { db()->rollBack(); $errors[] = 'Вместимость не может быть меньше количества зарегистрированных участников (' . $count . ').'; return; }
                    query('UPDATE events SET title=?,description=?,category_id=?,city=?,venue=?,starts_at=?,capacity=?,price=?,poster=? WHERE id=?',[$title,$description,$category,$city,$venue,$start->format('Y-m-d H:i:s'),$capacity,$price,$poster,$id]);
                } else {
                    query('INSERT INTO events (organizer_id,title,description,category_id,city,venue,starts_at,capacity,price,poster) VALUES (?,?,?,?,?,?,?,?,?,?)',[$user['id'],$title,$description,$category,$city,$venue,$start->format('Y-m-d H:i:s'),$capacity,$price,$poster]);
                    $id = (int) db()->lastInsertId();
                }
                db()->commit();
            } catch (Throwable $exception) { if (db()->inTransaction()) { db()->rollBack(); } throw $exception; }
            flash('Событие сохранено.'); redirect('event',['id'=>$id]);
        }
        return;
    }
    $id = positive_id($_POST['event_id'] ?? $_POST['id'] ?? 0);
    $event = find_event($id);
    if (!$event) { abort_page(404, 'Событие не найдено.'); }
    if ($action === 'event_delete') {
        if (!can_manage($event,$user)) { abort_page(403,'Удалять событие может только его организатор или администратор.'); }
        query('DELETE FROM events WHERE id=?',[$id]); flash('Событие удалено.'); redirect('dashboard');
    }
    if ($action === 'booking_add') {
        db()->beginTransaction();
        try {
            $locked = query('SELECT * FROM events WHERE id=? FOR UPDATE',[$id])->fetch();
            if (!$locked || strtotime($locked['starts_at']) <= time()) { db()->rollBack(); flash('Регистрация на это событие закрыта.','error'); redirect('event',['id'=>$id]); }
            $count = (int) query('SELECT COUNT(*) FROM bookings WHERE event_id=?',[$id])->fetchColumn();
            $already = query('SELECT id FROM bookings WHERE event_id=? AND user_id=?',[$id,$user['id']])->fetchColumn();
            if ($already || $count >= (int)$locked['capacity']) {
                db()->rollBack(); flash($already ? 'Вы уже записаны на это событие.' : 'Свободных мест больше нет.','info'); redirect('event',['id'=>$id]);
            }
            query('INSERT INTO bookings (user_id,event_id) VALUES (?,?)',[$user['id'],$id]); db()->commit();
        } catch (Throwable $exception) { if (db()->inTransaction()) { db()->rollBack(); } throw $exception; }
        flash('Место забронировано! Событие появилось в вашем кабинете.'); redirect('event',['id'=>$id]);
    }
    if ($action === 'booking_cancel') {
        // Use the same parent-row lock as booking and capacity updates.
        db()->beginTransaction();
        try { query('SELECT id FROM events WHERE id=? FOR UPDATE',[$id]); query('DELETE FROM bookings WHERE user_id=? AND event_id=?',[$user['id'],$id]); db()->commit(); }
        catch (Throwable $exception) { if (db()->inTransaction()) { db()->rollBack(); } throw $exception; }
        flash('Запись отменена.'); redirect('dashboard');
    }
    if ($action === 'comment_add') {
        $body = input('body');
        if (mb_strlen($body) < 3 || mb_strlen($body) > 2000) { flash('Комментарий должен содержать от 3 до 2000 символов.','error'); }
        else { query('INSERT INTO comments (user_id,event_id,body) VALUES (?,?,?)',[$user['id'],$id,$body]); flash('Комментарий опубликован.'); }
        redirect('event',['id'=>$id]);
    }
    abort_page(400, 'Неизвестное действие.');
}
