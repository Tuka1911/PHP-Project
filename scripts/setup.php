<?php
declare(strict_types=1);

// Installation deliberately remains CLI-only; no public setup endpoint exists.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

date_default_timezone_set('Asia/Almaty');
$root = dirname(__DIR__);
$local = is_file($root . '/config.local.php') ? require $root . '/config.local.php' : [];
if (!is_array($local)) {
    fwrite(STDERR, "config.local.php must return an array.\n");
    exit(1);
}
$setting = static function (string $env, string $key, string $default) use ($local): string {
    $value = getenv($env);
    return $value !== false ? $value : (string)($local[$key] ?? $default);
};
$host = $setting('DB_HOST', 'host', '127.0.0.1');
$port = $setting('DB_PORT', 'port', '3306');
$name = $setting('DB_NAME', 'name', 'jinal');
$user = $setting('DB_USER', 'user', 'root');
$password = $setting('DB_PASS', 'password', '');
if (!preg_match('/\A[a-zA-Z0-9_]{1,64}\z/', $name) || !ctype_digit($port)) {
    fwrite(STDERR, "Invalid DB_NAME or DB_PORT.\n");
    exit(1);
}

try {
    $db = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $db->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db->exec("USE `{$name}`");
    $sql = file_get_contents($root . '/database/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('Cannot read database/schema.sql.');
    }
    $db->exec($sql);
    echo "Database {$name}: schema ready (6 tables).\n";

    if ((int)$db->query('SELECT COUNT(*) FROM events')->fetchColumn() > 0) {
        echo "Events already exist. Existing data and passwords were preserved; seed skipped.\n";
        exit(0);
    }

    $db->beginTransaction();
    $demoPassword = 'JinalDemo!2026';
    $findUser = $db->prepare('SELECT id FROM users WHERE email = ?');
    $addUser = $db->prepare('INSERT INTO users (name, email, password_hash, role, bio) VALUES (?, ?, ?, ?, ?)');
    $userIds = [];
    foreach ([
        ['name' => 'Команда Jinal', 'email' => 'organiser@jinal.local', 'role' => 'admin', 'bio' => 'Собираем людей, которым интересно пробовать новое. Организуем встречи, мастерские и маленькие городские открытия.'],
        ['name' => 'Аружан', 'email' => 'member@jinal.local', 'role' => 'user', 'bio' => 'Люблю живую музыку, прогулки по городу и новые знакомства.'],
    ] as $demo) {
        $findUser->execute([$demo['email']]);
        $id = $findUser->fetchColumn();
        if ($id === false) {
            $addUser->execute([$demo['name'], $demo['email'], password_hash($demoPassword, PASSWORD_DEFAULT), $demo['role'], $demo['bio']]);
            $id = $db->lastInsertId();
        }
        $userIds[$demo['email']] = (int)$id;
    }

    $categoryIds = [];
    $findCategory = $db->prepare('SELECT id FROM categories WHERE slug = ?');
    $addCategory = $db->prepare('INSERT INTO categories (name, slug) VALUES (?, ?)');
    foreach (['workshop' => 'Мастер-классы', 'music' => 'Музыка', 'sport' => 'Спорт', 'art' => 'Искусство', 'tech' => 'Технологии', 'meetup' => 'Встречи'] as $slug => $label) {
        $findCategory->execute([$slug]);
        $id = $findCategory->fetchColumn();
        if ($id === false) {
            $addCategory->execute([$label, $slug]);
            $id = $db->lastInsertId();
        }
        $categoryIds[$slug] = (int)$id;
    }

    // All venues and events below are fictional demonstration content.
    // Dates are relative to installation, so the initial catalog is always upcoming.
    $events = [
        ['title' => 'Керамика и медленный вечер', 'slug' => 'workshop', 'city' => 'Алматы', 'venue' => 'Мастерская «Форма», ул. Жандосова, 12', 'days' => 3, 'time' => '18:30', 'capacity' => 16, 'price' => 8500,
            'description' => "Оставь рабочую неделю за дверью и попробуй создать вещь своими руками. Вместе с мастером вылепим чашку или небольшую вазу, познакомимся с фактурами глины и выберем глазурь. Навыки и инструменты не нужны — всё подготовлено на месте.\n\nВ стоимость входят материалы, два обжига, чай и помощь преподавателя. Готовую работу можно забрать через две недели. Приходи за десять минут до начала и выбирай одежду, которую не страшно испачкать."],
        ['title' => 'Джаз после заката', 'slug' => 'music', 'city' => 'Алматы', 'venue' => 'Камерный зал «Тёплый свет», ул. Кабанбай батыра, 54', 'days' => 5, 'time' => '20:00', 'capacity' => 80, 'price' => 5000,
            'description' => "Камерный вечер для тех, кто любит живой звук. Молодой квартет исполнит авторские композиции и знакомые джазовые стандарты. Контрабас, клавиши, саксофон и ударные создадут настроение, ради которого хочется отложить телефон.\n\nДвери открываются в 19:30, продолжительность концерта — полтора часа с небольшим перерывом. Рассадка свободная. Напитки оплачиваются отдельно; бронирование здесь подтверждает место, оплата участия производится у организатора."],
        ['title' => 'Утро в горах: прогулка к Медеу', 'slug' => 'sport', 'city' => 'Алматы', 'venue' => 'Сбор у автобусной остановки «Медеу»', 'days' => 7, 'time' => '08:00', 'capacity' => 24, 'price' => 0,
            'description' => "Встречаем рассвет у подножия гор и идём спокойным маршрутом с красивыми видами. Это дружеская прогулка без соревнования: будем останавливаться, фотографировать и знакомиться. Маршрут рассчитан на обычную физическую подготовку.\n\nВозьми воду, перекус, удобную обувь и дополнительный тёплый слой. Продолжительность — около трёх часов. При неблагоприятной погоде организатор перенесёт прогулку и сообщит об этом в описании события."],
        ['title' => 'Город в скетчбуке', 'slug' => 'art', 'city' => 'Астана', 'venue' => 'Студия «Линия», пр. Республики, 18', 'days' => 9, 'time' => '15:00', 'capacity' => 20, 'price' => 4500,
            'description' => "Учимся замечать город через рисунок. Художница покажет, как быстро намечать перспективу, выбирать выразительные детали и передавать настроение улицы. Начнём с простых упражнений и закончим собственной городской зарисовкой.\n\nБазовые материалы входят в стоимость, но любимый скетчбук можно принести с собой. Встреча подходит начинающим. Часть занятия проходит на улице, поэтому одевайся по погоде."],
        ['title' => 'PHP Meetup: от идеи до продукта', 'slug' => 'tech', 'city' => 'Алматы', 'venue' => 'Коворкинг «Среда», ул. Байзакова, 125', 'days' => 11, 'time' => '18:00', 'capacity' => 60, 'price' => 0,
            'description' => "Открытая встреча разработчиков о том, как превратить учебный проект в работающий сервис. Разберём структуру PHP-приложения, безопасную работу с MySQL и типичные ошибки первого деплоя. После коротких докладов останется время на вопросы.\n\nМожно прийти с ноутбуком и показать свой проект или просто послушать. Участие бесплатное, регистрация нужна для подготовки мест. В конце — знакомство с сообществом и обмен полезными ссылками."],
        ['title' => 'Книжный клуб без спойлеров', 'slug' => 'meetup', 'city' => 'Шымкент', 'venue' => 'Читальня «Однажды», ул. Тауке хана, 31', 'days' => 13, 'time' => '17:00', 'capacity' => 18, 'price' => 0,
            'description' => "Принеси книгу, которую хочется посоветовать, и историю о том, почему она тебе близка. Мы собираемся за большим столом, знакомимся и обмениваемся находками: от современной прозы до научпопа и поэзии. Обязательного списка чтения нет.\n\nКаждый участник получит несколько минут для рассказа, а затем начнётся свободная беседа. Чай можно заказать на месте. Книгу разрешается принести в электронном виде — важнее впечатление, которым хочется поделиться."],
        ['title' => 'Твой первый виниловый сет', 'slug' => 'music', 'city' => 'Астана', 'venue' => 'Музыкальная комната «33 оборота», ул. Кенесары, 40', 'days' => 16, 'time' => '19:00', 'capacity' => 12, 'price' => 7000,
            'description' => "Разберёмся, как устроены проигрыватели, чем отличается звучание пластинок и как собрать небольшой музыкальный сет. Ведущий покажет основы сведения, расскажет о подборе треков и даст каждому попробовать оборудование.\n\nСобственный опыт не требуется. Для практики приготовлена коллекция пластинок разных жанров; любимый винил тоже можно взять с собой. Занятие длится два часа в небольшой группе."],
        ['title' => 'Беговой клуб: лёгкий старт', 'slug' => 'sport', 'city' => 'Астана', 'venue' => 'Центральный парк, вход со стороны набережной', 'days' => 18, 'time' => '09:00', 'capacity' => 30, 'price' => 0,
            'description' => "Первые километры легче, когда рядом свои люди. Тренер проведёт разминку, поможет выбрать комфортный темп и объяснит основы техники. Пробежка чередуется с шагом, поэтому встреча подойдёт тем, кто только начинает.\n\nВозьми воду и надень удобные кроссовки. Плановая дистанция — до трёх километров без требования к скорости. После заминки можно остаться на чай и обсудить спортивные планы."],
        ['title' => 'Плёночная прогулка по Алматы', 'slug' => 'art', 'city' => 'Алматы', 'venue' => 'Сквер у театра оперы, ул. Кабанбай батыра, 110', 'days' => 21, 'time' => '11:00', 'capacity' => 15, 'price' => 3500,
            'description' => "Замедлимся и посмотрим на знакомые улицы как фотографы. Обсудим свет, композицию и работу с ограниченным количеством кадров. Пройдём небольшой маршрут по центру, отыщем неожиданные ракурсы и сделаем серию снимков.\n\nПодойдёт любая камера, включая телефон. Плёночный фотоаппарат можно принести, но это не обязательное условие. В стоимость входит сопровождение и последующий групповой разбор фотографий; плёнка и проявка приобретаются самостоятельно."],
        ['title' => 'Кофе: заваривай по-своему', 'slug' => 'workshop', 'city' => 'Шымкент', 'venue' => 'Кофейная лаборатория «Зёрна», ул. Казыбек би, 8', 'days' => 24, 'time' => '12:00', 'capacity' => 10, 'price' => 6500,
            'description' => "Практическая встреча о кофе, который легко приготовить дома. Бариста расскажет о помоле, воде и температуре, а затем вместе сравним несколько способов заваривания. Попробуем разные зёрна и научимся описывать вкус простыми словами.\n\nВсё оборудование и дегустационные порции включены. Можно принести свой домашний девайс и задать вопросы о рецепте. Продолжительность — два часа, количество мест ограничено, чтобы каждый успел попрактиковаться."],
        ['title' => 'Дизайн и код: вечер пет-проектов', 'slug' => 'tech', 'city' => 'Астана', 'venue' => 'Пространство «Орбита», пр. Мангилик Ел, 26', 'days' => 28, 'time' => '18:30', 'capacity' => 45, 'price' => 0,
            'description' => "Покажи небольшой проект и получи доброжелательную обратную связь от дизайнеров и разработчиков. За вечер посмотрим несколько коротких демонстраций, обсудим решения интерфейса и подумаем, как сделать следующий шаг. Можно прийти и без готового проекта.\n\nНа выступление отводится пять минут и ещё пять на вопросы. При регистрации достаточно забронировать место, а желание выступить можно отметить в комментарии к событию. Ноутбук и зарядка пригодятся для общения после основной части."],
        ['title' => 'Большой вечер настольных игр', 'slug' => 'meetup', 'city' => 'Алматы', 'venue' => 'Клуб «Соседи», ул. Желтоксан, 92', 'days' => 32, 'time' => '17:30', 'capacity' => 36, 'price' => 2500,
            'description' => "Собираемся вокруг настольных игр, чтобы познакомиться и хорошо провести вечер. Ведущие объяснят правила, помогут выбрать игру и составят компании по интересам. Начнём с быстрых игр на знакомство и перейдём к более длинным партиям.\n\nПриходи один или с друзьями — место за столом найдётся каждому зарегистрированному участнику. В стоимость входят игры, помощь ведущих и чай. Встреча рассчитана примерно на три часа; любимую настолку можно принести с собой."],
    ];

    $addEvent = $db->prepare('INSERT INTO events (organizer_id, category_id, title, description, city, venue, starts_at, capacity, price, poster) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $eventIds = [];
    $today = new DateTimeImmutable('today');
    foreach ($events as $event) {
        $date = $today->modify('+' . $event['days'] . ' days')->format('Y-m-d') . ' ' . $event['time'] . ':00';
        $addEvent->execute([$userIds['organiser@jinal.local'], $categoryIds[$event['slug']], $event['title'], $event['description'], $event['city'], $event['venue'], $date, $event['capacity'], $event['price'], $event['slug']]);
        $eventIds[] = (int)$db->lastInsertId();
    }
    $addBooking = $db->prepare('INSERT INTO bookings (user_id, event_id) VALUES (?, ?)');
    foreach ([0, 2, 4] as $eventIndex) {
        $addBooking->execute([$userIds['member@jinal.local'], $eventIds[$eventIndex]]);
    }
    $addComment = $db->prepare('INSERT INTO comments (user_id, event_id, body) VALUES (?, ?, ?)');
    $addComment->execute([$userIds['member@jinal.local'], $eventIds[0], 'Давно хотела попробовать керамику. Подскажите, пожалуйста, можно ли выбрать цвет глазури на месте?']);
    $addComment->execute([$userIds['organiser@jinal.local'], $eventIds[0], 'Да, мастер покажет палитру в начале занятия. Все материалы уже включены, достаточно прийти с хорошим настроением!']);
    $addComment->execute([$userIds['member@jinal.local'], $eventIds[4], 'Буду с учебным проектом на PHP. Интересно обсудить авторизацию и структуру базы данных.']);
    $db->commit();

    echo "Demo content ready: 2 users, 6 categories, 12 events, 3 bookings, 3 comments.\n";
    echo "Organiser: organiser@jinal.local\nMember: member@jinal.local\nDemo password: {$demoPassword}\n";
    echo "Dates use Asia/Almaty and are relative to the installation date.\n";
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "Setup failed: " . $error->getMessage() . "\nCheck that MySQL is started in XAMPP and review config.local.php / DB_* variables.\n");
    exit(1);
}
