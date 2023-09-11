<?php
/**
 * 2023-09-10 17:47
 * michaelpopov
 */

declare(strict_types=1);

function merMaid_Simple_2_PHP($mermaidText): array
{
    // Используем регулярное выражение для поиска строк, начинающихся с пробелов и содержащих стрелки.
    preg_match_all('/\w+\s*-->\s*\w+/', $mermaidText, $matches);

    // Инициализируем пустой массив для хранения имен узлов.
    $nodeNames = [];

    // Обрабатываем найденные строки.
    foreach ($matches[0] as $match) {
        // Разбиваем строку по стрелке и удаляем пробелы.
        $nodes = explode('-->', $match);
        $node1 = trim($nodes[0]);
        $node2 = trim($nodes[1]);

        // Добавляем имена узлов в массив, если они еще не там.
        if (!in_array($node1, $nodeNames)) {
            $nodeNames[] = $node1;
        }
        if (!in_array($node2, $nodeNames)) {
            $nodeNames[] = $node2;
        }
    }

    return $nodeNames;
}

/**
 * Создать заготовки функций карм из кода файла Mermaid
 *
 * @param string $mermaid файл кода Mermaid
 * @param        $function_Separator
 * @return string
 */
function merMaid_2_PHP_Karmas(string $mermaid,
                                     $function_Separator): string
{
    $lines = explode(PHP_EOL, $mermaid);
    $karmaFunctions = [];

    foreach ($lines as $line) {
        if (strpos($line, '-->') > 0) {
            $functions = explode('-->', $line);

            foreach ($functions as $functionName) {
                $karmaFunctions[] = 'function ' . trim($functionName) .
                    '(array &$nooShere, string &$karma): void {' .
                    PHP_EOL . '//TODO' . PHP_EOL . '}' . $function_Separator;
            }
        }
    }

    return implode($function_Separator,
                   array_unique($karmaFunctions));
}

/**
 * @param $fileName
 * @return void
 * @author michaelpopov
 * @date   2023-09-10 13:11
 */
function karmas_Make($fileName): void
{
    $file_md = __DIR__ . DIRECTORY_SEPARATOR . "$fileName.md";

    if (!file_exists($file_md)) {
        throw new Error("Нет файла $file_md");
    }

    $function_Separator = 'func_Separ';

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
        $karmas_PHP = $karmas_PHP . "\n" . function_run_Add($file_md);
    }

    file_put_contents($file_karmas_PHP_Old, $karmas_PHP);
}

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
 * Вернуть первый элемент дхармы
 * @param $fileName
 * @return string
 * @author michaelpopov
 * @date   2023-09-11 09:57
 */
function function_run_Add($fileName): string
{
    $mermaid = file_get_contents($fileName);
    $dharma = merMaid_Simple_2_PHP($mermaid);

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
    // Разбиваем код на строки
    $linesOld = explode("\n", $php_Old);
    $linesNew = explode($separator, $php_New);

    // Список функций в $php_Old
    $oldFunctions = [];

    foreach ($linesOld as $lineOld) {
        // Ищем объявления функций в $php_Old
        if (preg_match('/^function (\w+)/', $lineOld, $matches)) {
            $oldFunctionName = $matches[1];
            $oldFunctions[$oldFunctionName] = true;
        }
    }

    // Добавляем новые функции из $php_New, если их еще нет в $php_Old
    foreach ($linesNew as $lineNew) {
        if (preg_match('/^function (\w+)/', $lineNew, $matches)) {
            $newFunctionName = $matches[1];
            if (!isset($oldFunctions[$newFunctionName])) {
                $php_Old .= "\n" . $lineNew;
                $oldFunctions[$newFunctionName] = true; // Отмечаем функцию как добавленную
            }
        }
    }

    return $php_Old;
}

$files_md = ['aMain_New']; // Укажите файлы *.md без расширения
array_map('karmas_Make', $files_md);