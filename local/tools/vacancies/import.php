<?php

declare(strict_types=1);

/**
 * Импорт вакансий из vacancy.csv в инфоблок VACANCIES.
 *
 * Разместить рядом с vacancy.csv, например:
 * /local/tools/vacancies/import.php
 * /local/tools/vacancies/vacancy.csv
 *
 * GET: только предпросмотр, без изменений в базе.
 * POST: импорт одной или всех вакансий с проверкой сессии Битрикса.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!isset($USER) || !$USER->IsAdmin()) {
    http_response_code(403);
    exit('Доступ запрещён. Войдите как администратор.');
}

if (!\Bitrix\Main\Loader::includeModule('iblock')) {
    http_response_code(500);
    exit('Модуль iblock не подключён.');
}

$iblock = CIBlock::GetList([], [
    'TYPE' => 'CONTENT_RU',
    'CODE' => 'VACANCIES',
])->Fetch();

if (!$iblock) {
    http_response_code(500);
    exit('Инфоблок CONTENT_RU / VACANCIES не найден.');
}

$iblockId = (int) $iblock['ID'];
$csvFile = __DIR__ . '/vacancy.csv';

/** Очищает пробельные символы, сохраняя исходный регистр букв. */
function normalizeText(string $value): string
{
    $value = str_replace("\xC2\xA0", ' ', $value);
    $result = preg_replace('/\s+/u', ' ', trim($value));

    if ($result === null) {
        throw new RuntimeException('В CSV обнаружена строка с некорректной кодировкой.');
    }

    return $result;
}

/**
 * Преобразует список с маркерами • в значения множественного свойства.
 * Если маркеров нет, сохраняет весь текст одним значением.
 */
function parseBullets(string $text)
{
    $parts = preg_split('/\s*•\s*/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    if ($parts === false) {
        throw new RuntimeException('Не удалось разобрать маркированный список.');
    }

    $items = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $items[] = $part;
        }
    }

    // Для очистки пустого множественного свойства Bitrix ожидает false.
    return $items ?: false;
}

/** Возвращает пару [тип зарплаты, исходное значение без префикса]. */
function parseSalary(string $salary): array
{
    $salary = normalizeText($salary);

    if ($salary === '' || $salary === '-') {
        return ['', ''];
    }

    if (preg_match('/^(по\s+договор[её]нности|договорная)$/ui', $salary)) {
        return ['Договорная', ''];
    }

    if (preg_match('/^(от|до)\s+(.+)$/ui', $salary, $matches)) {
        return [mb_strtoupper($matches[1], 'UTF-8'), normalizeText($matches[2])];
    }

    return ['=', $salary];
}

/** Читает CSV с учётом многострочных ячеек и возвращает записи с ошибками. */
function readVacancies(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Не удалось открыть файл: ' . $path);
    }

    $records = [];

    try {
        if (fgetcsv($handle, 0, ',') === false) {
            throw new RuntimeException('CSV пустой или повреждён.');
        }

        $recordNumber = 1; // Заголовок уже прочитан.
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            ++$recordNumber;

            if ($row === [null]) {
                continue;
            }

            $error = '';
            if (count($row) !== 15) {
                $error = 'Ожидалось 15 столбцов, получено ' . count($row);
            } elseif (!ctype_digit(trim($row[0])) || (int) $row[0] <= 0) {
                $error = 'Некорректный номер вакансии в первом столбце';
            } elseif (trim($row[3]) === '') {
                $error = 'Не указано название вакансии';
            }

            $records[] = [
                'number' => $recordNumber,
                'data' => $row,
                'error' => $error,
            ];
        }
    } finally {
        fclose($handle);
    }

    return $records;
}

/** Нормализованное имя значения списка, только для поиска совпадений. */
function enumKey(string $value): string
{
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return mb_strtolower(normalizeText($value), 'UTF-8');
}

/** Один раз загружает свойства-списки и их варианты в кеш текущего запроса. */
function loadEnums(int $iblockId): array
{
    $required = [
        'OFFICE', 'LOCATION', 'TYPE', 'ACTIVITY',
        'SCHEDULE', 'FIELD', 'SALARY_TYPE',
    ];

    $cache = [];
    $properties = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId]);

    while ($property = $properties->Fetch()) {
        $code = (string) $property['CODE'];
        if (!in_array($code, $required, true)) {
            continue;
        }
        if ($property['PROPERTY_TYPE'] !== 'L') {
            throw new RuntimeException("Свойство {$code} должно иметь тип «Список».");
        }

        $propertyId = (int) $property['ID'];
        $cache[$code] = ['property_id' => $propertyId, 'values' => []];

        $items = CIBlockPropertyEnum::GetList(
            ['SORT' => 'ASC'],
            ['PROPERTY_ID' => $propertyId]
        );

        while ($enum = $items->Fetch()) {
            $cache[$code]['values'][enumKey((string) $enum['VALUE'])] = (int) $enum['ID'];
        }
    }

    foreach ($required as $code) {
        if (!isset($cache[$code])) {
            throw new RuntimeException("Свойство-список {$code} не найдено в инфоблоке.");
        }
    }

    return $cache;
}

