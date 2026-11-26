<?php
/**
 * 2023-09-10 18:32 (обновлено 2026-11-26)
 * michaelpopov
 *
 * 🧪 Тесты сансары. Запуск: php project_Make_Tests.php
 */

declare(strict_types=1);

require_once __DIR__ . '/project_Make.php';

$tests_Passed = 0;
$tests_Failed = 0;

function check(bool $condition, string $testName): void
{
    global $tests_Passed, $tests_Failed;
    if ($condition) {
        $tests_Passed++;
        echo "✔ $testName\n";
    } else {
        $tests_Failed++;
        echo "✘ $testName\n";
    }
}

/** Простые переходы: порядок узлов по первой встрече */
function merMaid_Simple_2_PHP_Test(): void
{
    $mermaid = <<<EOD
```mermaid
graph TD
    table_Fields_4_Copy --> crm_product_fields
    crm_product_fields --> product_Fields_Check
    crm_product_fields --> table_Fields_4_Copy
```
EOD;

    $result = merMaid_Simple_2_PHP($mermaid);
    $wanted = ['table_Fields_4_Copy',
               'crm_product_fields',
               'product_Fields_Check'];

    check($wanted === $result, 'merMaid_Simple_2_PHP: порядок и уникальность узлов');
}

/** Разбор строк с кавычками, кириллицей и подписями рёбер */
function merMaid_Line_2_Nodes_Test(): void
{
    check(merMaid_Line_2_Nodes('    A --> B') === ['A', 'B'],
          'line: простой переход');
    check(merMaid_Line_2_Nodes('"А1 --> |f2| s2"') === ['А1', 's2'],
          'line: кавычки + подпись ребра + кириллица');
    check(merMaid_Line_2_Nodes('A2 --> |f1 | s3') === ['A2', 's3'],
          'line: подпись ребра без кавычек');
    check(merMaid_Line_2_Nodes('start --> state1[label]') === ['start', 'state1'],
          'line: текст узла в скобках');
    check(merMaid_Line_2_Nodes('graph TD') === [],
          'line: не переход — пусто');
}

/** Генерация заготовок карм: валидные имена функций, дубликаты исключены */
function merMaid2PHP_Karmas_Test(): void
{
    $mermaidCode = <<<EOD
    graph TD
    "А1 --> |f2| s2"
    "А1 --> | f3  | s1"
    A2 --> |f1 | s3
    EOD;

    $php_Code = merMaid_2_PHP_Karmas($mermaidCode, KARMA_FUNCTION_SEPARATOR);

    // Все заготовки — синтаксически корректные объявления функций
    $isValidSyntax = preg_match_all('/^function [\w\x80-\xFF]+\(array &\$nooShere, string &\$karma\): void \{\n\/\/TODO\n\}$/m',
                                    $php_Code, $matches) === 5; // А1, s2, s1, A2, s3

    // Каждая функция уникальна
    preg_match_all('/^function ([\w\x80-\xFF]+)/m', $php_Code, $names);
    $isUnique = count($names[1]) === count(array_unique($names[1]));

    check($isValidSyntax, 'merMaid_2_PHP_Karmas: корректный PHP-синтаксис заготовок');
    check($isUnique, 'merMaid_2_PHP_Karmas: уникальность функций');
}

/** Добавление новых функций без изменения существующих */
function functions_New_Add_Test(): void
{
    $sep = KARMA_FUNCTION_SEPARATOR;

    $phpOld = "<?php\n\nfunction existingFunction() {\n//TODO\n}\nexisting(\$NOOSPHERE, \$KARMA);\n";

    $new_A = 'function newFunction(array &$nooShere, string &$karma): void {' .
             "\n//TODO\n}";
    $new_B = 'function existing(array &$nooShere, string &$karma): void {' .
             "\n//TODO\n}";
    $phpNew = $new_A . $sep . $new_B;

    $result = functions_New_Add($phpOld, $phpNew, $sep);

    check(strpos($result, 'function newFunction') !== false,
          'functions_New_Add: новая функция добавлена');
    check(substr_count($result, 'function existing(') === 1,
          'functions_New_Add: существующая не продублирована');
    check(strpos($result, 'existing($NOOSPHERE, $KARMA);') !== false
          && strpos($result, 'function newFunction') < strpos($result, 'existing($NOOSPHERE'),
          'functions_New_Add: новые функции вставлены до строки запуска');
}

/** Полный цикл: генерация -> линт -> повторная генерация идемпотентна */
function karmas_Make_Integration_Test(): void
{
    $dir = sys_get_temp_dir() . '/karma_test_' . getmypid();
    mkdir($dir, 0777, true);

    $md = "$dir/demo.md";
    file_put_contents($md, "```mermaid\ngraph TD\nstart --> state1\nstate1 --> end_Event\n```\n");

    // Копируем генератор в песочницу и подменяем имя дхармы,
    // чтобы __DIR__ внутри karmas_Make указывал на песочницу.
    $gen = file_get_contents(__DIR__ . '/project_Make.php');
    $gen = str_replace("['aMain_New']", "['demo']", $gen);
    file_put_contents("$dir/pm.php", $gen);

    $run = function (string $cmd) { return shell_exec($cmd . ' 2>&1'); };
    $run("php $dir/pm.php"); // первая генерация (файл demo.php ещё нет)

    $phpFile = "$dir/demo.php";
    $lint = trim((string)$run("php -l $phpFile"));

    $content1 = file_get_contents($phpFile);
    $run("php $dir/pm.php"); // повтор: ничего не должно измениться
    clearstatcache();
    $content2 = file_get_contents($phpFile);

    check(strpos($lint, 'No syntax errors') !== false,
          "integration: сгенерированный файл валиден ($lint)");
    check($content1 === $content2, 'integration: повторная генерация идемпотентна');
    check(strpos($content1, 'function start(') !== false
          && strpos($content1, 'function end_Event(') !== false
          && strpos($content1, 'start($NOOSPHERE, $KARMA);') !== false,
          'integration: все состояния и запуск на месте');

    // Новое состояние в дхарме -> добавляется в существующий файл
    file_put_contents($md, "```mermaid\ngraph TD\nstart --> state1\nstate1 --> middle\nmiddle --> end_Event\n```\n");
    $run("php $dir/pm.php");
    clearstatcache();
    $content3 = file_get_contents($phpFile);
    check(strpos($content3, 'function middle(') !== false
          && substr_count($content3, 'function start(') === 1,
          'integration: новое состояние добавлено, старые сохранены');
    check(strpos($content3, 'function middle(') < strpos($content3, 'start($NOOSPHERE'),
          'integration: новая функция вставлена до строки запуска');

    // Сгенерированная программа синтаксически валидна и исполняется
    $lint3 = trim((string)$run("php -l $phpFile"));
    check(strpos($lint3, 'No syntax errors') !== false,
          "integration: файл после дополнения валиден ($lint3)");

    array_map('unlink', glob("$dir/*"));
    rmdir($dir);
}

merMaid_Simple_2_PHP_Test();
merMaid_Line_2_Nodes_Test();
merMaid2PHP_Karmas_Test();
functions_New_Add_Test();
karmas_Make_Integration_Test();

echo "\nПройдено: $tests_Passed, провалено: $tests_Failed\n";
exit($tests_Failed === 0 ? 0 : 1);
