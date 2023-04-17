<?php

function connect_db() :PDO
{
    $db_host = 'db'; // Имя сервиса базы данных в docker-compose
    $db_name = 'test_db'; // Имя базы данных
    $db_user = 'test_user'; // Имя пользователя базы данных
    $db_password = 'password'; // Пароль пользователя базы данных

    try {
        $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
        exit();
    }
    return $conn;
}

// Заглушка для проверки email-адреса
function check_email($email): int
{
    usleep(rand(1000000, 60000000)); // Имитация задержки от 1 секунды до 1 минуты
    return rand(0, 1); // Рандом, валиден или нет
}

// Заглушка для отправки email-сообщения
function send_email($email, $from, $to, $subj, $body): bool
{
    usleep(rand(1000000, 10000000)); // Имитация задержки от 1 секунды до 10 секунд
    return true; // Сценарий фейла в отправке письма не рассматриваем
}

// Логирование факта отправки уведомлений с таймштампом
function log_sent_email(string $email, string $timestamp) :void
{
    $log_dir = 'logs';
    $log_file = 'sent_emails.log';
    $log_entry = "[$timestamp] Sent email to $email" . PHP_EOL;

    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    file_put_contents($log_dir . '/' . $log_file, $log_entry, FILE_APPEND);
}

// Проверка email-адресов
function process_emails(int $offset) :void
{
    $conn = connect_db();
    $emails = $conn->query("SELECT users.email FROM users LEFT JOIN emails ON users.email = emails.email WHERE validts <= DATE_ADD(NOW(), INTERVAL 3 DAY) AND emails.email IS NULL LIMIT 10 OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
    $conn = null;
    if (count($emails) == 0) {
        return;
    }

    foreach ($emails as $email) {
        $isValid = check_email($email['email']);
        $results[] = ['email' => $email['email'], 'isValid' => $isValid];
    }

    // Вставка результатов в таблицу emails
    $db = connect_db();
    // id=id - mysql аналог on conflict do nothing
    $insertOrUpdateStmt = $db->prepare("INSERT INTO emails (email, checked, valid) VALUES (:email, 1, :valid) ON DUPLICATE KEY UPDATE id=id");
    foreach ($results as $result) {
        $insertOrUpdateStmt->execute([':email' => $result['email'], ':valid' => $result['isValid']]);
    }
    $db = null;
}

// Отправка уведомлений пользователям об истечении подписок
function send_notifications(int $offset) :void
{
// Получаем список пользователей с истекающими подписками и валидными email-ами
    $db = connect_db();
    $sql = "SELECT users.username, users.email FROM users INNER JOIN emails ON users.email = emails.email WHERE validts <= DATE_ADD(NOW(), INTERVAL 3 DAY) AND emails.valid = 1 AND emails.expiration_notice = 0 LIMIT 10 OFFSET $offset";
    $result = $db->query($sql);
    $db = null;

    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $username = $row['username'];
        $email = $row['email'];
        $from = 'admin@example.com';
        $to = $email;
        $subj = 'Subscription Expiring Soon';
        $body = "{$username}, your subscription is expiring soon";

        send_email($email, $from, $to, $subj, $body);

        // Логирование отправленного письма
        $timestamp = date('Y-m-d H:i:s');
        log_sent_email($email, $timestamp);

        // Обновление поля expiration_notice в таблице emails
        $update_sql = "UPDATE emails SET expiration_notice = 1 WHERE email = :email";
        $db = connect_db();
        $update_stmt = $db->prepare($update_sql);
        $update_stmt->bindParam(':email', $email);
        $update_stmt->execute();
        $db = null;
    }
}

// Основной скрипт
$option = $argv[1] ?? '';

if ($option === '-check') {
    // Второй аргумент - смещение для OFFSET в запросе
    $offset = isset($argv[2]) ? intval($argv[2]) : 0;
    process_emails($offset);
} elseif ($option === '-send') {
    // Второй аргумент - смещение для OFFSET в запросе
    $offset = isset($argv[2]) ? intval($argv[2]) : 0;
    send_notifications($offset);
} else {
    echo "Неизвестный аргумент.\n";
}