/** Получает ID варианта списка, при необходимости добавляя недостающее значение. */
function resolveEnum(array &$cache, string $code, string $rawValue): ?int
{
    $value = normalizeText($rawValue);
    if ($value === '') {
        return null;
    }

    $key = enumKey($value);
    if (isset($cache[$code]['values'][$key])) {
        return $cache[$code]['values'][$key];
    }

    // Не выбираем «похожее» название наугад: это может быть другое значение.
    $id = (new CIBlockPropertyEnum())->Add([
        'PROPERTY_ID' => $cache[$code]['property_id'],
        'VALUE' => $value,
        'DEF' => 'N',
        'SORT' => 500,
    ]);

    if (!$id) {
        throw new RuntimeException("Не удалось добавить значение «{$value}» в список {$code}.");
    }

    $cache[$code]['values'][$key] = (int) $id;
    return (int) $id;
}

/** Собирает полный набор свойств из 15 столбцов CSV. */
function makePropertyValues(array $row, array &$enums): array
{
    [$salaryType, $salaryValue] = parseSalary($row[7]);

    $properties = [
        'OFFICE' => $row[1],
        'LOCATION' => $row[2],
        'REQUIRE' => parseBullets($row[4]),
        'DUTY' => parseBullets($row[5]),
        'CONDITIONS' => parseBullets($row[6]),
        'SALARY_TYPE' => $salaryType,
        'SALARY_VALUE' => $salaryValue,
        'TYPE' => $row[8],
        'ACTIVITY' => $row[9],
        'SCHEDULE' => $row[10],
        'FIELD' => $row[11],
        'EMAIL' => normalizeText($row[12]),
    ];

    foreach (['OFFICE', 'LOCATION', 'TYPE', 'ACTIVITY', 'SCHEDULE', 'FIELD', 'SALARY_TYPE'] as $code) {
        $properties[$code] = resolveEnum($enums, $code, (string) $properties[$code]) ?: false;
    }

    return $properties;
}

/** Находит ранее импортированные элементы по стабильному внешнему ID. */
function loadExistingElements(int $iblockId): array
{
    $existing = [];
    $result = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $iblockId],
        false,
        false,
        ['ID', 'XML_ID']
    );

    while ($item = $result->Fetch()) {
        $xmlId = (string) $item['XML_ID'];
        if (strpos($xmlId, 'VACANCY_CSV_') !== 0) {
            continue;
        }
        if (isset($existing[$xmlId])) {
            throw new RuntimeException("Обнаружены дубли элементов с XML_ID {$xmlId}.");
        }
        $existing[$xmlId] = (int) $item['ID'];
    }

    return $existing;
}

/** Добавляет новую или обновляет ранее импортированную вакансию. */
function saveVacancy(
    int $iblockId,
    int $userId,
    array $row,
    array &$enums,
    array &$existing
): string {
    $number = (int) $row[0];
    $xmlId = 'VACANCY_CSV_' . $number;
    $name = normalizeText($row[3]);
    $properties = makePropertyValues($row, $enums);

    $element = new CIBlockElement();

    if (isset($existing[$xmlId])) {
        $id = $existing[$xmlId];

        if (!$element->Update($id, [
            'NAME' => $name,
            'MODIFIED_BY' => $userId,
        ])) {
            throw new RuntimeException("Ошибка обновления элемента {$id}: " . $element->LAST_ERROR);
        }

        // Изменяем только переданные свойства; первоначальная DATE сохраняется.
        CIBlockElement::SetPropertyValuesEx($id, $iblockId, $properties);
        return 'updated';
    }

    $properties['DATE'] = date('d.m.Y H:i:s');
    $id = $element->Add([
        'IBLOCK_ID' => $iblockId,
        'IBLOCK_SECTION_ID' => false,
        'XML_ID' => $xmlId,
        'NAME' => $name,
        'ACTIVE' => 'Y',
        'CREATED_BY' => $userId,
        'MODIFIED_BY' => $userId,
        'PROPERTY_VALUES' => $properties,
    ]);

    if (!$id) {
        throw new RuntimeException('Ошибка создания: ' . $element->LAST_ERROR);
    }

    $existing[$xmlId] = (int) $id;
    return 'created';
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

try {
    $records = readVacancies($csvFile);
} catch (Throwable $error) {
    http_response_code(500);
    exit(escapeHtml($error->getMessage()));
}

$validCount = 0;
$invalidRecords = [];
foreach ($records as $record) {
    if ($record['error'] === '') {
        ++$validCount;
    } else {
        $invalidRecords[] = $record;
    }
}

$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_bitrix_sessid()) {
        http_response_code(403);
        exit('Неверная сессия. Обновите страницу и повторите действие.');
    }

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if (!in_array($action, ['one', 'all'], true)) {
        http_response_code(400);
        exit('Неизвестное действие.');
    }

    if ($action === 'all' && function_exists('set_time_limit')) {
        @set_time_limit(180);
    }

    $report = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

    try {
        $enums = loadEnums($iblockId);
        $existing = loadExistingElements($iblockId);
        $processed = 0;

        foreach ($records as $record) {
            if ($record['error'] !== '') {
                ++$report['skipped'];
                $report['errors'][] = 'Запись №' . $record['number'] . ': ' . $record['error'];
                continue;
            }

            try {
                $status = saveVacancy(
                    $iblockId,
                    (int) $USER->GetID(),
                    $record['data'],
                    $enums,
                    $existing
                );
                ++$report[$status];
            } catch (Throwable $error) {
                ++$report['skipped'];
                $report['errors'][] = 'Вакансия №' . $record['data'][0] . ': ' . $error->getMessage();
            }

            ++$processed;
            if ($action === 'one' && $processed >= 1) {
                break;
            }
        }
    } catch (Throwable $error) {
        $report['errors'][] = 'Импорт не начался: ' . $error->getMessage();
    }
}

