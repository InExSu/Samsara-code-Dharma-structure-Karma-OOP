<?php
/**
 * 2023-09-10 18:32
 * michaelpopov
 */

declare(strict_types=1);

require_once __DIR__ . '/project_Make.php';

function merMaid2PHP_Karmas_Test(): void
{
    $mermaidCode = <<<EOD
    graph TD
    "А1 --> |f2| s2"
    "А1 --> | f3  | s1"
    "A2 --> |f1 | s3"
    EOD;

    $php_Code = merMaid_2_PHP_Karmas($mermaidCode, 'func_Separ');

    $wanted = 'function f2(array &$nooSphere, string &state):void {}' . "\n" .
        'function f3(array &$nooSphere, string &state):void {}' . "\n" .
        'function f1(array &$nooSphere, string &state):void {}';

    assert($php_Code, $wanted);
}

function functions_New_Add_Test()
{
// Тест 1: Добавление новой функции
    $phpOld1 = <<<EOD
<?php

function existingFunction()
{
    // Существующая функция
}
EOD;

    $phpNew1 = <<<EOD
function newFunction()
{
    // Новая функция
}
EOD;

    $expectedResult1 = <<<EOD
<?php

function existingFunction()
{
    // Существующая функция
}

function newFunction()
{
    // Новая функция
}
EOD;

    $result1 = functions_New_Add($phpOld1, $phpNew1, 'func_Separ');

    if (strpos($result1, 'newFunction') === false) {
        echo "Тест 1 не пройден.\n";
    }

// Тест 2: Не добавлять существующую функцию
    $phpOld2 = <<<EOD
<?php

function existingFunction()
{
    // Существующая функция
}
EOD;

    $phpNew2 = <<<EOD
function existingFunction()
{
    // Существующая функция
}
EOD;

    $expectedResult2 = <<<EOD
<?php

function existingFunction()
{
    // Существующая функция
}
EOD;

    $result2 = functions_New_Add($phpOld2, $phpNew2, 'func_Separ');

    if (trim($result2) !== trim($expectedResult2)) {
        echo "Тест 2 не пройден.\n";
    }
}

function merMaid_Simple_2_PHP_Test()
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

    assert($wanted == $result);
}

merMaid2PHP_Karmas_Test();
functions_New_Add_Test();
merMaid_Simple_2_PHP_Test();