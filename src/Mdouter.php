<?php
/**
 * Created by PhpStorm.
 * User: zhangyi
 * Date: 2019/7/26
 * Time: 11:18
 */

namespace Mdouter;

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\TextAlignment;
use PhpOffice\PhpWord\Style\ListItem;

class Mdouter
{
    // 一张表一个文件，默认的一个库一个文件
    const SPLIT_TYPE_TABLE = 1;

    const FILE_TYPE_MARKDOWN = 1;
    const FILE_TYPE_DOC = 2;
    const FILE_TYPE_EXCEL = 4;
    const FILE_TYPE_PDF = 8;

    private $infoSchema;

    private $dbNames;

    private $tables;

    private $path;

    private $splitType;

    private $fileType;

    private $uniquePattern;

    public function __construct($host, $username, $password, $port = 3306, $charset = 'utf8')
    {
        $this->infoSchema = new InfoSchema($host, $username, $password, $port, $charset);
    }

    public function setDB($dbNames)
    {
        $this->dbNames = $dbNames;
        return $this;
    }

    public function setTables($tables)
    {
        $this->tables = $tables;
        return $this;
    }

    public function setPath($path)
    {
        if ($path{0} == '/' || strpos($path, ':') > 0) {
            $this->path = $path;
        } else {
            $this->path = __DIR__ . '/' . $path;
        }

        $this->path = rtrim($this->path, '/\\');
        if (!is_dir($this->path)) {
            if (!mkdir($this->path, 0755, true)) {
                throw new \RuntimeException('无法在导出位置创建文件夹，请确认权限');
            }
        }

        return $this;
    }

    public function splitByTable()
    {
        $this->splitType = self::SPLIT_TYPE_TABLE;
        return $this;
    }

    public function setFileType($fileType)
    {
        $this->fileType = $fileType;
        return $this;
    }

    public function toMarkdown()
    {
        $this->setFileType(self::FILE_TYPE_MARKDOWN);
        return $this;
    }

    public function toDoc()
    {
        $this->setFileType(self::FILE_TYPE_DOC);
        return $this;
    }

    public function toXls()
    {
        $this->setFileType(self::FILE_TYPE_EXCEL);
        return $this;
    }

    /**
     * 如果有多张符合指定正则模式的表，则只导出其中一张，对于有相同表结构的分表特别有用。
     * @param $pattern
     * @return $this
     */
    public function setUniquePattern($pattern)
    {
        $this->uniquePattern = $pattern;
        return $this;
    }

