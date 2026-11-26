<?php
/**
 * 2023-09-10 17:47 (обновлено 2026-11-26)
 * michaelpopov
 *
 * ☸️ Сансара: генератор структуры управляющих функций (карм) из файлов-дхарм
 * (Markdown с Mermaid-диаграммами состояний).
 */

declare(strict_types=1);

/** Разделитель заготовок функций внутри промежуточного текста. */
const KARMA_FUNCTION_SEPARATOR = "\n/*func_Separ*/\n";

/**
 * Имена узлов из простых строк вида "A --> B" (без кавычек и подписей рёбер),
 * в порядке первой встречи.
 *
 * @param string $mermaidText текст Mermaid
 * @return string[] имена узлов
 */
function merMaid_Simple_2_PHP(string $mermaidText): array
{
    preg_match_all('/[A-Za-z_\x80-\xFF][A-Za-z0-9_\x80-\xFF]*\s*-->\s*[A-Za-z_\x80-\xFF][A-Za-z0-9_\x80-\xFF]*/',
                   $mermaidText,
                   $matches);

    $nodeNames = [];

    foreach ($matches[0] as $match) {
        foreach (explode('-->', $match) as $node) {
            $node = trim($node);
            if ($node !== '' && !in_array($node, $nodeNames, true)) {
                $nodeNames[] = $node;
            }
        }
    }

    return $nodeNames;
}

/**
 * Разобрать строку перехода Mermaid и вернуть имена её узлов.
 *
 * Поддерживаются варианты:
 *   A --> B
 *   "А1 --> |f2| s2"        (кавычки + подпись ребра)
 *   A --> |условие| B
 *   A -- текст --> B
 *
 * @param string $line одна строка mermaid-графа
 * @return string[] два имени узлов либо пустой массив, если переход не найден
 */
function merMaid_Line_2_Nodes(string $line): array
{
    $line = trim($line, " \t;");

    // Строка целиком может быть заключена в кавычки: "A --> |f| B"
    if (strlen($line) >= 2 && $line[0] === '"' && substr($line, -1) === '"') {
        $line = trim(substr($line, 1, -1));
    }

    if (($arrowPos = strpos($line, '-->')) === false) {
        return [];
    }

    $left  = substr($line, 0, $arrowPos);
    $right = substr($line, $arrowPos + 3);

    $namePattern = '/["\']?\s*([A-Za-z_\x80-\xFF][A-Za-z0-9_\x80-\xFF]*)/';

    // Левый узел — первое имя в строке до стрелки
    $nodes = [];
    if (preg_match($namePattern, $left, $m)) {
        $nodes[] = $m[1];
    }

    // Правый узел — последнее имя после стрелки (подписи |...| могут содержать слова)
    $right = preg_replace('/\|[^|]*\|/', ' ', $right); // убираем подписи рёбер |...|
    $right = preg_replace('/\[\^?[^\]]*\]/', ' ', $right); // убираем текст в скобках [..], [(..)]
    if (preg_match_all($namePattern, $right, $m)) {
        $names = $m[1];
        if ($names) {
            $nodes[] = $names[count($names) - 1];
        }
    }

    return count($nodes) === 2 ? $nodes : [];
}

/**
 * Создать заготовки функций карм из кода файла Mermaid.
 *
 * @param string $mermaid            файл кода Mermaid
 * @param string $function_Separator разделитель заготовок
 * @return string
 */
function merMaid_2_PHP_Karmas(string $mermaid,
                              string $function_Separator): string
{
    $lines = preg_split('/\r\n|\r|\n/', $mermaid);
    $karmaFunctions = [];

    foreach ($lines as $line) {
        if (strpos($line, '-->') === false) {
            continue;
        }
        $nodes = merMaid_Line_2_Nodes($line);
        foreach ($nodes as $nodeName) {
            $karmaFunctions[$nodeName] =
                'function ' . $nodeName .
                '(array &$nooShere, string &$karma): void {' .
                PHP_EOL . '//TODO' . PHP_EOL . '}';
        }
    }

    return implode($function_Separator, $karmaFunctions);
}

/**
 * Вернуть первый элемент дхармы (стартовое состояние)
 *
 * @param string $fileName путь к .md файлу
 * @return string
 * @author michaelpopov
 * @date   2023-09-11 09:57
 */