?><!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Импорт вакансий</title>
    <style>
        body {font: 15px/1.5 Arial, sans-serif; max-width: 960px; padding: 24px; margin: auto; color: #202735;}
        .panel {border: 1px solid #d7dce5; border-radius: 8px; padding: 16px; margin: 16px 0;}
        .muted {color: #626c7b;}
        button {padding: 10px 16px; margin: 4px 8px 4px 0; cursor: pointer;}
        table {width: 100%; border-collapse: collapse;}
        th, td {text-align: left; padding: 8px; border-bottom: 1px solid #e3e6eb; vertical-align: top;}
        .warning {background: #fff5df;}
        .success {background: #e8f8ef;}
        .error {color: #ad2130;}
    </style>
</head>
<body>
    <h1>Импорт вакансий</h1>
    <p class="muted">Инфоблок: <?= $iblockId ?> (VACANCIES). Источник: vacancy.csv.</p>
    <div class="panel">
        <p>Всего записей: <b><?= count($records) ?></b>; корректных: <b><?= $validCount ?></b>;
            с ошибками: <b><?= count($invalidRecords) ?></b>.</p>
        <p>При открытии страницы база данных не изменяется. Импорт запускается только кнопкой ниже.</p>
        <form method="post">
            <?= bitrix_sessid_post() ?>
            <button type="submit" name="action" value="all"
                onclick="return confirm('Импортировать все корректные вакансии? Ранее импортированные записи будут обновлены.');">
                Импортировать все
            </button>
        </form>
        <p class="muted">Повторный импорт обновляет записи по XML_ID, не создавая дубли.
            Все новые вакансии активны: последний столбец CSV не используется, поскольку его назначение не описано.</p>
    </div>

    <?php if ($report !== null): ?>
        <div class="panel success">
            <h2>Результат импорта</h2>
            <p>Добавлено: <b><?= $report['created'] ?></b>;
                обновлено: <b><?= $report['updated'] ?></b>;
                пропущено: <b><?= $report['skipped'] ?></b>.</p>
            <?php if ($report['errors']): ?>
                <h3>Замечания</h3>
                <ul>
                    <?php foreach ($report['errors'] as $message): ?>
                        <li class="error"><?= escapeHtml($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($invalidRecords): ?>
        <div class="panel warning">
            <h2>Некорректные строки CSV</h2>
            <ul>
                <?php foreach ($invalidRecords as $record): ?>
                    <li>Запись №<?= $record['number'] ?>: <?= escapeHtml($record['error']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h2>Предпросмотр первых вакансий</h2>
    <table>
        <thead><tr><th>№ в CSV</th><th>Название</th><th>Комбинат</th><th>Зарплата</th></tr></thead>
        <tbody>
        <?php $shown = 0; foreach ($records as $record): ?>
            <?php if ($record['error'] !== '') { continue; } ?>
            <tr>
                <td><?= escapeHtml($record['data'][0]) ?></td>
                <td><?= escapeHtml(normalizeText($record['data'][3])) ?></td>
                <td><?= escapeHtml(normalizeText($record['data'][1])) ?></td>
                <td><?= escapeHtml(normalizeText($record['data'][7])) ?></td>
            </tr>
            <?php if (++$shown >= 8) { break; } ?>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
