<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/actions.php';

$page = get_value('page', 'home');
$allowed = ['home','events','event','login','register','dashboard','edit','profile','about'];
if (!in_array($page,$allowed,true)) { $page = 'error'; }
$errors = []; $old = [];
try {
    // Establish the database before rendering to show a clean setup error.
    db();
    handle_post($page,$errors,$old);
    $user = current_user();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    $titles = ['home'=>'События, которые сближают','events'=>'Афиша событий','event'=>'О событии','login'=>'С возвращением','register'=>'Присоединяйтесь к Jinal','dashboard'=>'Мой кабинет','edit'=>'Создать событие','profile'=>'Мой профиль','about'=>'О проекте','error'=>'Страница не найдена'];
    $title = $titles[$page] ?? 'Jinal';
    $categories = query('SELECT * FROM categories ORDER BY id')->fetchAll();
    if ($page === 'home') {
        $featured = query('SELECT e.*, c.name AS category_name,c.slug AS category_slug,(SELECT COUNT(*) FROM bookings b WHERE b.event_id=e.id) AS registered_count FROM events e JOIN categories c ON c.id=e.category_id WHERE e.starts_at>NOW() ORDER BY e.starts_at LIMIT 6')->fetchAll();
        $stats = ['events'=>(int)query('SELECT COUNT(*) FROM events WHERE starts_at>NOW()')->fetchColumn(),'members'=>(int)query('SELECT COUNT(*) FROM users')->fetchColumn(),'categories'=>count($categories)];
    }
    if ($page === 'events') {
        $filters = ['q'=>mb_substr(get_value('q'),0,120),'category'=>get_value('category'),'city'=>get_value('city'),'date'=>get_value('date'),'sort'=>get_value('sort','date')];
        $where = ['e.starts_at>NOW()']; $params = [];
        if ($filters['q'] !== '') {
            // A literal search: user-supplied SQL wildcard characters are escaped.
            $needle = '%' . str_replace(['!','%','_'],['!!','!%','!_'],$filters['q']) . '%';
            $where[] = "(e.title LIKE ? ESCAPE '!' OR e.description LIKE ? ESCAPE '!')"; $params[]=$needle; $params[]=$needle;
        }
        if ($filters['category'] !== '') { $where[]='e.category_id=?'; $params[]=positive_id($filters['category']); }
        if ($filters['city'] !== '') { $where[]='e.city=?'; $params[]=mb_substr($filters['city'],0,80); }
        if ($filters['date'] !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d',$filters['date']);
            if ($date && $date->format('Y-m-d') === $filters['date']) { $where[]='e.starts_at>=?'; $params[]=$date->format('Y-m-d'); }
            else { $where[]='1=0'; }
        }
        $order = ['date'=>'e.starts_at ASC','price'=>'e.price ASC,e.starts_at ASC','popular'=>'registered_count DESC,e.starts_at ASC','newest'=>'e.created_at DESC,e.id DESC'];
        if (!isset($order[$filters['sort']])) { $filters['sort']='date'; }
        $clause = implode(' AND ',$where);
        $total = (int)query('SELECT COUNT(*) FROM events e WHERE ' . $clause,$params)->fetchColumn();
        $pages = max(1,(int)ceil($total/6)); $number = min($pages,max(1,positive_id($_GET['p'] ?? 1))); $offset=($number-1)*6;
        $pagination = ['page'=>$number,'pages'=>$pages,'total'=>$total];
        // LIMIT/OFFSET are computed integers; the sort clause comes from an allowlist.
        $events = query('SELECT e.*,c.name AS category_name,c.slug AS category_slug,(SELECT COUNT(*) FROM bookings b WHERE b.event_id=e.id) AS registered_count FROM events e JOIN categories c ON c.id=e.category_id WHERE ' . $clause . ' ORDER BY ' . $order[$filters['sort']] . ' LIMIT 6 OFFSET ' . $offset,$params)->fetchAll();
        $cities = query('SELECT DISTINCT city FROM events ORDER BY city')->fetchAll(PDO::FETCH_COLUMN);
    }
    if ($page === 'event') {
        $event = find_event(positive_id($_GET['id'] ?? 0));
        if (!$event) { abort_page(404,'Такого события нет. Возможно, организатор его удалил.'); }
        $title = $event['title']; $organizer=$event['organizer_name']; $attendees=(int)$event['registered_count'];
        $isRegistered = $user && (bool)query('SELECT id FROM bookings WHERE event_id=? AND user_id=?',[$event['id'],$user['id']])->fetchColumn();
        $comments=query('SELECT c.*,u.name AS author FROM comments c JOIN users u ON u.id=c.user_id WHERE c.event_id=? ORDER BY c.created_at DESC,c.id DESC LIMIT 100',[$event['id']])->fetchAll();
    }
    if (in_array($page,['dashboard','edit','profile'],true)) { $user=require_user(); }
    if ($page === 'dashboard') {
        $organized=query('SELECT e.*,c.name AS category_name,c.slug AS category_slug,(SELECT COUNT(*) FROM bookings b WHERE b.event_id=e.id) AS registered_count FROM events e JOIN categories c ON c.id=e.category_id WHERE e.organizer_id=? ORDER BY e.starts_at DESC',[$user['id']])->fetchAll();
        $bookings=query('SELECT e.*,b.id AS booking_id,c.name AS category_name,c.slug AS category_slug,(SELECT COUNT(*) FROM bookings b2 WHERE b2.event_id=e.id) AS registered_count FROM bookings b JOIN events e ON e.id=b.event_id JOIN categories c ON c.id=e.category_id WHERE b.user_id=? ORDER BY e.starts_at',[$user['id']])->fetchAll();
        $dashboardStats=['organized'=>count($organized),'bookings'=>count($bookings),'attendees'=>array_sum(array_column($organized,'registered_count'))];
    }
    if ($page === 'edit') {
        $id=positive_id($_POST['id'] ?? $_GET['id'] ?? 0); $event=$id ? find_event($id) : null;
        if ($id && !$event) { abort_page(404,'Событие не найдено.'); }
        if ($event && !can_manage($event,$user)) { abort_page(403,'Изменять это событие может только его организатор или администратор.'); }
        $title=$event ? 'Редактировать событие' : 'Создать событие';
    }
    if ($page === 'error') { http_response_code(404); $status=404; $message='Эта страница не найдена. Загляните в афишу — там точно есть что-то интересное.'; }
    require dirname(__DIR__) . '/templates/header.php';
    require dirname(__DIR__) . '/templates/' . $page . '.php';
    require dirname(__DIR__) . '/templates/footer.php';
} catch (Throwable $exception) {
    error_log('[Jinal] ' . $exception->getMessage());
    http_response_code(503);
    // No database credentials, exception trace or unescaped input is exposed.
    echo '<!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Jinal · Запуск</title><link rel="stylesheet" href="assets/style.css"><body><main class="container"><h1>Jinal скоро вернётся</h1><p>Не удалось загрузить приложение. Проверьте, что MySQL в XAMPP запущен и выполнена команда установки.</p><p>В папке проекта: <code>C:\\xampp\\php\\php.exe scripts/setup.php</code></p><p>Подробности доступны в журнале сервера.</p><a href="index.php">Повторить</a></main></body></html>';
}