function function_run_Add(string $fileName): string
{
    $mermaid = file_get_contents($fileName);
    $dharma = merMaid_Simple_2_PHP($mermaid);

    if (!$dharma) {
        throw new RuntimeException("В дхарме $fileName не найдено ни одного перехода -->");
    }

    return $dharma[0] . '($NOOSPHERE, $KARMA);';
}

/**
 * Добавляет новые функции из $php_New в $php_Old, если их еще нет в $php_Old.
 *
 * @param string $php_Old
 * @param string $php_New
 * @param string $separator
 * @return string $php_Old с новыми функциями из $php_New
 */
function functions_New_Add(string $php_Old,
                           string $php_New,
                           string $separator): string
{
    $linesNew = explode($separator, $php_New);

    // Список функций в $php_Old
    $oldFunctions = [];
    if (preg_match_all('/^function\s+(\w+)/m', $php_Old, $m)) {
        foreach ($m[1] as $name) {
            $oldFunctions[$name] = true;
        }
    }

    // Вставляем новые заготовки перед последней строкой запуска (после всех функций),
    // либо добавляем в конец.
    $toAdd = [];
    foreach ($linesNew as $chunk) {
        $chunk = trim($chunk);
        if ($chunk === '') {
            continue;
        }
        if (preg_match('/^function\s+(\w+)/', $chunk, $m)) {
            $newFunctionName = $m[1];
            if (!isset($oldFunctions[$newFunctionName])) {
                $toAdd[] = $chunk;
                $oldFunctions[$newFunctionName] = true;
            }
        }
    }

    if (!$toAdd) {
        return $php_Old;
    }

    $block = implode(PHP_EOL, $toAdd);

    // Если в конце файла есть вызов стартовой функции — вставляем функции перед ним
    if (preg_match('/(?<head>.*)(?<run>^[\w\x80-\xFF]+\(\$NOOSPHERE,\s*\$KARMA\);[^\n]*\n?)/m',
                   $php_Old, $m)) {
        return rtrim($m['head']) . PHP_EOL . $block . PHP_EOL . $m['run'];
    }

    return rtrim($php_Old) . PHP_EOL . $block . PHP_EOL;
}

/**
 * Заголовок создаваемого PHP-файла карм.
 */
function karmas_PHP_Header(): string
{
    return <<<EOD
<?php

declare(strict_types=1);

\$NOOSPHERE = [];
\$KARMA = '';

EOD;
}

/**
 * Сгенерировать/дополнить файл карм по файлу-дхарме.
 *
 * @param string $fileName имя файла .md без расширения
 * @return void
 * @author michaelpopov
 * @date   2023-09-10 13:11
 */
function karmas_Make(string $fileName): void
{
    $file_md = __DIR__ . DIRECTORY_SEPARATOR . "$fileName.md";

    if (!file_exists($file_md)) {
        throw new RuntimeException("Нет файла $file_md");
    }

    $function_Separator = KARMA_FUNCTION_SEPARATOR;

    $mermaid = file_get_contents($file_md);

    $karmas_PHP_New = merMaid_2_PHP_Karmas($mermaid, $function_Separator);

    $karmas_PHP_Old = karmas_PHP_Header();

    $file_karmas_PHP_Old = __DIR__ . DIRECTORY_SEPARATOR . "$fileName.php";

    if (file_exists($file_karmas_PHP_Old)) {
        $karmas_PHP_Old = file_get_contents($file_karmas_PHP_Old);
    }

    $karmas_PHP = functions_New_Add($karmas_PHP_Old,
                                    $karmas_PHP_New,
                                    $function_Separator);

    if (!file_exists($file_karmas_PHP_Old)) {
        $karmas_PHP = $karmas_PHP . "\n" . function_run_Add($file_md) . "\n";
    }

    file_put_contents($file_karmas_PHP_Old, $karmas_PHP);

    echo "Дхарма $fileName.md -> карма $fileName.php\n";
}

// Точка входа: если файл запущен напрямую (а не подключён тестами)
if (realpath($argv[0] ?? '') === realpath(__FILE__)) {
    $files_md = ['aMain_New']; // Укажите файлы *.md без расширения
    array_map('karmas_Make', $files_md);
}