    public function exportSingleDB($db)
    {

        $tables = $this->infoSchema->getAllTables($db);

        if ($this->uniquePattern) {
            $filteredTables = [];
            foreach ($tables as $table => $comment) {
                $baseTable = preg_replace($this->uniquePattern, '', $table);
                if (!in_array($baseTable, $filteredTables)) {
                    $filteredTables[] = $baseTable;
                } else {
                    unset($tables[$table]);
                }
            }
        }



        switch ($this->fileType) {
            case self::FILE_TYPE_DOC:
                $i = 0;
                $phpword = new PhpWord();
                $phpword->addFontStyle('tableDesc', ['name' => '微软雅黑', 'size' => 14]);
                $phpword->addTableStyle('table', ['borderSize' => 1, 'cellMargin' => 50, 'borderColor' => 'DDDDDD'], ['bgColor' => '409EFF']);
                $phpword->addFontStyle('tableHead', ['name' => '微软雅黑', 'size' => 10, 'bold' => true, 'color' => 'FCFEFD']);
                $section = $phpword->addSection();
                foreach ($tables as $tableName => $comment) {
                    $section->addTextBreak(2);
                    $section->addListItem("{$tableName}（{$comment}）", 0, 'tableDesc', ListItem::TYPE_BULLET_FILLED, ['lineHeight' => 1.5]);
                    $docTable = $section->addTable('table');
                    $docTable->addRow(250);
                    $docTable->addCell(1500)->addText('字段', 'tableHead');
                    $docTable->addCell(1500)->addText('类型', 'tableHead');
                    $docTable->addCell(1500)->addText('允许null', 'tableHead');
                    $docTable->addCell(1600)->addText('默认值', 'tableHead');
                    $docTable->addCell(3200)->addText('注释', 'tableHead');

                    $fields = $this->infoSchema->getAllFields($db, $tableName);
                    foreach ($fields as $field) {
                        if (is_null($field['COLUMN_DEFAULT'])) {
                            $default = 'Null';
                        } elseif ($field['COLUMN_DEFAULT'] == '') {
                            $default = "''";
                        } else {
                            $default = $field['COLUMN_DEFAULT'];
                        }
                        $docTable->addRow(300);
                        $docTable->addCell()->addText($field['COLUMN_NAME'] ?: ' ');
                        $docTable->addCell()->addText($field['COLUMN_TYPE'] ?: ' ');
                        $docTable->addCell()->addText($field['IS_NULLABLE'] ?: ' ');
                        $docTable->addCell()->addText($default);
                        $docTable->addCell()->addText($field['COLUMN_COMMENT'] ? htmlspecialchars($field['COLUMN_COMMENT']) : ' ');
                    }

                    if (($i % 256 == 0 || $i == (count($tables) - 1)) && $i > 0) {
                        IOFactory::createWriter($phpword, 'Word2007')->save($this->path . '/' . $db . '_' . ceil($i / 256) . '.docx');
                        if ($i % 256 == 0 && $i > 0) {
                            $phpword = new PhpWord();
                            $phpword->addFontStyle('tableDesc', ['name' => '微软雅黑', 'size' => 14]);
                            $phpword->addTableStyle('table', ['borderSize' => 1, 'cellMargin' => 50, 'borderColor' => 'DDDDDD'], ['bgColor' => '409EFF']);
                            $phpword->addFontStyle('tableHead', ['name' => '微软雅黑', 'size' => 10, 'bold' => true, 'color' => 'FCFEFD']);
                            $section = $phpword->addSection();
                        }
                    }

                    $i++;
                }

                break;
            case self::FILE_TYPE_MARKDOWN:
            default:
                $file = $this->path . '/' . $db . '.md';
                $fp = fopen($file, 'w');
                if (!$fp) {
                    throw new \RuntimeException('请确认导出位置可写');
                }

                foreach ($tables as $table => $comment) {
                    fwrite($fp, "#### {$table}（{$comment}）\r\n");
                    fwrite($fp, "| 字段 | 类型 | 允许null | 默认值 | 注释 |\r\n");
                    fwrite($fp, "| ---- | ---- | ---- | ---- | ---- |\r\n");
                    $fields = $this->infoSchema->getAllFields($db, $table);
                    foreach ($fields as $field) {
                        if (is_null($field['COLUMN_DEFAULT'])) {
                            $default = 'Null';
                        } elseif ($field['COLUMN_DEFAULT'] == '') {
                            $default = "''";
                        } else {
                            $default = $field['COLUMN_DEFAULT'];
                        }
                        fwrite($fp, "| {$field['COLUMN_NAME']} | {$field['COLUMN_TYPE']} | {$field['IS_NULLABLE']} | {$default} | {$field['COLUMN_COMMENT']} |\r\n");
                    }
                    fwrite($fp, "\r\n\r\n");
                }

                fclose($fp);
                break;
        }
    }

    public function export()
    {
        if (empty($this->dbNames)) {
            throw new \InvalidArgumentException('need set dbNames to export');
        }
        if (is_string($this->dbNames)) {
            $this->exportSingleDB($this->dbNames);
        } elseif (is_array($this->dbNames)) {
            foreach ($this->dbNames as $dbName) {
                $this->exportSingleDB($dbName);
            }
        } else {
            throw new \UnexpectedValueException('type of dbName parameter is bad');
        }
    }
}

(new Mdouter('172.17.12.152', 'root', '123456'))
    ->setDB('xq_2345_common')
    ->setPath('../dict')
    ->setUniquePattern('#_(\d{1,3}|[0-9a-f]{2}|201[89](\d{2}){1,2})$#')
    ->setFileType(Mdouter::FILE_TYPE_DOC)
    ->export();