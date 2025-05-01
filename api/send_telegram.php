<?php
    // --- НАСТРОЙКИ ---
    $botToken = "8175695105:AAH07fdPH-xa1jX8jhTJyUtjQ6VXkJdGhW0"; // <<<=== ЗАМЕНИТЕ НА ВАШ ТОКЕН БОТА
    $chatId = "1398791782"; // <<<=== ЗАМЕНИТЕ НА ВАШ CHAT ID (число)
    // -----------------

    // Разрешаем CORS-запросы со всех доменов (*).
    // ВНИМАНИЕ: В продакшене для безопасности лучше ограничить домен:
    // header("Access-Control-Allow-Origin: https://remontcars.github.io/");
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");

    // Обработка preflight запроса OPTIONS (необходимо для CORS при отправке JSON)
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    // Принимаем только POST запросы
    if ($_SERVER["REQUEST_METHOD"] == "POST") {

        // Получаем JSON данные из тела запроса (отправленные через fetch)
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        // Проверяем, что JSON успешно декодирован и является массивом
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Ошибка: Неверный формат данных (ожидается JSON).']);
            exit;
        }

        // Извлекаем данные из массива, защищая от HTML-тегов
        $name = isset($data['name']) ? htmlspecialchars(trim($data['name'])) : '';
        $phone = isset($data['phone']) ? htmlspecialchars(trim($data['phone'])) : '';
        $truckModel = isset($data['truckModel']) ? htmlspecialchars(trim($data['truckModel'])) : 'Не указана';
        $messageText = isset($data['message']) ? htmlspecialchars(trim($data['message'])) : '';

        // Простая валидация на сервере
        if (empty($name) || empty($phone) || empty($messageText)) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Ошибка: Пожалуйста, заполните все обязательные поля (Имя, Телефон, Описание).']);
            exit;
        }

        // Формируем текст сообщения для Telegram
        // Используем HTML теги для форматирования (<b> - жирный)
        $telegramMessage = "<b>🔔 Новая заявка с сайта!</b>\n\n";
        $telegramMessage .= "<b>👤 Имя:</b> " . $name . "\n";
        $telegramMessage .= "<b>📞 Телефон:</b> " . $phone . "\n";
        $telegramMessage .= "<b>🚚 Модель:</b> " . $truckModel . "\n\n";
        $telegramMessage .= "<b>📝 Описание:</b>\n" . $messageText;

        // URL для запроса к Telegram API
        $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";

        // Параметры запроса
        $params = [
            'chat_id' => $chatId,
            'text' => $telegramMessage,
            'parse_mode' => 'HTML' // Указываем, что используем HTML-теги для форматирования
        ];

        // Отправка запроса через cURL
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params)); // Кодируем параметры для POST
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Возвращать ответ сервера, а не выводить его
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Таймаут на выполнение запроса (секунды)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Проверка SSL сертификата (рекомендуется)

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Получаем HTTP код ответа
        $curl_error = curl_error($ch); // Получаем ошибку cURL, если она есть
        curl_close($ch);

        // Готовим ответ для JavaScript
        header('Content-Type: application/json'); // Указываем, что ответ будет в формате JSON

        // Проверяем результат отправки
        if ($httpcode == 200 && $response) {
             $responseData = json_decode($response, true);
             if ($responseData && $responseData['ok']) {
                 // Успешно отправлено в Telegram
                 http_response_code(200);
                 echo json_encode(['success' => true, 'message' => 'Заявка успешно отправлена!']);
             } else {
                 // Ошибка со стороны Telegram API
                 http_response_code(500); // Internal Server Error
                 $errorMsg = isset($responseData['description']) ? $responseData['description'] : 'Неизвестная ошибка Telegram API';
                 error_log("Telegram API Error: " . $errorMsg); // Логируем ошибку на сервере (если настроено)
                 echo json_encode(['success' => false, 'message' => 'Ошибка при отправке в Telegram: ' . $errorMsg]);
             }
        } else {
            // Ошибка сети, таймаут или сервера Telegram
             http_response_code(502); // Bad Gateway (или другой код > 400)
             error_log("cURL Error: " . $curl_error . " | HTTP Code: " . $httpcode); // Логируем ошибку
             echo json_encode(['success' => false, 'message' => 'Не удалось связаться с сервером Telegram. Ошибка сети или сервера. ' . $curl_error]);
        }

    } else {
        // Если запрос не методом POST
        http_response_code(405); // Method Not Allowed
        header('Allow: POST, OPTIONS'); // Сообщаем разрешенные методы
        echo json_encode(['success' => false, 'message' => 'Метод не поддерживается. Используйте POST.']);
    }
?>